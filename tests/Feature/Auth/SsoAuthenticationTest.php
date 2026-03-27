<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SsoAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('sso.server_base_url', 'https://sso-server.test');
        config()->set('sso.authorize_endpoint', '/oauth/authorize');
        config()->set('sso.token_endpoint', '/api/oauth/token');
        config()->set('sso.userinfo_endpoint', '/api/oauth/userinfo');
        config()->set('sso.client_id', 'portal-client');
        config()->set('sso.client_secret', 'secret-value');
        config()->set('sso.redirect_uri', 'http://sso-client.test/auth/sso/callback');
        config()->set('sso.scopes', ['openid', 'profile', 'email']);
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
    }

    public function test_redirect_endpoint_builds_the_expected_authorize_redirect(): void
    {
        $response = $this->get('/auth/sso/redirect');

        $response->assertRedirect();

        $location = $response->headers->get('Location');

        $this->assertNotNull($location);
        $this->assertStringStartsWith('https://sso-server.test/oauth/authorize?', $location);
        $this->assertStringContainsString('response_type=code', $location);
        $this->assertStringContainsString('client_id=portal-client', $location);
        $this->assertStringContainsString(urlencode('http://sso-client.test/auth/sso/callback'), $location);
        $this->assertTrue(session()->has(config('sso.state_session_key')));
        $this->assertTrue(session()->has(config('sso.pkce_verifier_session_key')));
    }

    public function test_callback_with_invalid_state_does_not_authenticate_the_user(): void
    {
        $response = $this
            ->withSession([config('sso.state_session_key') => 'expected-state'])
            ->get('/auth/sso/callback?code=valid-code&state=other-state');

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Ervenytelen vagy lejart SSO allapot. Probald ujra a bejelentkezest.');

        $this->assertGuest();
    }

    public function test_callback_with_missing_code_is_handled_gracefully(): void
    {
        $response = $this
            ->withSession([config('sso.state_session_key') => 'valid-state'])
            ->get('/auth/sso/callback?state=valid-state');

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Hianyzik az authorization code a callbackbol.');

        $this->assertGuest();
    }

    public function test_callback_handles_token_exchange_failure(): void
    {
        Http::fake([
            'https://sso-server.test/api/oauth/token' => Http::response([
                'message' => 'OAuth token request failed.',
                'error' => 'invalid_grant',
            ], 400),
        ]);

        $response = $this
            ->withSession([
                config('sso.state_session_key') => 'valid-state',
                config('sso.pkce_verifier_session_key') => 'verifier-value',
            ])
            ->get('/auth/sso/callback?code=valid-code&state=valid-state');

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Az SSO token csere OAuth hibaval meghiusult.');

        $this->assertGuest();
    }

    public function test_callback_handles_userinfo_failure(): void
    {
        Http::fake([
            'https://sso-server.test/api/oauth/token' => Http::response([
                'message' => 'OAuth token issued successfully.',
                'data' => ['access_token' => 'access-token'],
            ], 200),
            'https://sso-server.test/api/oauth/userinfo' => Http::response([
                'message' => 'User info request forbidden.',
            ], 403),
        ]);

        $response = $this
            ->withSession([
                config('sso.state_session_key') => 'valid-state',
                config('sso.pkce_verifier_session_key') => 'verifier-value',
            ])
            ->get('/auth/sso/callback?code=valid-code&state=valid-state');

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Az SSO userinfo vegpont hibaval valaszolt.');

        $this->assertGuest();
    }

    public function test_successful_callback_authenticates_the_user_and_provisions_locally(): void
    {
        Http::fake([
            'https://sso-server.test/api/oauth/token' => Http::response([
                'message' => 'OAuth token issued successfully.',
                'data' => ['access_token' => 'access-token'],
            ], 200),
            'https://sso-server.test/api/oauth/userinfo' => Http::response([
                'message' => 'User info retrieved successfully.',
                'data' => [
                    'sub' => 'user-123',
                    'email' => 'sso.user@example.test',
                    'name' => 'SSO User',
                ],
            ], 200),
        ]);

        $response = $this
            ->withSession([
                config('sso.state_session_key') => 'valid-state',
                config('sso.pkce_verifier_session_key') => 'verifier-value',
            ])
            ->get('/auth/sso/callback?code=valid-code&state=valid-state');

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'sso.user@example.test')->first();

        $this->assertNotNull($user);
        $this->assertSame('SSO User', $user->name);
        $this->assertAuthenticatedAs($user);
    }

    public function test_callback_handles_json_token_response_without_access_token(): void
    {
        Http::fake([
            'https://sso-server.test/api/oauth/token' => Http::response([
                'message' => 'OAuth token issued successfully.',
                'data' => ['token_type' => 'Bearer'],
            ], 200),
        ]);

        $response = $this
            ->withSession([
                config('sso.state_session_key') => 'valid-state',
                config('sso.pkce_verifier_session_key') => 'verifier-value',
            ])
            ->get('/auth/sso/callback?code=valid-code&state=valid-state');

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Az SSO token valasz nem tartalmaz ervenyes access tokent.');

        $this->assertGuest();
    }

    public function test_callback_handles_non_json_token_response(): void
    {
        Http::fake([
            'https://sso-server.test/api/oauth/token' => Http::response('<html><body>Server error</body></html>', 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]),
        ]);

        $response = $this
            ->withSession([
                config('sso.state_session_key') => 'valid-state',
                config('sso.pkce_verifier_session_key') => 'verifier-value',
            ])
            ->get('/auth/sso/callback?code=valid-code&state=valid-state');

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Az SSO token vegpont ervenytelen, nem JSON valaszt adott.');

        $this->assertGuest();
    }

    public function test_logout_clears_the_authenticated_session(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/auth/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
    }
}
