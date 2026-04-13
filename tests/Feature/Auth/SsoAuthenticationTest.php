<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\OidcLogoutReceipt;
use App\Models\OidcSessionMapping;
use App\Services\Sso\OidcDiscoveryService;
use App\Services\Sso\OidcSessionMappingCleanupService;
use App\Services\Sso\OidcUserInfoService;
use App\Services\Sso\SsoClientService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
    protected string $legacyOidcPrivateKeyPem;

    /**
     * @var array<string, string>
     */
    protected array $oidcJwk;

    /**
     * @var array<string, string>
     */
    protected array $legacyOidcJwk;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->oidcPrivateKeyPem = file_get_contents(base_path('tests/Fixtures/oidc/private.pem')) ?: '';
        $this->legacyOidcPrivateKeyPem = file_get_contents(base_path('tests/Fixtures/oidc/legacy-private.pem')) ?: '';
        $this->oidcJwk = $this->jwkFromPrivateKey($this->oidcPrivateKeyPem, 'client-test-oidc-key-1');
        $this->legacyOidcJwk = $this->jwkFromPrivateKey($this->legacyOidcPrivateKeyPem, 'client-test-legacy-oidc-key-1');

        config()->set('sso.server_base_url', 'https://sso-server.test');
        config()->set('sso.authorize_endpoint', '/oauth/authorize');
        config()->set('sso.token_endpoint', '/api/oauth/token');
        config()->set('sso.userinfo_endpoint', '/api/oauth/userinfo');
        config()->set('sso.logout_endpoint', '/oidc/logout');
        config()->set('sso.oidc_discovery_endpoint', '/.well-known/openid-configuration');
        config()->set('sso.oidc_jwks_endpoint', '/.well-known/jwks.json');
        config()->set('sso.oidc_expected_issuer', 'https://sso-server.test');
        config()->set('sso.client_id', 'portal-client');
        config()->set('sso.client_secret', 'secret-value');
        config()->set('sso.redirect_uri', 'http://sso-client.test/auth/sso/callback');
        config()->set('sso.logout_return_uri', 'http://sso-client.test/auth/logout/return');
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
    private function idToken(
        ?string $nonce = 'oidc-nonce',
        array $overrides = [],
        ?string $privateKeyPem = null,
        ?string $kid = null,
    ): string
    {
        $signingKeyPem = $privateKeyPem ?? $this->oidcPrivateKeyPem;
        $signingKid = $kid ?? $this->oidcJwk['kid'];

        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => $signingKid,
        ], JSON_THROW_ON_ERROR));

        $payload = $this->base64UrlEncode(json_encode(array_merge([
            'iss' => 'https://sso-server.test',
            'sub' => '123',
            'aud' => 'portal-client',
            'iat' => now()->timestamp,
            'exp' => now()->addMinutes(5)->timestamp,
            'nonce' => $nonce,
            'sid' => 'sid-current-session',
        ], $overrides), JSON_THROW_ON_ERROR));

        $signingInput = $header.'.'.$payload;
        $signature = '';
        openssl_sign($signingInput, $signature, $signingKeyPem, OPENSSL_ALGO_SHA256);

        return $signingInput.'.'.$this->base64UrlEncode($signature);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function logoutToken(
        array $overrides = [],
        ?string $privateKeyPem = null,
        ?string $kid = null,
    ): string {
        $signingKeyPem = $privateKeyPem ?? $this->oidcPrivateKeyPem;
        $signingKid = $kid ?? $this->oidcJwk['kid'];

        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => $signingKid,
        ], JSON_THROW_ON_ERROR));

        $payload = $this->base64UrlEncode(json_encode(array_merge([
            'iss' => 'https://sso-server.test',
            'sub' => '123',
            'aud' => 'portal-client',
            'iat' => now()->timestamp,
            'exp' => now()->addMinutes(5)->timestamp,
            'jti' => (string) str()->uuid(),
            'events' => [
                'http://schemas.openid.net/event/backchannel-logout' => new \stdClass(),
            ],
        ], $overrides), JSON_THROW_ON_ERROR));

        $signingInput = $header.'.'.$payload;
        $signature = '';
        openssl_sign($signingInput, $signature, $signingKeyPem, OPENSSL_ALGO_SHA256);

        return $signingInput.'.'.$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * @return array<string, string>
     */
    private function jwkFromPrivateKey(string $privateKeyPem, string $kid): array
    {
        $resource = openssl_pkey_get_private($privateKeyPem);

        $this->assertNotFalse($resource);

        $details = openssl_pkey_get_details($resource);

        $this->assertIsArray($details);

        /** @var array<string, string> $rsa */
        $rsa = $details['rsa'];

        return [
            'kty' => 'RSA',
            'kid' => $kid,
            'use' => 'sig',
            'alg' => 'RS256',
            'n' => $this->base64UrlEncode($rsa['n']),
            'e' => $this->base64UrlEncode($rsa['e']),
        ];
    }

    /**
     * @return array{keys: array<int, array<string, string>>}
     */
    private function jwksPayload(?array $keys = null): array
    {
        return [
            'keys' => $keys ?? [
                $this->oidcJwk,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function discoveryPayload(array $overrides = []): array
    {
        return array_merge([
            'issuer' => 'https://sso-server.test',
            'authorization_endpoint' => 'https://sso-server.test/oauth/authorize',
            'token_endpoint' => 'https://sso-server.test/api/oauth/token',
            'userinfo_endpoint' => 'https://sso-server.test/api/oauth/userinfo',
            'end_session_endpoint' => 'https://sso-server.test/oidc/logout',
            'jwks_uri' => 'https://sso-server.test/.well-known/jwks.json',
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'scopes_supported' => ['openid', 'profile', 'email'],
            'claims_supported' => ['sub', 'name', 'email', 'email_verified'],
            'frontchannel_logout_supported' => true,
            'frontchannel_logout_session_supported' => true,
            'backchannel_logout_supported' => true,
            'backchannel_logout_session_supported' => true,
            'code_challenge_methods_supported' => ['S256'],
        ], $overrides);
    }

    /**
     * @return array<string, array<string, string|null>>
     */
    private function oidcSessionContext(string $idTokenHint, ?string $subject = '123', ?string $sid = 'sid-current-session'): array
    {
        return [
            config('sso.oidc_session_context_key') => [
                'id_token_hint' => $idTokenHint,
                'id_token_subject' => $subject,
                'issuer' => 'https://sso-server.test',
                'sid' => $sid,
                'stored_at' => now()->toIso8601String(),
            ],
        ];
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Login')
                ->where('loginUrl', route('auth.sso.redirect'))
            );
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
    public function test_redirect_endpoint_can_resolve_authorize_endpoint_from_discovery(): void
    {
        config()->set('sso.authorize_endpoint', null);

        Http::fake([
            'https://sso-server.test/.well-known/openid-configuration' => Http::response($this->discoveryPayload(), 200),
        ]);

        $response = $this->get('/auth/sso/redirect');

        $response->assertRedirect();

        $location = $response->headers->get('Location');

        $this->assertNotNull($location);
        $this->assertStringStartsWith('https://sso-server.test/oauth/authorize?', $location);
    }

    #[Group('security')]
    public function test_callback_can_use_discovery_metadata_for_token_and_jwks_resolution(): void
    {
        config()->set('sso.token_endpoint', null);
        config()->set('sso.oidc_jwks_endpoint', null);
        config()->set('sso.oidc_expected_issuer', null);
        config()->set('sso.userinfo_endpoint', null);

        Http::fake([
            'https://sso-server.test/.well-known/openid-configuration' => Http::response($this->discoveryPayload(), 200),
            'https://sso-server.test/api/oauth/token' => Http::response([
                'message' => 'OAuth token issued successfully.',
                'data' => [
                    'access_token' => 'access-token',
                    'refresh_token' => 'refresh-token',
                    'expires_in' => 3600,
                    'refresh_token_expires_in' => 7200,
                    'token_type' => 'Bearer',
                    'scope' => 'openid profile email',
                    'id_token' => $this->idToken(),
                ],
                'meta' => [],
                'errors' => [],
            ], 200),
            'https://sso-server.test/.well-known/jwks.json' => Http::response($this->jwksPayload(), 200),
            'https://sso-server.test/api/oauth/userinfo' => Http::response([
                'message' => 'User info retrieved successfully.',
                'data' => [
                    'sub' => '123',
                    'name' => 'Jane Example',
                    'email' => 'jane@example.test',
                    'email_verified' => true,
                ],
                'meta' => [],
                'errors' => [],
            ], 200),
        ]);

        $response = $this
            ->withSession($this->pendingAuthorizationSession())
            ->get('/auth/sso/callback?code=valid-code&state=valid-state');

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.oidc.discovery_loaded',
            'description' => 'OIDC discovery metadata loaded.',
        ]);
    }

    #[Group('security')]
    public function test_callback_fails_when_discovery_document_is_invalid(): void
    {
        config()->set('sso.token_endpoint', null);
        config()->set('sso.oidc_jwks_endpoint', null);
        config()->set('sso.oidc_expected_issuer', null);
        config()->set('sso.userinfo_endpoint', null);

        Http::fake([
            'https://sso-server.test/.well-known/openid-configuration' => Http::response([
                'issuer' => 'https://sso-server.test',
                'authorization_endpoint' => 'https://sso-server.test/oauth/authorize',
            ], 200),
            'https://sso-server.test/api/oauth/token' => Http::response([
                'message' => 'OAuth token issued successfully.',
                'data' => [
                    'access_token' => 'access-token',
                    'refresh_token' => 'refresh-token',
                    'expires_in' => 3600,
                    'refresh_token_expires_in' => 7200,
                    'token_type' => 'Bearer',
                    'scope' => 'openid profile email',
                    'id_token' => $this->idToken(),
                ],
                'meta' => [],
                'errors' => [],
            ], 200),
            'https://sso-server.test/.well-known/jwks.json' => Http::response($this->jwksPayload(), 200),
            'https://sso-server.test/api/oauth/userinfo' => Http::response([
                'message' => 'User info retrieved successfully.',
                'data' => [
                    'sub' => '123',
                    'name' => 'Jane Example',
                    'email' => 'jane@example.test',
                    'email_verified' => true,
                ],
                'meta' => [],
                'errors' => [],
            ], 200),
        ]);

        $response = $this
            ->withSession($this->pendingAuthorizationSession())
            ->get('/auth/sso/callback?code=valid-code&state=valid-state');

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Az SSO discovery dokumentum ervenytelen.');

        $this->assertGuest();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.oidc.discovery_validation_failed',
            'description' => 'OIDC discovery metadata validation failed.',
        ]);
    }

    #[Group('security')]
    public function test_discovery_validation_rejects_issuer_mismatch(): void
    {
        Http::fake([
            'https://sso-server.test/.well-known/openid-configuration' => Http::response($this->discoveryPayload([
                'issuer' => 'https://issuer-mismatch.test',
            ]), 200),
        ]);

        $this->expectException(\App\Exceptions\SsoAuthenticationException::class);
        $this->expectExceptionMessage('Az SSO discovery dokumentum ervenytelen.');

        app(OidcDiscoveryService::class)->getProviderMetadata();
    }

    #[Group('security')]
    public function test_discovery_validation_rejects_missing_rs256_algorithm(): void
    {
        Http::fake([
            'https://sso-server.test/.well-known/openid-configuration' => Http::response($this->discoveryPayload([
                'id_token_signing_alg_values_supported' => ['ES256'],
            ]), 200),
        ]);

        $this->expectException(\App\Exceptions\SsoAuthenticationException::class);
        $this->expectExceptionMessage('Az SSO discovery dokumentum ervenytelen.');

        app(OidcDiscoveryService::class)->getProviderMetadata();
    }

    #[Group('security')]
    public function test_discovery_validation_rejects_invalid_endpoint_url(): void
    {
        Http::fake([
            'https://sso-server.test/.well-known/openid-configuration' => Http::response($this->discoveryPayload([
                'jwks_uri' => '/.well-known/jwks.json',
            ]), 200),
        ]);

        $this->expectException(\App\Exceptions\SsoAuthenticationException::class);
        $this->expectExceptionMessage('Az SSO discovery dokumentum ervenytelen.');

        app(OidcDiscoveryService::class)->getProviderMetadata();
    }

    #[Group('security')]
    public function test_discovery_validation_rejects_claims_supported_without_subject(): void
    {
        Http::fake([
            'https://sso-server.test/.well-known/openid-configuration' => Http::response($this->discoveryPayload([
                'claims_supported' => ['email'],
            ]), 200),
        ]);

        $this->expectException(\App\Exceptions\SsoAuthenticationException::class);
        $this->expectExceptionMessage('Az SSO discovery dokumentum ervenytelen.');

        app(OidcDiscoveryService::class)->getProviderMetadata();
    }

    #[Group('security')]
    public function test_client_uses_an_explicit_claim_contract_for_id_token_and_userinfo(): void
    {
        $service = app(OidcUserInfoService::class);

        $this->assertSame(
            ['iss', 'sub', 'aud', 'iat', 'exp', 'nonce', 'sid'],
            $service->expectedIdTokenClaims(),
        );

        $this->assertSame(
            ['sub', 'name', 'email', 'email_verified'],
            $service->expectedUserInfoClaimsForScopes(['openid', 'profile', 'email']),
        );
    }

    #[Group('security')]
    public function test_callback_rejects_userinfo_payload_without_subject(): void
    {
        Http::fake([
            'https://sso-server.test/api/oauth/token' => Http::response([
                'message' => 'OAuth token issued successfully.',
                'data' => [
                    'access_token' => 'access-token',
                    'id_token' => $this->idToken(overrides: ['sub' => 'server-user-42']),
                ],
                'meta' => [],
                'errors' => [],
            ], 200),
            'https://sso-server.test/.well-known/jwks.json' => Http::response($this->jwksPayload(), 200),
            'https://sso-server.test/api/oauth/userinfo' => Http::response([
                'message' => 'User info retrieved successfully.',
                'data' => [
                    'email' => 'missing-sub@example.test',
                    'name' => 'Missing Subject',
                ],
                'meta' => [],
                'errors' => [],
            ], 200),
        ]);

        $this
            ->withSession($this->pendingAuthorizationSession())
            ->get('/auth/sso/callback?code=valid-code&state=valid-state')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Ervenytelen userinfo valasz erkezett az SSO szervertol.');
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
                    'id_token' => $this->idToken(overrides: ['sub' => 'user-123']),
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
                    'id_token' => $this->idToken(overrides: ['sub' => 'user-123']),
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

        $oidcSessionContext = session(config('sso.oidc_session_context_key'));
        $this->assertIsArray($oidcSessionContext);
        $this->assertSame('sid-current-session', $oidcSessionContext['sid'] ?? null);

        $this->assertDatabaseHas('oidc_session_mappings', [
            'sid_hash' => hash('sha256', 'sid-current-session'),
            'user_id' => $user->id,
            'client_id' => 'portal-client',
            'invalidated_at' => null,
        ]);

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

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.sid.bound',
            'description' => 'Client OIDC sid bound to the local auth session.',
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
                    'id_token' => $this->idToken(overrides: ['sub' => 'server-user-77']),
                ],
            ], 200),
            'https://sso-server.test/.well-known/jwks.json' => Http::response($this->jwksPayload(), 200),
            'https://sso-server.test/api/oauth/userinfo' => Http::response([
                'message' => 'User info retrieved successfully.',
                'data' => [
                    'sub' => 'server-user-77',
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
                    'id_token' => $this->idToken(overrides: ['sub' => 'server-user-88']),
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
                    'id_token' => $this->idToken(overrides: ['sub' => 'user-123']),
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
                    'id_token' => $this->idToken(overrides: ['sub' => 'user-123']),
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
                    'id_token' => $this->idToken('expected-downstream-nonce', ['sub' => 'user-123']),
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
    public function test_callback_selects_the_matching_kid_from_a_multi_key_jwks(): void
    {
        Http::fake([
            'https://sso-server.test/api/oauth/token' => Http::response([
                'message' => 'OAuth token issued successfully.',
                'data' => [
                    'access_token' => 'access-token',
                    'id_token' => $this->idToken(),
                ],
            ], 200),
            'https://sso-server.test/.well-known/jwks.json' => Http::response($this->jwksPayload([
                $this->legacyOidcJwk,
                $this->oidcJwk,
            ]), 200),
            'https://sso-server.test/api/oauth/userinfo' => Http::response([
                'message' => 'User info retrieved successfully.',
                'data' => [
                    'sub' => '123',
                    'name' => 'Jane Example',
                    'email' => 'jane@example.test',
                    'email_verified' => true,
                ],
                'meta' => [],
                'errors' => [],
            ], 200),
        ]);

        $this
            ->withSession($this->pendingAuthorizationSession())
            ->get('/auth/sso/callback?code=valid-code&state=valid-state')
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.id_token.kid_selected',
            'description' => 'Client ID token kid selected from the JWKS.',
        ]);
    }

    #[Group('security')]
    public function test_callback_accepts_a_legacy_but_still_published_jwks_key(): void
    {
        Http::fake([
            'https://sso-server.test/api/oauth/token' => Http::response([
                'message' => 'OAuth token issued successfully.',
                'data' => [
                    'access_token' => 'access-token',
                    'id_token' => $this->idToken(
                        privateKeyPem: $this->legacyOidcPrivateKeyPem,
                        kid: $this->legacyOidcJwk['kid'],
                    ),
                ],
            ], 200),
            'https://sso-server.test/.well-known/jwks.json' => Http::response($this->jwksPayload([
                $this->oidcJwk,
                $this->legacyOidcJwk,
            ]), 200),
            'https://sso-server.test/api/oauth/userinfo' => Http::response([
                'message' => 'User info retrieved successfully.',
                'data' => [
                    'sub' => '123',
                    'name' => 'Jane Example',
                    'email' => 'jane@example.test',
                    'email_verified' => true,
                ],
                'meta' => [],
                'errors' => [],
            ], 200),
        ]);

        $this
            ->withSession($this->pendingAuthorizationSession())
            ->get('/auth/sso/callback?code=valid-code&state=valid-state')
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    #[Group('security')]
    public function test_callback_refetches_jwks_once_when_kid_is_unknown_in_cached_jwks(): void
    {
        Cache::put(
            'sso.oidc.jwks.'.sha1('https://sso-server.test/.well-known/jwks.json'),
            $this->jwksPayload([
                $this->oidcJwk,
            ]),
            300,
        );

        Http::fake([
            'https://sso-server.test/api/oauth/token' => Http::response([
                'message' => 'OAuth token issued successfully.',
                'data' => [
                    'access_token' => 'access-token',
                    'id_token' => $this->idToken(
                        privateKeyPem: $this->legacyOidcPrivateKeyPem,
                        kid: $this->legacyOidcJwk['kid'],
                    ),
                ],
            ], 200),
            'https://sso-server.test/.well-known/jwks.json' => Http::response($this->jwksPayload([
                $this->oidcJwk,
                $this->legacyOidcJwk,
            ]), 200),
            'https://sso-server.test/api/oauth/userinfo' => Http::response([
                'message' => 'User info retrieved successfully.',
                'data' => [
                    'sub' => '123',
                    'name' => 'Jane Example',
                    'email' => 'jane@example.test',
                    'email_verified' => true,
                ],
                'meta' => [],
                'errors' => [],
            ], 200),
        ]);

        $this
            ->withSession($this->pendingAuthorizationSession())
            ->get('/auth/sso/callback?code=valid-code&state=valid-state')
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.id_token.unknown_kid_refresh_triggered',
            'description' => 'Client ID token unknown kid triggered a JWKS refresh.',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.id_token.kid_selected',
            'description' => 'Client ID token kid selected from the JWKS.',
        ]);
    }

    #[Group('security')]
    public function test_callback_rejects_id_token_when_the_matching_kid_is_missing_from_jwks(): void
    {
        Http::fake([
            'https://sso-server.test/api/oauth/token' => Http::response([
                'message' => 'OAuth token issued successfully.',
                'data' => [
                    'access_token' => 'access-token',
                    'id_token' => $this->idToken(
                        privateKeyPem: $this->legacyOidcPrivateKeyPem,
                        kid: $this->legacyOidcJwk['kid'],
                    ),
                ],
            ], 200),
            'https://sso-server.test/.well-known/jwks.json' => Http::response($this->jwksPayload([
                $this->oidcJwk,
            ]), 200),
        ]);

        $this
            ->withSession($this->pendingAuthorizationSession())
            ->get('/auth/sso/callback?code=valid-code&state=valid-state')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Az SSO JWKS nem tartalmazza a szukseges alairasi kulcsot.');

        $this->assertGuest();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.id_token.kid_not_found',
            'description' => 'Client ID token kid was not found in the JWKS.',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.id_token.unknown_kid_refresh_triggered',
            'description' => 'Client ID token unknown kid triggered a JWKS refresh.',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.id_token.unknown_kid_still_missing',
            'description' => 'Client ID token kid was still missing after JWKS refresh.',
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
    public function test_callback_rejects_userinfo_subject_mismatch_after_successful_id_token_verification(): void
    {
        Http::fake([
            'https://sso-server.test/api/oauth/token' => Http::response([
                'message' => 'OAuth token issued successfully.',
                'data' => [
                    'access_token' => 'access-token',
                    'id_token' => $this->idToken(overrides: ['sub' => 'server-user-42']),
                ],
                'meta' => [],
                'errors' => [],
            ], 200),
            'https://sso-server.test/.well-known/jwks.json' => Http::response($this->jwksPayload(), 200),
            'https://sso-server.test/api/oauth/userinfo' => Http::response([
                'message' => 'User info retrieved successfully.',
                'data' => [
                    'sub' => 'other-user-99',
                    'email' => 'mismatch@example.test',
                    'name' => 'Mismatch User',
                ],
                'meta' => [],
                'errors' => [],
            ], 200),
        ]);

        $response = $this
            ->withSession($this->pendingAuthorizationSession())
            ->get('/auth/sso/callback?code=valid-code&state=valid-state');

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Az SSO userinfo subject claimje ervenytelen.');

        $this->assertGuest();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.userinfo.validation_failed',
            'description' => 'OIDC userinfo validation failed.',
        ]);
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
    public function test_logout_clears_only_the_local_session_and_returns_to_login(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withSession($this->oidcSessionContext($this->idToken(overrides: ['sub' => 'server-user-logout']), 'server-user-logout'))
            ->post('/auth/logout');

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('success', 'Sikeres kijelentkezes.');

        $this->assertGuest();
        $this->assertNull(session(config('sso.logout_state_session_key')));

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.session.cleared',
            'description' => 'Client session cleared.',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.logout.local_completed',
            'description' => 'Client local logout completed.',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.logout.completed',
            'description' => 'Client logout completed.',
        ]);
    }

    #[Group('security')]
    public function test_logout_return_completes_cleanly_when_state_matches(): void
    {
        $logoutState = 'provider-logout-state';

        $response = $this
            ->withSession([
                config('sso.logout_state_session_key') => [
                    'state' => $logoutState,
                    'initiated_at' => now()->toIso8601String(),
                ],
            ])
            ->get('/auth/logout/return?state='.$logoutState);

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('success', 'Sikeres kijelentkezes.');

        $this->assertGuest();
        $this->assertNull(session(config('sso.logout_state_session_key')));

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.logout.provider_returned',
            'description' => 'Client provider logout returned.',
        ]);
    }

    #[Group('security')]
    public function test_logout_return_rejects_invalid_state(): void
    {
        $response = $this
            ->withSession([
                config('sso.logout_state_session_key') => [
                    'state' => 'expected-logout-state',
                    'initiated_at' => now()->toIso8601String(),
                ],
            ])
            ->get('/auth/logout/return?state=invalid-logout-state');

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Ervenytelen logout visszateresi allapot.');

        $this->get('/dashboard')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'A munkamenet hianyzik vagy lejart. Jelentkezz be ujra.');
    }

    #[Group('security')]
    public function test_logout_can_resolve_the_provider_end_session_endpoint_from_discovery(): void
    {
        config()->set('sso.logout_endpoint', null);

        Http::fake([
            'https://sso-server.test/.well-known/openid-configuration' => Http::response($this->discoveryPayload(), 200),
        ]);

        $service = app(SsoClientService::class);
        $request = Request::create('/auth/logout', 'POST');
        $request->setLaravelSession(app('session.store'));
        $request->session()->put(config('sso.oidc_session_context_key'), [
            'id_token_hint' => $this->idToken(),
            'id_token_subject' => '123',
            'issuer' => 'https://sso-server.test',
            'sid' => 'sid-current-session',
            'stored_at' => now()->toIso8601String(),
        ]);

        $location = $service->initiateLogout($request);

        $this->assertStringStartsWith('https://sso-server.test/oidc/logout?', $location);
    }

    #[Group('security')]
    public function test_frontchannel_logout_clears_the_local_session_for_a_valid_provider_request(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withSession($this->oidcSessionContext($this->idToken()))
            ->get('/auth/frontchannel-logout?iss=https%3A%2F%2Fsso-server.test&client_id=portal-client');

        $response
            ->assertOk()
            ->assertSeeText('Front-channel logout completed.');

        $this->assertGuest();
        $this->assertNull(session(config('sso.oidc_session_context_key')));

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.logout.frontchannel_received',
            'description' => 'Client front-channel logout received.',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.logout.frontchannel_validated',
            'description' => 'Client front-channel logout validated.',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.logout.frontchannel_local_completed',
            'description' => 'Client front-channel local logout completed.',
        ]);
    }

    #[Group('security')]
    public function test_frontchannel_logout_clears_the_local_session_when_sid_matches(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withSession($this->oidcSessionContext($this->idToken(), sid: 'sid-frontchannel-match'))
            ->get('/auth/frontchannel-logout?iss=https%3A%2F%2Fsso-server.test&client_id=portal-client&sid=sid-frontchannel-match');

        $response
            ->assertOk()
            ->assertSeeText('Front-channel logout completed.');

        $this->assertGuest();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.logout.frontchannel_sid_matched',
            'description' => 'Client front-channel logout sid matched the local session.',
        ]);
    }

    #[Group('security')]
    public function test_frontchannel_logout_keeps_the_local_session_when_sid_mismatches(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withSession($this->oidcSessionContext($this->idToken(), sid: 'sid-frontchannel-current'))
            ->get('/auth/frontchannel-logout?iss=https%3A%2F%2Fsso-server.test&client_id=portal-client&sid=sid-frontchannel-other');

        $response
            ->assertOk()
            ->assertSeeText('Front-channel logout sid mismatch; no local session was cleared.');

        $this->assertAuthenticatedAs($user);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.logout.frontchannel_sid_mismatch',
            'description' => 'Client front-channel logout sid did not match the local session.',
        ]);
    }

    #[Group('security')]
    public function test_frontchannel_logout_rejects_invalid_provider_guard_without_clearing_the_session(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withSession($this->oidcSessionContext($this->idToken()))
            ->get('/auth/frontchannel-logout?iss=https%3A%2F%2Fevil-issuer.test&client_id=portal-client');

        $response
            ->assertStatus(400)
            ->assertSeeText('Ervenytelen front-channel logout kereses.');

        $this->assertAuthenticatedAs($user);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.logout.frontchannel_validation_failed',
            'description' => 'Client front-channel logout validation failed.',
        ]);
    }

    #[Group('security')]
    public function test_backchannel_logout_verifies_a_valid_logout_token_and_clears_local_sessions(): void
    {
        $user = User::factory()->create([
            'sso_user_id' => '123',
        ]);

        Http::fake([
            'https://sso-server.test/.well-known/jwks.json' => Http::response($this->jwksPayload(), 200),
        ]);

        \DB::table('sessions')->insert([
            'id' => 'backchannel-session-1',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => base64_encode('{}'),
            'last_activity' => now()->timestamp,
        ]);

        $logoutToken = $this->logoutToken([
            'jti' => 'backchannel-jti-1',
        ]);

        $response = $this
            ->actingAs($user)
            ->post('/auth/backchannel-logout', [
                'logout_token' => $logoutToken,
            ]);

        $response
            ->assertOk()
            ->assertSeeText('Back-channel logout completed.');

        $this->assertGuest();
        $this->assertDatabaseMissing('sessions', [
            'id' => 'backchannel-session-1',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.logout.backchannel_received',
            'description' => 'Client back-channel logout received.',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.logout.backchannel_verified',
            'description' => 'Client back-channel logout verified.',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.logout.backchannel_local_completed',
            'description' => 'Client back-channel local logout completed.',
        ]);

        $this->assertDatabaseHas('oidc_logout_receipts', [
            'jti_hash' => hash('sha256', 'backchannel-jti-1'),
            'issuer' => 'https://sso-server.test',
            'audience' => 'portal-client',
            'outcome' => 'local_completed',
        ]);
    }

    #[Group('security')]
    public function test_backchannel_logout_replay_is_a_controlled_idempotent_noop(): void
    {
        $user = User::factory()->create([
            'sso_user_id' => '123',
        ]);

        Http::fake([
            'https://sso-server.test/.well-known/jwks.json' => Http::response($this->jwksPayload(), 200),
        ]);

        \DB::table('sessions')->insert([
            'id' => 'backchannel-replay-first-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => base64_encode('{}'),
            'last_activity' => now()->timestamp,
        ]);

        $logoutToken = $this->logoutToken([
            'jti' => 'backchannel-replay-jti',
        ]);

        $this
            ->actingAs($user)
            ->post('/auth/backchannel-logout', [
                'logout_token' => $logoutToken,
            ])
            ->assertOk()
            ->assertSeeText('Back-channel logout completed.');

        \DB::table('sessions')->insert([
            'id' => 'backchannel-replay-second-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => base64_encode('{}'),
            'last_activity' => now()->timestamp,
        ]);

        $this->post('/auth/backchannel-logout', [
            'logout_token' => $logoutToken,
        ])
            ->assertOk()
            ->assertSeeText('Back-channel logout already processed.');

        $this->assertDatabaseHas('sessions', [
            'id' => 'backchannel-replay-second-session',
        ]);

        $this->assertSame(1, OidcLogoutReceipt::query()
            ->where('jti_hash', hash('sha256', 'backchannel-replay-jti'))
            ->count());

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.logout.backchannel_replay_detected',
            'description' => 'Client back-channel logout replay detected.',
        ]);
    }

    #[Group('security')]
    public function test_backchannel_logout_clears_only_sessions_matched_by_sid(): void
    {
        $user = User::factory()->create([
            'sso_user_id' => '123',
        ]);

        Http::fake([
            'https://sso-server.test/.well-known/jwks.json' => Http::response($this->jwksPayload(), 200),
        ]);

        \DB::table('sessions')->insert([
            'id' => 'backchannel-sid-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => base64_encode('{}'),
            'last_activity' => now()->timestamp,
        ]);

        OidcSessionMapping::query()->create([
            'sid_hash' => hash('sha256', 'sid-backchannel-match'),
            'session_id' => 'backchannel-sid-session',
            'user_id' => $user->id,
            'issuer' => 'https://sso-server.test',
            'client_id' => 'portal-client',
            'bound_at' => now(),
            'last_seen_at' => now(),
        ]);

        $response = $this->post('/auth/backchannel-logout', [
            'logout_token' => $this->logoutToken([
                'sid' => 'sid-backchannel-match',
                'jti' => 'backchannel-sid-match-jti',
            ]),
        ]);

        $response
            ->assertOk()
            ->assertSeeText('Back-channel logout completed.');

        $this->assertDatabaseMissing('sessions', [
            'id' => 'backchannel-sid-session',
        ]);
        $this->assertDatabaseHas('oidc_session_mappings', [
            'sid_hash' => hash('sha256', 'sid-backchannel-match'),
        ]);

        $this->assertNotNull(OidcSessionMapping::query()
            ->where('sid_hash', hash('sha256', 'sid-backchannel-match'))
            ->value('invalidated_at'));

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.logout.backchannel_sid_matched',
            'description' => 'Client back-channel logout sid matched local session correlation.',
        ]);

        $this->assertDatabaseHas('oidc_logout_receipts', [
            'jti_hash' => hash('sha256', 'backchannel-sid-match-jti'),
            'sid_hash' => hash('sha256', 'sid-backchannel-match'),
            'outcome' => 'local_completed',
        ]);
    }

    #[Group('security')]
    public function test_stale_and_orphan_oidc_session_mappings_can_be_purged(): void
    {
        $activeUser = User::factory()->create();
        $orphanUser = User::factory()->create();

        \DB::table('sessions')->insert([
            'id' => 'active-session-mapping',
            'user_id' => $activeUser->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => base64_encode('{}'),
            'last_activity' => now()->timestamp,
        ]);

        OidcSessionMapping::query()->create([
            'sid_hash' => hash('sha256', 'sid-active-mapping'),
            'session_id' => 'active-session-mapping',
            'user_id' => $activeUser->id,
            'issuer' => 'https://sso-server.test',
            'client_id' => 'portal-client',
            'bound_at' => now(),
            'last_seen_at' => now(),
        ]);

        OidcSessionMapping::query()->create([
            'sid_hash' => hash('sha256', 'sid-orphan-mapping'),
            'session_id' => 'orphan-session-mapping',
            'user_id' => $orphanUser->id,
            'issuer' => 'https://sso-server.test',
            'client_id' => 'portal-client',
            'bound_at' => now()->subDays(2),
            'last_seen_at' => now()->subDays(2),
        ]);

        OidcSessionMapping::query()->create([
            'sid_hash' => hash('sha256', 'sid-stale-mapping'),
            'session_id' => 'stale-session-mapping',
            'user_id' => $orphanUser->id,
            'issuer' => 'https://sso-server.test',
            'client_id' => 'portal-client',
            'bound_at' => now()->subDays(3),
            'last_seen_at' => now()->subDays(3),
            'invalidated_at' => now()->subDays(2),
        ]);

        $service = app(OidcSessionMappingCleanupService::class);

        $this->assertSame(1, $service->purgeOrphanMappings());
        $this->assertSame(1, $service->purgeStaleMappings(retentionSeconds: 3600));

        $this->assertDatabaseHas('oidc_session_mappings', [
            'sid_hash' => hash('sha256', 'sid-active-mapping'),
            'invalidated_at' => null,
        ]);
        $this->assertDatabaseMissing('oidc_session_mappings', [
            'sid_hash' => hash('sha256', 'sid-orphan-mapping'),
        ]);
        $this->assertDatabaseMissing('oidc_session_mappings', [
            'sid_hash' => hash('sha256', 'sid-stale-mapping'),
        ]);
    }

    #[Group('security')]
    public function test_expired_backchannel_logout_receipts_can_be_purged(): void
    {
        OidcLogoutReceipt::query()->create([
            'jti_hash' => hash('sha256', 'expired-receipt-jti'),
            'issuer' => 'https://sso-server.test',
            'audience' => 'portal-client',
            'sid_hash' => hash('sha256', 'sid-expired-receipt'),
            'outcome' => 'local_completed',
            'processed_at' => now()->subHours(2),
            'expires_at' => now()->subHour(),
        ]);

        OidcLogoutReceipt::query()->create([
            'jti_hash' => hash('sha256', 'active-receipt-jti'),
            'issuer' => 'https://sso-server.test',
            'audience' => 'portal-client',
            'sid_hash' => hash('sha256', 'sid-active-receipt'),
            'outcome' => 'local_completed',
            'processed_at' => now(),
            'expires_at' => now()->addHour(),
        ]);

        $deleted = app(\App\Services\Sso\OidcBackChannelLogoutReceiptService::class)->purgeExpiredReceipts();

        $this->assertSame(1, $deleted);
        $this->assertDatabaseMissing('oidc_logout_receipts', [
            'jti_hash' => hash('sha256', 'expired-receipt-jti'),
        ]);
        $this->assertDatabaseHas('oidc_logout_receipts', [
            'jti_hash' => hash('sha256', 'active-receipt-jti'),
        ]);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.logout.receipt_cleanup_completed',
        ]);
    }

    #[Group('security')]
    public function test_backchannel_logout_sid_mismatch_is_a_controlled_noop(): void
    {
        $user = User::factory()->create([
            'sso_user_id' => '123',
        ]);

        Http::fake([
            'https://sso-server.test/.well-known/jwks.json' => Http::response($this->jwksPayload(), 200),
        ]);

        \DB::table('sessions')->insert([
            'id' => 'backchannel-unmatched-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => base64_encode('{}'),
            'last_activity' => now()->timestamp,
        ]);

        $response = $this->post('/auth/backchannel-logout', [
            'logout_token' => $this->logoutToken([
                'sid' => 'sid-backchannel-unmatched',
                'jti' => 'backchannel-sid-mismatch-jti',
            ]),
        ]);

        $response
            ->assertOk()
            ->assertSeeText('Back-channel logout sid mismatch; no local session was cleared.');

        $this->assertDatabaseHas('sessions', [
            'id' => 'backchannel-unmatched-session',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.logout.backchannel_sid_mismatch',
            'description' => 'Client back-channel logout sid did not match a local session.',
        ]);

        $this->assertDatabaseHas('oidc_logout_receipts', [
            'jti_hash' => hash('sha256', 'backchannel-sid-mismatch-jti'),
            'sid_hash' => hash('sha256', 'sid-backchannel-unmatched'),
            'outcome' => 'sid_mismatch',
        ]);
    }

    #[Group('security')]
    public function test_backchannel_logout_rejects_an_expired_logout_token_without_receipt_or_cleanup(): void
    {
        $user = User::factory()->create([
            'sso_user_id' => '123',
        ]);

        Http::fake([
            'https://sso-server.test/.well-known/jwks.json' => Http::response($this->jwksPayload(), 200),
        ]);

        \DB::table('sessions')->insert([
            'id' => 'backchannel-expired-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => base64_encode('{}'),
            'last_activity' => now()->timestamp,
        ]);

        $response = $this->actingAs($user)->post('/auth/backchannel-logout', [
            'logout_token' => $this->logoutToken([
                'jti' => 'backchannel-expired-jti',
                'exp' => now()->subMinutes(10)->timestamp,
            ]),
        ]);

        $response
            ->assertStatus(401)
            ->assertSeeText('A back-channel logout token lejart.');

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('sessions', [
            'id' => 'backchannel-expired-session',
        ]);
        $this->assertDatabaseMissing('oidc_logout_receipts', [
            'jti_hash' => hash('sha256', 'backchannel-expired-jti'),
        ]);
    }

    #[Group('security')]
    public function test_backchannel_logout_rejects_an_invalid_signature(): void
    {
        Http::fake([
            'https://sso-server.test/.well-known/jwks.json' => Http::response($this->jwksPayload(), 200),
        ]);

        $response = $this->post('/auth/backchannel-logout', [
            'logout_token' => $this->logoutToken(privateKeyPem: $this->legacyOidcPrivateKeyPem, kid: $this->oidcJwk['kid']),
        ]);

        $response
            ->assertStatus(401)
            ->assertSeeText('Ervenytelen back-channel logout token alairas.');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.logout.backchannel_verification_failed',
            'description' => 'Client back-channel logout verification failed.',
        ]);
    }

    #[Group('security')]
    public function test_backchannel_logout_rejects_an_issuer_mismatch(): void
    {
        Http::fake([
            'https://sso-server.test/.well-known/jwks.json' => Http::response($this->jwksPayload(), 200),
        ]);

        $response = $this->post('/auth/backchannel-logout', [
            'logout_token' => $this->logoutToken([
                'iss' => 'https://evil-issuer.test',
            ]),
        ]);

        $response
            ->assertStatus(401)
            ->assertSeeText('A back-channel logout issuer claimje ervenytelen.');
    }

    #[Group('security')]
    public function test_backchannel_logout_rejects_an_audience_mismatch(): void
    {
        Http::fake([
            'https://sso-server.test/.well-known/jwks.json' => Http::response($this->jwksPayload(), 200),
        ]);

        $response = $this->post('/auth/backchannel-logout', [
            'logout_token' => $this->logoutToken([
                'aud' => 'different-client',
            ]),
        ]);

        $response
            ->assertStatus(401)
            ->assertSeeText('A back-channel logout audience claimje ervenytelen.');
    }

    #[Group('security')]
    public function test_backchannel_logout_rejects_a_missing_logout_event_claim(): void
    {
        Http::fake([
            'https://sso-server.test/.well-known/jwks.json' => Http::response($this->jwksPayload(), 200),
        ]);

        $response = $this->post('/auth/backchannel-logout', [
            'logout_token' => $this->logoutToken([
                'events' => [],
            ]),
        ]);

        $response
            ->assertStatus(401)
            ->assertSeeText('A back-channel logout token esemeny claimje ervenytelen.');
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
