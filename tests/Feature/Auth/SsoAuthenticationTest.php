<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Sso\SsoClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class SsoAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected string $oidcPrivateKeyPem;

    /**
     * @var array<string, string>
     */
    protected array $oidcJwk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->oidcPrivateKeyPem = file_get_contents(base_path('tests/Fixtures/oidc/private.pem')) ?: '';
        $this->oidcJwk = $this->jwkFromPrivateKey($this->oidcPrivateKeyPem);

        config()->set('sso.server_base_url', 'https://sso-server.test');
        config()->set('sso.authorize_endpoint', '/oauth/authorize');
        config()->set('sso.token_endpoint', '/api/oauth/token');
        config()->set('sso.userinfo_endpoint', '/api/oauth/userinfo');
        config()->set('sso.oidc_jwks_endpoint', '/.well-known/jwks.json');
        config()->set('sso.oidc_expected_issuer', 'https://sso-server.test');
        config()->set('sso.client_id', 'portal-client');
        config()->set('sso.client_secret', 'secret-value');
        config()->set('sso.redirect_uri', 'http://sso-client.test/auth/sso/callback');
        config()->set('sso.scopes', ['openid', 'profile', 'email']);
    }

    /**
     * @return array<string, array<string, array<string, bool|string|null>>>
     */
    private function pendingAuthorizationSession(
        string $state = 'valid-state',
        string $codeVerifier = 'verifier-value',
        ?string $nonce = 'oidc-nonce',
        bool $scopeContainsOpenId = true,
    ): array {
        return [
            config('sso.pending_auth_session_key') => [
                $state => [
                    'state' => $state,
                    'code_verifier' => $codeVerifier,
                    'nonce' => $nonce,
                    'issued_at' => now()->toIso8601String(),
                    'scope_contains_openid' => $scopeContainsOpenId,
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function idToken(?string $nonce = 'oidc-nonce', array $overrides = []): string
    {
        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => $this->oidcJwk['kid'],
        ], JSON_THROW_ON_ERROR));

        $payload = $this->base64UrlEncode(json_encode(array_merge([
            'iss' => 'https://sso-server.test',
            'sub' => 'user-123',
            'aud' => 'portal-client',
            'iat' => now()->timestamp,
            'exp' => now()->addMinutes(5)->timestamp,
            'nonce' => $nonce,
        ], $overrides), JSON_THROW_ON_ERROR));

        $signingInput = $header.'.'.$payload;
        $signature = '';
        openssl_sign($signingInput, $signature, $this->oidcPrivateKeyPem, OPENSSL_ALGO_SHA256);

        return $signingInput.'.'.$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * @return array<string, string>
     */
    private function jwkFromPrivateKey(string $privateKeyPem): array
    {
        $resource = openssl_pkey_get_private($privateKeyPem);

        $this->assertNotFalse($resource);

        $details = openssl_pkey_get_details($resource);

        $this->assertIsArray($details);

        /** @var array<string, string> $rsa */
        $rsa = $details['rsa'];

        return [
            'kty' => 'RSA',
            'kid' => 'client-test-oidc-key-1',
            'use' => 'sig',
            'alg' => 'RS256',
            'n' => $this->base64UrlEncode($rsa['n']),
            'e' => $this->base64UrlEncode($rsa['e']),
        ];
    }

    /**
     * @return array{keys: array<int, array<string, string>>}
     */
    private function jwksPayload(): array
    {
        return [
            'keys' => [
                $this->oidcJwk,
            ],
        ];
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
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $pendingAuthorizations = session(config('sso.pending_auth_session_key'), []);
        $this->assertIsArray($pendingAuthorizations);
        $this->assertArrayHasKey($query['state'] ?? '', $pendingAuthorizations);
        $this->assertSame($query['state'] ?? null, $pendingAuthorizations[$query['state']]['state'] ?? null);
        $this->assertSame($query['nonce'] ?? null, $pendingAuthorizations[$query['state']]['nonce'] ?? null);
        $this->assertNotSame($query['state'] ?? null, $query['nonce'] ?? null);
        $this->assertNotEmpty($pendingAuthorizations[$query['state']]['code_verifier'] ?? null);
        $this->assertTrue((bool) ($pendingAuthorizations[$query['state']]['scope_contains_openid'] ?? false));

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.login_redirect.started',
            'description' => 'Client login redirect started.',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.nonce.issued',
            'description' => 'Client OIDC nonce issued.',
        ]);
    }

    #[Group('security')]
    public function test_redirect_endpoint_omits_nonce_when_openid_scope_is_not_requested(): void
    {
        config()->set('sso.scopes', ['profile', 'email']);

        $response = $this->get('/auth/sso/redirect');

        $response->assertRedirect();

        $location = $response->headers->get('Location');

        $this->assertNotNull($location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertArrayNotHasKey('nonce', $query);

        $pendingAuthorizations = session(config('sso.pending_auth_session_key'), []);
        $this->assertIsArray($pendingAuthorizations);
        $this->assertArrayHasKey($query['state'] ?? '', $pendingAuthorizations);
        $this->assertFalse((bool) ($pendingAuthorizations[$query['state']]['scope_contains_openid'] ?? true));
        $this->assertNull($pendingAuthorizations[$query['state']]['nonce'] ?? null);

        $this->assertDatabaseMissing('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.nonce.issued',
        ]);
    }

    #[Group('security')]
    public function test_redirect_endpoint_keeps_multiple_pending_auth_contexts_separate(): void
    {
        $first = $this->get('/auth/sso/redirect');
        $second = $this->get('/auth/sso/redirect');

        $first->assertRedirect();
        $second->assertRedirect();

        $firstLocation = $first->headers->get('Location');
        $secondLocation = $second->headers->get('Location');

        $this->assertNotNull($firstLocation);
        $this->assertNotNull($secondLocation);

        parse_str((string) parse_url($firstLocation, PHP_URL_QUERY), $firstQuery);
        parse_str((string) parse_url($secondLocation, PHP_URL_QUERY), $secondQuery);

        $pendingAuthorizations = session(config('sso.pending_auth_session_key'), []);

        $this->assertCount(2, $pendingAuthorizations);
        $this->assertArrayHasKey($firstQuery['state'] ?? '', $pendingAuthorizations);
        $this->assertArrayHasKey($secondQuery['state'] ?? '', $pendingAuthorizations);
        $this->assertNotSame($firstQuery['state'] ?? null, $secondQuery['state'] ?? null);
        $this->assertNotSame($firstQuery['nonce'] ?? null, $secondQuery['nonce'] ?? null);
    }

    #[Group('security')]
    public function test_callback_with_invalid_state_does_not_authenticate_the_user(): void
    {
        $response = $this
            ->withSession($this->pendingAuthorizationSession('expected-state'))
            ->get('/auth/sso/callback?code=valid-code&state=other-state');

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Ervenytelen vagy lejart SSO allapot. Probald ujra a bejelentkezest.');

        $this->assertGuest();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.callback.failed',
            'description' => 'Client authentication callback failed.',
        ]);
    }

    #[Group('security')]
    public function test_callback_with_missing_code_is_handled_gracefully(): void
    {
        $response = $this
            ->withSession($this->pendingAuthorizationSession())
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
            ->withSession($this->pendingAuthorizationSession())
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
            ->withSession($this->pendingAuthorizationSession())
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
            ->withSession($this->pendingAuthorizationSession())
            ->get('/auth/sso/callback?state=valid-state&error=access_denied');

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'A bejelentkezes nem folytathato, mert ehhez az alkalmazashoz nincs hozzaferese.');

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
    public function test_callback_handles_invalid_request_error_from_provider_separately(): void
    {
        $response = $this
            ->withSession($this->pendingAuthorizationSession())
            ->get('/auth/sso/callback?state=valid-state&error=invalid_request');

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'A bejelentkezesi kerest a szolgaltato elutasitotta. Inditsa ujra a folyamatot.');

        $this->assertGuest();
    }

    #[Group('security')]
    public function test_callback_handles_userinfo_failure(): void
    {
        Http::fake([
            'https://sso-server.test/api/oauth/token' => Http::response([
                'message' => 'OAuth token issued successfully.',
                'data' => [
                    'access_token' => 'access-token',
                    'id_token' => $this->idToken(),
                ],
            ], 200),
            'https://sso-server.test/.well-known/jwks.json' => Http::response($this->jwksPayload(), 200),
            'https://sso-server.test/api/oauth/userinfo' => Http::response([
                'message' => 'User info request forbidden.',
            ], 403),
        ]);

        $response = $this
            ->withSession($this->pendingAuthorizationSession())
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
                'data' => [
                    'access_token' => 'access-token',
                    'id_token' => $this->idToken(),
                ],
            ], 200),
            'https://sso-server.test/.well-known/jwks.json' => Http::response($this->jwksPayload(), 200),
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
            ->withSession($this->pendingAuthorizationSession())
            ->get('/auth/sso/callback?code=valid-code&state=valid-state');

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'sso.user@example.test')->first();

        $this->assertNotNull($user);
        $this->assertSame('user-123', $user->sso_user_id);
        $this->assertSame('SSO User', $user->name);
        $this->assertAuthenticatedAs($user);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.callback.succeeded',
            'description' => 'Client authentication callback succeeded.',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.session.established',
            'description' => 'Client session established.',
        ]);

        $callbackActivity = Activity::query()
            ->where('event', 'client_auth.callback.succeeded')
            ->latest()
            ->firstOrFail();

        $this->assertArrayNotHasKey('access_token', $callbackActivity->properties->toArray());
        $this->assertArrayNotHasKey('client_secret', $callbackActivity->properties->toArray());

        $identityValidationContexts = session(config('sso.identity_validation_session_key'), []);
        $this->assertIsArray($identityValidationContexts);
        $this->assertArrayHasKey('valid-state', $identityValidationContexts);
        $this->assertSame('oidc-nonce', $identityValidationContexts['valid-state']['expected_nonce'] ?? null);
        $this->assertSame('validated_from_id_token', $identityValidationContexts['valid-state']['validation_status'] ?? null);
        $this->assertNotNull($identityValidationContexts['valid-state']['validated_at'] ?? null);

        $pendingContexts = session(config('sso.pending_auth_session_key'), []);
        $this->assertIsArray($pendingContexts);
        $this->assertArrayNotHasKey('valid-state', $pendingContexts);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.nonce.context_retained',
            'description' => 'Client nonce context retained for downstream identity validation.',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.id_token.received',
            'description' => 'Client ID token received.',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.nonce.validated',
            'description' => 'Client nonce validated against ID token.',
        ]);
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
                'data' => [
                    'access_token' => 'access-token',
                    'id_token' => $this->idToken(),
                ],
            ], 200),
            'https://sso-server.test/.well-known/jwks.json' => Http::response($this->jwksPayload(), 200),
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
            ->withSession($this->pendingAuthorizationSession())
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
                'data' => [
                    'access_token' => 'access-token',
                    'id_token' => $this->idToken(),
                ],
            ], 200),
            'https://sso-server.test/.well-known/jwks.json' => Http::response($this->jwksPayload(), 200),
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
            ->withSession($this->pendingAuthorizationSession())
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
            ->withSession($this->pendingAuthorizationSession())
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
                'data' => [
                    'access_token' => 'access-token',
                    'id_token' => $this->idToken(),
                ],
            ], 200),
            'https://sso-server.test/.well-known/jwks.json' => Http::response($this->jwksPayload(), 200),
            'https://sso-server.test/api/oauth/userinfo' => Http::response([
                'sub' => 'user-123',
                'email' => 'top.level@example.test',
                'name' => 'Top Level User',
            ], 200),
        ]);

        $response = $this
            ->withSession($this->pendingAuthorizationSession())
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
                'data' => [
                    'access_token' => 'access-token',
                    'id_token' => $this->idToken(),
                ],
            ], 200),
            'https://sso-server.test/.well-known/jwks.json' => Http::response($this->jwksPayload(), 200),
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
            ->withSession($this->pendingAuthorizationSession())
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

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.reauth.redirected',
            'description' => 'Client reauthentication redirect triggered.',
        ]);
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
            ->withSession($this->pendingAuthorizationSession())
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
            ->withSession($this->pendingAuthorizationSession())
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
            ->withSession($this->pendingAuthorizationSession('valid-state', ''))
            ->get('/auth/sso/callback?code=valid-code&state=valid-state');

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Hianyzo PKCE verifier miatt nem folytathato a bejelentkezes. Inditsd ujra a login folyamatot.');

        $this->assertGuest();
    }

    #[Group('security')]
    public function test_callback_with_missing_nonce_context_for_openid_flow_is_handled_gracefully(): void
    {
        $response = $this
            ->withSession($this->pendingAuthorizationSession('valid-state', 'verifier-value', null, true))
            ->get('/auth/sso/callback?code=valid-code&state=valid-state');

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Hianyzo OIDC nonce allapot miatt nem folytathato a bejelentkezes. Inditsd ujra a login folyamatot.');

        $this->assertGuest();
    }

    #[Group('security')]
    public function test_expected_nonce_can_be_resolved_from_retained_identity_validation_context(): void
    {
        Http::fake([
            'https://sso-server.test/api/oauth/token' => Http::response([
                'message' => 'OAuth token issued successfully.',
                'data' => [
                    'access_token' => 'access-token',
                    'id_token' => $this->idToken('expected-downstream-nonce'),
                ],
            ], 200),
            'https://sso-server.test/.well-known/jwks.json' => Http::response($this->jwksPayload(), 200),
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
            ->withSession($this->pendingAuthorizationSession('nonce-state', 'verifier-value', 'expected-downstream-nonce'))
            ->get('/auth/sso/callback?code=valid-code&state=nonce-state')
            ->assertRedirect(route('dashboard', absolute: false));

        $service = app(SsoClientService::class);

        $session = app('session.store');

        $this->assertSame('expected-downstream-nonce', $service->getExpectedNonceByState($session, 'nonce-state'));
        $this->assertNotNull($service->getIdentityValidationContextByState($session, 'nonce-state'));
        $this->assertNull($service->getPendingAuthorizationContextByState($session, 'nonce-state'));
    }

    #[Group('security')]
    public function test_callback_rejects_mismatched_nonce_from_id_token(): void
    {
        Http::fake([
            'https://sso-server.test/api/oauth/token' => Http::response([
                'message' => 'OAuth token issued successfully.',
                'data' => [
                    'access_token' => 'access-token',
                    'id_token' => $this->idToken('different-nonce'),
                ],
            ], 200),
            'https://sso-server.test/.well-known/jwks.json' => Http::response($this->jwksPayload(), 200),
        ]);

        $this
            ->withSession($this->pendingAuthorizationSession())
            ->get('/auth/sso/callback?code=valid-code&state=valid-state')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Az OIDC nonce ellenorzes sikertelen. Inditsd ujra a bejelentkezest.');

        $this->assertGuest();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.nonce.validation_failed',
            'description' => 'Client nonce validation failed.',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.id_token.signature_verified',
            'description' => 'Client ID token signature verified.',
        ]);
    }

    #[Group('security')]
    public function test_callback_rejects_invalid_id_token_signature_before_nonce_validation(): void
    {
        $invalidToken = $this->idToken();
        $invalidToken = substr($invalidToken, 0, -2).'xx';

        Http::fake([
            'https://sso-server.test/api/oauth/token' => Http::response([
                'message' => 'OAuth token issued successfully.',
                'data' => [
                    'access_token' => 'access-token',
                    'id_token' => $invalidToken,
                ],
            ], 200),
            'https://sso-server.test/.well-known/jwks.json' => Http::response($this->jwksPayload(), 200),
        ]);

        $this
            ->withSession($this->pendingAuthorizationSession())
            ->get('/auth/sso/callback?code=valid-code&state=valid-state')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Az SSO ID token alairasa ervenytelen.');

        $this->assertGuest();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.id_token.signature_verification_failed',
            'description' => 'Client ID token signature verification failed.',
        ]);

        $this->assertDatabaseMissing('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.nonce.validation_failed',
            'description' => 'Client nonce validation failed.',
        ]);
    }

    #[Group('security')]
    public function test_callback_rejects_id_token_with_issuer_mismatch(): void
    {
        Http::fake([
            'https://sso-server.test/api/oauth/token' => Http::response([
                'message' => 'OAuth token issued successfully.',
                'data' => [
                    'access_token' => 'access-token',
                    'id_token' => $this->idToken('oidc-nonce', ['iss' => 'https://evil-issuer.test']),
                ],
            ], 200),
            'https://sso-server.test/.well-known/jwks.json' => Http::response($this->jwksPayload(), 200),
        ]);

        $this
            ->withSession($this->pendingAuthorizationSession())
            ->get('/auth/sso/callback?code=valid-code&state=valid-state')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Az SSO ID token issuer claimje ervenytelen.');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.id_token.claim_validation_failed',
            'description' => 'Client ID token claim validation failed.',
        ]);
    }

    #[Group('security')]
    public function test_callback_rejects_id_token_with_audience_mismatch(): void
    {
        Http::fake([
            'https://sso-server.test/api/oauth/token' => Http::response([
                'message' => 'OAuth token issued successfully.',
                'data' => [
                    'access_token' => 'access-token',
                    'id_token' => $this->idToken('oidc-nonce', ['aud' => 'different-client']),
                ],
            ], 200),
            'https://sso-server.test/.well-known/jwks.json' => Http::response($this->jwksPayload(), 200),
        ]);

        $this
            ->withSession($this->pendingAuthorizationSession())
            ->get('/auth/sso/callback?code=valid-code&state=valid-state')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Az SSO ID token audience claimje ervenytelen.');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.id_token.claim_validation_failed',
            'description' => 'Client ID token claim validation failed.',
        ]);
    }

    #[Group('security')]
    public function test_callback_rejects_missing_nonce_claim_from_id_token_when_openid_is_required(): void
    {
        Http::fake([
            'https://sso-server.test/api/oauth/token' => Http::response([
                'message' => 'OAuth token issued successfully.',
                'data' => [
                    'access_token' => 'access-token',
                    'id_token' => $this->idToken(null, ['nonce' => null]),
                ],
            ], 200),
            'https://sso-server.test/.well-known/jwks.json' => Http::response($this->jwksPayload(), 200),
        ]);

        $this
            ->withSession($this->pendingAuthorizationSession())
            ->get('/auth/sso/callback?code=valid-code&state=valid-state')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Az SSO ID token nem tartalmaz ervenyes nonce claimet. Inditsd ujra a bejelentkezest.');

        $this->assertGuest();
    }

    #[Group('security')]
    public function test_validate_expected_nonce_allows_deferred_identity_validation_when_returned_nonce_is_not_available(): void
    {
        $request = request()->create('/auth/sso/callback', 'GET');
        $request->setLaravelSession(app('session.store'));

        app(SsoClientService::class)->validateExpectedNonce(
            request: $request,
            expectedNonce: 'expected-nonce',
            returnedNonce: null,
            required: true,
        );

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.nonce.validation_deferred',
            'description' => 'Client nonce validation deferred until identity response is available.',
        ]);
    }

    #[Group('security')]
    public function test_validate_expected_nonce_rejects_mismatched_returned_nonce(): void
    {
        $request = request()->create('/auth/sso/callback', 'GET');
        $request->setLaravelSession(app('session.store'));

        $this->expectException(\App\Exceptions\SsoAuthenticationException::class);
        $this->expectExceptionMessage('Az OIDC nonce ellenorzes sikertelen. Inditsd ujra a bejelentkezest.');

        app(SsoClientService::class)->validateExpectedNonce(
            request: $request,
            expectedNonce: 'expected-nonce',
            returnedNonce: 'different-nonce',
            required: true,
        );
    }

    #[Group('security')]
    public function test_redirect_endpoint_fails_fast_when_required_scopes_are_missing_from_configuration(): void
    {
        config()->set('sso.scopes', ['profile']);

        $this->get('/auth/sso/redirect')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Az SSO kliens konfiguracioja hianyos: a "email" scope kotelezo ehhez a kliens flow-hoz.');
    }

    #[Group('security')]
    public function test_logout_clears_the_authenticated_session(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/auth/logout');

        $response->assertRedirect('/');
        $this->assertGuest();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.session.cleared',
            'description' => 'Client session cleared.',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.logout.completed',
            'description' => 'Client logout completed.',
        ]);
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

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.api',
            'event' => 'client_api.request.unauthorized',
            'description' => 'Client API request unauthorized.',
        ]);
    }
}
