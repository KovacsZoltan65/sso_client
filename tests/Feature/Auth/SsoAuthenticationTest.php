<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
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

    #[Group('security')]
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

    #[Group('security')]
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

    #[Group('security')]
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

    #[Group('security')]
    public function test_callback_with_missing_state_is_handled_gracefully(): void
    {
        $response = $this
            ->withSession([
                config('sso.state_session_key') => 'valid-state',
                config('sso.pkce_verifier_session_key') => 'verifier-value',
            ])
            ->get('/auth/sso/callback?code=valid-code');

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Hianyzik a state ertek a callbackbol.');

        $this->assertGuest();
    }

    #[Group('security')]
    public function test_callback_handles_token_exchange_failure(): void
    {
        Http::fake([
            'https://sso-server.test/api/oauth/token' => Http::response([
                'message' => 'OAuth token request failed.',
                'data' => [],
                'meta' => [],
                'errors' => [
                    'code' => ['The provided authorization code is invalid.'],
                ],
            ], 422),
        ]);

        $response = $this
            ->withSession([
                config('sso.state_session_key') => 'valid-state',
                config('sso.pkce_verifier_session_key') => 'verifier-value',
            ])
            ->get('/auth/sso/callback?code=valid-code&state=valid-state');

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Az SSO token vegpont hibaval valaszolt.');

        $this->assertGuest();
    }

    #[Group('security')]
    public function test_callback_handles_oauth_error_response_from_provider(): void
    {
        Log::spy();

        $response = $this
            ->withSession([
                config('sso.state_session_key') => 'valid-state',
                config('sso.pkce_verifier_session_key') => 'verifier-value',
            ])
            ->get('/auth/sso/callback?state=valid-state&error=access_denied');

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Az SSO szerver hibaval terjen vissza a bejelentkezesbol.');

        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context): bool {
            $serializedContext = json_encode($context);

            return $message === 'SSO authentication callback failed.'
                && ($context['provider_error'] ?? null) === 'access_denied'
                && ! array_key_exists('access_token', $context)
                && ! array_key_exists('client_secret', $context)
                && is_string($serializedContext)
                && ! str_contains($serializedContext, 'access-token')
                && ! str_contains($serializedContext, 'secret-value');
        });

        $this->assertGuest();
    }

    #[Group('security')]
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

    #[Group('security')]
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
        $this->assertSame('user-123', $user->sso_user_id);
        $this->assertSame('SSO User', $user->name);
        $this->assertAuthenticatedAs($user);
    }

    #[Group('security')]
    public function test_successful_callback_links_a_legacy_local_user_by_email_and_persists_sso_user_id(): void
    {
        $legacyUser = User::factory()->create([
            'sso_user_id' => null,
            'email' => 'legacy.user@example.test',
            'name' => 'Legacy User',
        ]);

        Http::fake([
            'https://sso-server.test/api/oauth/token' => Http::response([
                'message' => 'OAuth token issued successfully.',
                'data' => ['access_token' => 'access-token'],
            ], 200),
            'https://sso-server.test/api/oauth/userinfo' => Http::response([
                'message' => 'User info retrieved successfully.',
                'data' => [
                    'id' => 'server-user-77',
                    'email' => 'legacy.user@example.test',
                    'name' => 'Linked Legacy User',
                ],
            ], 200),
        ]);

        $this
            ->withSession([
                config('sso.state_session_key') => 'valid-state',
                config('sso.pkce_verifier_session_key') => 'verifier-value',
            ])
            ->get('/auth/sso/callback?code=valid-code&state=valid-state')
            ->assertRedirect(route('dashboard', absolute: false));

        $legacyUser->refresh();

        $this->assertSame('server-user-77', $legacyUser->sso_user_id);
        $this->assertSame('Linked Legacy User', $legacyUser->name);
        $this->assertAuthenticatedAs($legacyUser);
    }

    #[Group('security')]
    public function test_successful_callback_prefers_existing_sso_user_id_link_over_email_changes(): void
    {
        $linkedUser = User::factory()->create([
            'sso_user_id' => 'server-user-88',
            'email' => 'old.email@example.test',
            'name' => 'Previously Linked',
        ]);

        Http::fake([
            'https://sso-server.test/api/oauth/token' => Http::response([
                'message' => 'OAuth token issued successfully.',
                'data' => ['access_token' => 'access-token'],
            ], 200),
            'https://sso-server.test/api/oauth/userinfo' => Http::response([
                'message' => 'User info retrieved successfully.',
                'data' => [
                    'sub' => 'server-user-88',
                    'email' => 'new.email@example.test',
                    'name' => 'Renamed Linked User',
                ],
            ], 200),
        ]);

        $this
            ->withSession([
                config('sso.state_session_key') => 'valid-state',
                config('sso.pkce_verifier_session_key') => 'verifier-value',
            ])
            ->get('/auth/sso/callback?code=valid-code&state=valid-state')
            ->assertRedirect(route('dashboard', absolute: false));

        $linkedUser->refresh();

        $this->assertSame('server-user-88', $linkedUser->sso_user_id);
        $this->assertSame('new.email@example.test', $linkedUser->email);
        $this->assertSame('Renamed Linked User', $linkedUser->name);
        $this->assertAuthenticatedAs($linkedUser);
    }

    #[Group('security')]
    public function test_callback_rejects_top_level_token_payload_without_envelope_data(): void
    {
        Http::fake([
            'https://sso-server.test/api/oauth/token' => Http::response([
                'access_token' => 'access-token',
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

    #[Group('security')]
    public function test_callback_rejects_top_level_userinfo_payload_without_envelope_data(): void
    {
        Http::fake([
            'https://sso-server.test/api/oauth/token' => Http::response([
                'message' => 'OAuth token issued successfully.',
                'data' => ['access_token' => 'access-token'],
            ], 200),
            'https://sso-server.test/api/oauth/userinfo' => Http::response([
                'sub' => 'user-123',
                'email' => 'top.level@example.test',
                'name' => 'Top Level User',
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
            ->assertSessionHas('error', 'Ervenytelen userinfo valasz erkezett az SSO szervertol.');

        $this->assertGuest();
    }

    #[Group('security')]
    public function test_successful_callback_creates_a_persistent_session_for_protected_pages(): void
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

        $this
            ->withSession([
                config('sso.state_session_key') => 'valid-state',
                config('sso.pkce_verifier_session_key') => 'verifier-value',
            ])
            ->get('/auth/sso/callback?code=valid-code&state=valid-state')
            ->assertRedirect(route('dashboard', absolute: false));

        $dashboardResponse = $this->get('/dashboard');

        $dashboardResponse
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.isAuthenticated', true)
                ->where('auth.isGuest', false)
                ->where('auth.user.email', 'sso.user@example.test')
            );
    }

    #[Group('security')]
    public function test_guest_user_is_redirected_to_login_from_a_protected_page(): void
    {
        $response = $this->get('/dashboard');

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'A munkamenet hianyzik vagy lejart. Jelentkezz be ujra.');

        $this->assertGuest();
    }

    #[Group('security')]
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

    #[Group('security')]
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

    #[Group('security')]
    public function test_callback_with_missing_pkce_verifier_is_handled_gracefully(): void
    {
        $response = $this
            ->withSession([
                config('sso.state_session_key') => 'valid-state',
            ])
            ->get('/auth/sso/callback?code=valid-code&state=valid-state');

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Hianyzo PKCE verifier miatt nem folytathato a bejelentkezes. Inditsd ujra a login folyamatot.');

        $this->assertGuest();
    }

    #[Group('security')]
    public function test_redirect_endpoint_fails_fast_when_required_scopes_are_missing_from_configuration(): void
    {
        config()->set('sso.scopes', ['profile']);

        $this->get('/auth/sso/redirect')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Az SSO kliens konfiguracioja hianyos: a "openid" scope kotelezo ehhez a kliens flow-hoz.');
    }

    #[Group('security')]
    public function test_logout_clears_the_authenticated_session(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/auth/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    #[Group('security')]
    public function test_logout_prevents_access_to_protected_pages_until_reauthenticated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/auth/logout')
            ->assertRedirect('/');

        $this->get('/dashboard')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'A munkamenet hianyzik vagy lejart. Jelentkezz be ujra.');
    }

    #[Group('security')]
    public function test_json_requests_receive_a_consistent_401_reauth_payload(): void
    {
        $this->getJson('/dashboard')
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Authentication required.',
                'meta' => [
                    'redirect_to' => route('login'),
                    'reauth_to' => route('auth.sso.redirect'),
                ],
            ]);
    }
}
