<?php

namespace App\Services\Sso;

use App\Data\SsoStatusData;
use App\Exceptions\SsoAuthenticationException;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * @phpstan-type SsoProfileApi array{
 *     enabled: bool,
 *     baseUrl: string|null,
 *     endpoints: array{
 *         show: string|null,
 *         update: string|null,
 *         updatePassword: string|null
 *     },
 *     editableFields: array<int, string>,
 *     readOnlyFields: array<int, string>
 * }
 * @phpstan-type SsoUserInfo array<string, mixed>
 * @phpstan-type SsoDiagnostics array{
 *     sso_phase: string,
 *     sso_endpoint: string|null,
 *     http_status: int,
 *     is_json_response: bool,
 *     has_access_token: bool,
 *     oauth_error: string|null,
 *     response_message: string|null
 * }
 * @phpstan-type PendingAuthorizationContext array{
 *     state: string,
 *     code_verifier: string,
 *     nonce: string|null,
 *     issued_at: string,
 *     scope_contains_openid: bool
 * }
 * @phpstan-type IdentityValidationContext array{
 *     state: string,
 *     expected_nonce: string|null,
 *     scope_contains_openid: bool,
 *     retained_at: string,
 *     validation_status: string,
 *     validated_at?: string|null
 * }
 * @phpstan-type OidcSessionContext array{
 *     id_token_hint: string,
 *     id_token_subject: string|null,
 *     issuer: string|null,
 *     stored_at: string
 * }
 * @phpstan-type LogoutStateContext array{
 *     state: string,
 *     initiated_at: string
 * }
 * @phpstan-type TokenExchangeResult array{
 *     access_token: string,
 *     id_token: string|null
 * }
 */
class SsoClientService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly OidcIdTokenVerifier $oidcIdTokenVerifier,
        private readonly OidcDiscoveryService $oidcDiscoveryService,
        private readonly OidcUserInfoService $oidcUserInfoService,
    ) {
    }

    /**
     * Az SSO kliens aktuális konfigurációs és működési állapotának összegzése.
     */
    public function status(): SsoStatusData
    {
        $serverBaseUrl = $this->serverBaseUrl();
        $authorizeEndpoint = $this->configuredEndpoint('authorize_endpoint');
        $tokenEndpoint = $this->configuredEndpoint('token_endpoint');
        $userinfoEndpoint = $this->configuredEndpoint('userinfo_endpoint');
        $redirectUri = $this->redirectUri();
        $scopes = config('sso.scopes', []);
        $requiredScopesConfigured = collect($scopes)
            ->map(static fn (mixed $scope): string => trim((string) $scope))
            ->filter()
            ->contains('email');
        $localAuthEnabled = (bool) config('sso.local_auth_enabled');
        $configured = filled($serverBaseUrl)
            && filled(config('sso.client_id'))
            && filled($authorizeEndpoint)
            && filled($tokenEndpoint)
            && filled($userinfoEndpoint)
            && filled($redirectUri)
            && $requiredScopesConfigured;

        return new SsoStatusData(
            configured: $configured,
            localAuthEnabled: $localAuthEnabled,
            serverBaseUrl: $serverBaseUrl,
            authorizeEndpoint: $authorizeEndpoint,
            tokenEndpoint: $tokenEndpoint,
            userinfoEndpoint: $userinfoEndpoint,
            redirectUri: $redirectUri,
            scopes: $scopes,
            mode: $configured ? 'authorization-code-session' : 'missing-configuration',
            message: $configured
                ? 'Az SSO kliens redirect, callback es lokalis session flow-val mukodik.'
                : 'Az SSO kapcsolat meg mindig hianyos konfiguracioval fut.',
        );
    }

    /**
     * Az authorize végpont teljes URL-jének lekérése konfiguráció alapján.
     */
    public function authorizationRedirectUrl(): ?string
    {
        return $this->configuredEndpoint('authorize_endpoint');
    }

    /**
     * A self-service profil API kliensoldali metaadatai.
     *
     * @return SsoProfileApi
     */
    public function selfServiceProfileApi(): array
    {
        $serverBaseUrl = $this->serverBaseUrl();

        return [
            'enabled' => $serverBaseUrl !== null,
            'baseUrl' => $serverBaseUrl,
            'endpoints' => [
                'show' => $serverBaseUrl ? $serverBaseUrl.'/api/profile' : null,
                'update' => $serverBaseUrl ? $serverBaseUrl.'/api/profile' : null,
                'updatePassword' => $serverBaseUrl ? $serverBaseUrl.'/api/profile/password' : null,
            ],
            'editableFields' => ['name'],
            'readOnlyFields' => ['email'],
        ];
    }

    /**
     * Az SSO bejelentkezés indításához szükséges authorize URL felépítése state és PKCE értékekkel.
     */
    public function buildAuthorizationUrl(Request $request): string
    {
        try {
            $this->ensureConfigured();
        } catch (SsoAuthenticationException $exception) {
            $this->auditLogService->logFailure(
                logName: AuditLogService::LOG_CLIENT_AUTH,
                event: 'client_auth.login_redirect.failed',
                description: 'Client login redirect failed.',
                properties: [
                    'reason' => 'missing_configuration',
                    'redirect_target' => $this->configuredEndpoint('authorize_endpoint'),
                    ...$this->auditLogService->requestContext($request),
                ],
            );

            throw $exception;
        }

        $configuredScopes = $this->normalizedScopes();
        $requiresNonce = $this->scopeContainsOpenId($configuredScopes);
        $state = Str::random(64);
        $codeVerifier = Str::random(96);
        $nonce = $requiresNonce ? Str::random(96) : null;
        $codeChallenge = $this->codeChallengeFromVerifier($codeVerifier);

        $this->storePendingAuthorizationContext($request, [
            'state' => $state,
            'code_verifier' => $codeVerifier,
            'nonce' => $nonce,
            'issued_at' => now()->toIso8601String(),
            'scope_contains_openid' => $requiresNonce,
        ]);

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => (string) config('sso.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'scope' => implode(' ', $configuredScopes),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ], arg_separator: '&', encoding_type: PHP_QUERY_RFC3986);

        $redirectUrl = $this->configuredEndpoint('authorize_endpoint').'?'.$query;

        if ($nonce !== null) {
            $this->auditLogService->logSuccess(
                logName: AuditLogService::LOG_CLIENT_AUTH,
                event: 'client_auth.nonce.issued',
                description: 'Client OIDC nonce issued.',
                causer: $request->user(),
                properties: [
                    'has_nonce' => true,
                    'scope_contains_openid' => true,
                    'redirect_target' => $this->configuredEndpoint('authorize_endpoint'),
                    ...$this->auditLogService->requestContext($request),
                ],
            );
        }

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_CLIENT_AUTH,
            event: 'client_auth.login_redirect.started',
            description: 'Client login redirect started.',
            causer: $request->user(),
            properties: [
                'redirect_target' => $this->configuredEndpoint('authorize_endpoint'),
                ...$this->auditLogService->requestContext($request),
            ],
        );

        return $redirectUrl;
    }

    /**
     * A callback kérés feldolgozása és a helyi sessionnel rendelkező felhasználó hitelesítése.
     */
    public function authenticateFromCallback(Request $request): User
    {
        try {
            $this->ensureConfigured();
        } catch (SsoAuthenticationException $exception) {
            $this->auditLogService->logFailure(
                logName: AuditLogService::LOG_CLIENT_AUTH,
                event: 'client_auth.callback.failed',
                description: 'Client authentication callback failed.',
                properties: [
                    'callback_result' => 'failure',
                    'reason' => 'missing_configuration',
                    'http_status' => $exception->status(),
                    ...$this->auditLogService->requestContext($request),
                ],
            );

            throw $exception;
        }

        if ($request->filled('error')) {
            $this->handleAuthorizeCallbackError($request);
        }

        $code = $request->string('code')->toString();
        $state = $request->string('state')->toString();

        if ($code === '') {
            $this->throwCallbackFailure(
                request: $request,
                message: 'Hianyzik az authorization code a callbackbol.',
                status: 422,
                reason: 'missing_authorization_code',
            );
        }

        if ($state === '') {
            $this->throwCallbackFailure(
                request: $request,
                message: 'Hianyzik a state ertek a callbackbol.',
                status: 422,
                reason: 'missing_state',
            );
        }

        $pendingAuthorization = $this->getPendingAuthorizationContextByState($request->session(), $state);
        $expectedState = is_array($pendingAuthorization) ? (string) ($pendingAuthorization['state'] ?? '') : '';
        $codeVerifier = is_array($pendingAuthorization) ? (string) ($pendingAuthorization['code_verifier'] ?? '') : '';
        $expectedNonce = is_array($pendingAuthorization) ? trim((string) ($pendingAuthorization['nonce'] ?? '')) : '';
        $scopeContainsOpenId = (bool) ($pendingAuthorization['scope_contains_openid'] ?? false);

        if ($expectedState === '' || ! hash_equals($expectedState, $state)) {
            $this->throwCallbackFailure(
                request: $request,
                message: 'Ervenytelen vagy lejart SSO allapot. Probald ujra a bejelentkezest.',
                status: 401,
                reason: 'invalid_state',
            );
        }

        if ($codeVerifier === '') {
            $this->throwCallbackFailure(
                request: $request,
                message: 'Hianyzo PKCE verifier miatt nem folytathato a bejelentkezes. Inditsd ujra a login folyamatot.',
                status: 401,
                reason: 'missing_pkce_verifier',
            );
        }

        // Future OIDC ID token validation will compare the returned nonce against this session-bound value.
        if ($scopeContainsOpenId && $expectedNonce === '') {
            $this->throwCallbackFailure(
                request: $request,
                message: 'Hianyzo OIDC nonce allapot miatt nem folytathato a bejelentkezes. Inditsd ujra a login folyamatot.',
                status: 401,
                reason: 'missing_nonce_context',
            );
        }

        $tokenResponse = $this->exchangeCodeForTokens($code, $codeVerifier);

        $verifiedIdTokenClaims = null;
        $receivedIdToken = null;

        if ($scopeContainsOpenId) {
            $idToken = trim((string) ($tokenResponse['id_token'] ?? ''));

            if ($idToken === '') {
                $this->throwCallbackFailure(
                    request: $request,
                    message: 'Az SSO token valasz nem tartalmaz ervenyes ID tokent az openid flow-hoz.',
                    status: 502,
                    reason: 'missing_id_token',
                );
            }

            $receivedIdToken = $idToken;
            $verifiedIdTokenClaims = $this->verifyIdToken($request, $idToken);
            $returnedNonce = trim((string) ($verifiedIdTokenClaims['nonce'] ?? ''));

            $this->validateExpectedNonce(
                request: $request,
                expectedNonce: $expectedNonce !== '' ? $expectedNonce : null,
                returnedNonce: $returnedNonce,
                required: true,
                allowDeferredWhenReturnedNonceMissing: false,
            );
        }

        $accessToken = $tokenResponse['access_token'];
        $userInfo = $this->fetchUserInfo($request, $accessToken);

        if ($scopeContainsOpenId && is_array($verifiedIdTokenClaims)) {
            $this->validateUserInfoSubject(
                request: $request,
                expectedSubject: trim((string) ($verifiedIdTokenClaims['sub'] ?? '')),
                userInfo: $userInfo,
            );
        }

        $user = $this->resolveLocalUser($userInfo);

        if (is_array($pendingAuthorization) && $scopeContainsOpenId) {
            $this->retainIdentityValidationContext($request, $pendingAuthorization, 'validated_from_id_token');
        }

        $this->forgetPendingAuthorizationContext($request, $state);

        Auth::login($user, remember: false);
        $request->session()->regenerate();

        if ($scopeContainsOpenId && $receivedIdToken !== null && is_array($verifiedIdTokenClaims)) {
            $this->storeOidcSessionContext($request, [
                'id_token_hint' => $receivedIdToken,
                'id_token_subject' => $this->normalizedOptionalString($verifiedIdTokenClaims['sub'] ?? null),
                'issuer' => $this->normalizedOptionalString($verifiedIdTokenClaims['iss'] ?? null),
                'stored_at' => now()->toIso8601String(),
            ]);
        } else {
            $request->session()->forget(config('sso.oidc_session_context_key'));
        }

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_CLIENT_AUTH,
            event: 'client_auth.callback.succeeded',
            description: 'Client authentication callback succeeded.',
            subject: $user,
            causer: $user,
            properties: [
                'callback_result' => 'success',
                ...$this->auditLogService->requestContext($request),
            ],
        );

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_CLIENT_AUTH,
            event: 'client_auth.session.established',
            description: 'Client session established.',
            subject: $user,
            causer: $user,
            properties: [
                'status' => 'authenticated',
                ...$this->auditLogService->requestContext($request),
            ],
        );

        return $user;
    }

    /**
     * A lokális session teljes kijelentkeztetése és az ideiglenes SSO állapot törlése.
     */
    public function initiateLogout(Request $request): string
    {
        /** @var User|null $user */
        $user = $request->user();
        $logoutState = Str::random(64);
        $providerLogoutUrl = $this->buildProviderLogoutUrl($request, $logoutState);

        if ($user instanceof User) {
            $this->auditLogService->logSuccess(
                logName: AuditLogService::LOG_CLIENT_AUTH,
                event: 'client_auth.logout.provider_initiated',
                description: 'Client provider logout initiated.',
                subject: $user,
                causer: $user,
                properties: [
                    'redirect_target' => $providerLogoutUrl,
                    'status' => 'provider_logout_started',
                    ...$this->auditLogService->requestContext($request),
                ],
            );
        }

        $this->clearLocalSession($request, $user, [
            'state' => $logoutState,
            'initiated_at' => now()->toIso8601String(),
        ]);

        return $providerLogoutUrl;
    }

    public function finalizeLogoutReturn(Request $request): void
    {
        $logoutContext = $this->getLogoutStateContext($request->session());
        $expectedState = is_array($logoutContext) ? trim((string) ($logoutContext['state'] ?? '')) : '';
        $returnedState = $request->string('state')->toString();

        if ($expectedState === '' || $returnedState === '' || ! hash_equals($expectedState, $returnedState)) {
            $request->session()->forget(config('sso.logout_state_session_key'));

            $this->auditLogService->logFailure(
                logName: AuditLogService::LOG_CLIENT_AUTH,
                event: 'client_auth.logout.provider_returned',
                description: 'Client provider logout returned.',
                properties: [
                    'reason' => 'invalid_logout_state',
                    'status' => 'invalid',
                    ...$this->auditLogService->requestContext($request),
                ],
            );

            throw new SsoAuthenticationException('Ervenytelen logout visszateresi allapot.', 401);
        }

        $request->session()->forget(config('sso.logout_state_session_key'));
        $request->session()->regenerateToken();

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_CLIENT_AUTH,
            event: 'client_auth.logout.provider_returned',
            description: 'Client provider logout returned.',
            properties: [
                'status' => 'provider_logout_returned',
                ...$this->auditLogService->requestContext($request),
            ],
        );
    }

    /**
     * Authorization code cseréje access tokenre az SSO szervernél.
     */
    /**
     * @return TokenExchangeResult
     */
    private function exchangeCodeForTokens(string $code, string $codeVerifier): array
    {
        $endpoint = $this->configuredEndpoint('token_endpoint');

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout((int) config('sso.timeout', 10))
                ->post($endpoint, array_filter([
                    'grant_type' => 'authorization_code',
                    'client_id' => (string) config('sso.client_id'),
                    'client_secret' => config('sso.client_secret'),
                    'redirect_uri' => $this->redirectUri(),
                    'code' => $code,
                    'code_verifier' => $codeVerifier,
                ], fn (mixed $value) => filled($value)));
        } catch (ConnectionException $exception) {
            $this->throwCallbackFailure(
                request: request(),
                message: 'Az SSO token vegpont nem erheto el.',
                status: 502,
                reason: 'token_endpoint_unreachable',
                previous: $exception,
                context: [
                    'sso_phase' => 'token_exchange',
                    'sso_endpoint' => $endpoint,
                    'http_status' => null,
                    'is_json_response' => false,
                ],
            );
        }

        $payload = $this->decodeJsonResponse($response);
        $accessToken = trim((string) data_get($payload, 'data.access_token'));
        $idToken = trim((string) data_get($payload, 'data.id_token'));
        $responseMessage = trim((string) data_get($payload, 'message'));
        $diagnostics = $this->buildResponseDiagnostics(
            phase: 'token_exchange',
            endpoint: $endpoint,
            response: $response,
            payload: $payload,
            hasAccessToken: $accessToken !== '',
            responseMessage: $responseMessage,
            oauthError: null,
        );

        if ($payload === null) {
            $this->throwCallbackFailure(
                request: request(),
                message: 'Az SSO token vegpont ervenytelen, nem JSON valaszt adott.',
                status: 502,
                reason: 'token_response_invalid_json',
                context: $diagnostics,
            );
        }

        if (! $response->successful()) {
            $this->throwCallbackFailure(
                request: request(),
                message: 'Az SSO token vegpont hibaval valaszolt.',
                status: 502,
                reason: 'token_endpoint_failed',
                context: $diagnostics,
            );
        }

        if ($accessToken === '') {
            $this->throwCallbackFailure(
                request: request(),
                message: 'Az SSO token valasz nem tartalmaz ervenyes access tokent.',
                status: 502,
                reason: 'missing_access_token',
                context: $diagnostics,
            );
        }

        return [
            'access_token' => $accessToken,
            'id_token' => $idToken !== '' ? $idToken : null,
        ];
    }

    /**
     * Userinfo adatok lekérése az SSO szervertől access tokennel.
     *
     * @return SsoUserInfo
     */
    private function fetchUserInfo(Request $request, string $accessToken): array
    {
        try {
            return $this->oidcUserInfoService->fetch($accessToken);
        } catch (SsoAuthenticationException $exception) {
            $this->throwCallbackFailure(
                request: $request,
                message: $exception->getMessage(),
                status: $exception->getCode() > 0 ? (int) $exception->getCode() : 502,
                reason: $this->mapUserInfoFailureReason($exception->getMessage()),
                previous: $exception,
            );
        }
    }

    /**
     * @param SsoUserInfo $userInfo
     */
    private function validateUserInfoSubject(Request $request, string $expectedSubject, array $userInfo): void
    {
        try {
            $this->oidcUserInfoService->assertSubjectMatches($expectedSubject, $userInfo);
        } catch (SsoAuthenticationException $exception) {
            $this->throwCallbackFailure(
                request: $request,
                message: $exception->getMessage(),
                status: $exception->getCode() > 0 ? (int) $exception->getCode() : 401,
                reason: 'userinfo_subject_mismatch',
                previous: $exception,
            );
        }
    }

    private function mapUserInfoFailureReason(string $message): string
    {
        return match ($message) {
            'Az SSO userinfo vegpont nem erheto el.' => 'userinfo_endpoint_unreachable',
            'Az SSO userinfo vegpont ervenytelen, nem JSON valaszt adott.' => 'userinfo_response_invalid_json',
            'Az SSO userinfo vegpont hibaval valaszolt.' => 'userinfo_endpoint_failed',
            'Ervenytelen userinfo valasz erkezett az SSO szervertol.' => 'userinfo_payload_invalid',
            default => 'userinfo_validation_failed',
        };
    }

    /**
     * A userinfo válasz alapján helyi felhasználó feloldása vagy létrehozása.
     *
     * @param  SsoUserInfo  $userInfo
     */
    private function resolveLocalUser(array $userInfo): User
    {
        $ssoUserId = $this->resolveSsoUserId($userInfo);
        $email = trim((string) data_get($userInfo, 'email'));
        $name = $this->resolveDisplayName($userInfo, $email !== '' ? $email : $ssoUserId);

        if ($ssoUserId === '') {
            $this->throwCallbackFailure(
                request: request(),
                message: 'Az SSO userinfo valasz nem tartalmaz felhasznalhato user azonositot.',
                status: 422,
                reason: 'missing_subject_identifier',
            );
        }

        $user = User::query()->where('sso_user_id', $ssoUserId)->first();

        if ($user) {
            return $this->syncResolvedUser($user, $ssoUserId, $email, $name);
        }

        if ($email !== '') {
            $legacyUser = User::query()
                ->whereNull('sso_user_id')
                ->where('email', $email)
                ->first();

            if ($legacyUser) {
                return $this->syncResolvedUser($legacyUser, $ssoUserId, $email, $name);
            }
        }

        return User::query()->create([
            'sso_user_id' => $ssoUserId,
            'name' => $name,
            'email' => $email,
            'password' => Str::password(32),
            'email_verified_at' => now(),
            'last_authenticated_at' => now(),
        ]);
    }

    /**
     * Az SSO válaszból használható felhasználói azonosító feloldása.
     *
     * @param  SsoUserInfo  $userInfo
     */
    private function resolveSsoUserId(array $userInfo): string
    {
        return trim((string) (data_get($userInfo, 'sub') ?: data_get($userInfo, 'id')));
    }

    /**
     * A feloldott helyi felhasználó adatainak szinkronizálása az SSO adatokkal.
     */
    private function syncResolvedUser(User $user, string $ssoUserId, string $email, string $name): User
    {
        $attributes = [
            'sso_user_id' => $ssoUserId,
            'name' => $name,
            'last_authenticated_at' => now(),
        ];

        if ($email !== '') {
            $attributes['email'] = $email;

            if ($user->email_verified_at === null) {
                $attributes['email_verified_at'] = now();
            }
        }

        $user->forceFill($attributes)->save();

        return $user->refresh();
    }

    /**
     * Megjelenítendő név feloldása az SSO userinfo válaszból.
     *
     * @param  SsoUserInfo  $userInfo
     */
    private function resolveDisplayName(array $userInfo, string $fallbackEmail): string
    {
        return trim((string) (data_get($userInfo, 'name')
            ?: data_get($userInfo, 'preferred_username')
            ?: data_get($userInfo, 'nickname')
            ?: Str::before($fallbackEmail, '@')))
            ?: 'SSO User';
    }

    /**
     * A kötelező SSO konfigurációs elemek ellenőrzése.
     */
    private function ensureConfigured(): void
    {
        $configuredScopes = collect($this->normalizedScopes());

        foreach (['email'] as $requiredScope) {
            if (! $configuredScopes->contains($requiredScope)) {
                throw new SsoAuthenticationException(
                    sprintf('Az SSO kliens konfiguracioja hianyos: a "%s" scope kotelezo ehhez a kliens flow-hoz.', $requiredScope),
                    500,
                );
            }
        }

        if (! $this->status()->configured) {
            throw new SsoAuthenticationException('Az SSO kliens konfiguracioja hianyos.', 500);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function throwCallbackFailure(
        ?Request $request,
        string $message,
        int $status,
        string $reason,
        ?\Throwable $previous = null,
        array $context = [],
    ): never {
        $properties = [
            'callback_result' => 'failure',
            'reason' => $reason,
            'http_status' => $status,
        ];

        if ($request instanceof Request) {
            $properties = [
                ...$properties,
                ...$this->auditLogService->requestContext($request),
            ];
        }

        if (isset($context['sso_endpoint']) && is_string($context['sso_endpoint'])) {
            $properties['api_endpoint'] = $context['sso_endpoint'];
        }

        if (isset($context['provider_error']) && is_string($context['provider_error'])) {
            $properties['provider_error'] = $context['provider_error'];
        }

        if (isset($context['provider_error_description']) && is_string($context['provider_error_description'])) {
            $properties['provider_error_description'] = $context['provider_error_description'];
        }

        $this->auditLogService->logFailure(
            logName: AuditLogService::LOG_CLIENT_AUTH,
            event: 'client_auth.callback.failed',
            description: 'Client authentication callback failed.',
            causer: $request?->user(),
            properties: $properties,
        );

        throw new SsoAuthenticationException($message, $status, $previous, $context);
    }

    private function handleAuthorizeCallbackError(Request $request): never
    {
        $providerError = trim($request->string('error')->toString());
        $providerDescription = trim($request->string('error_description')->toString());

        $message = $this->authorizeCallbackErrorMessage($providerError, $providerDescription);

        $this->throwCallbackFailure(
            request: $request,
            message: $message,
            status: 401,
            reason: 'authorize_callback_error',
            context: [
                'provider_error' => $providerError,
                'provider_error_description' => $providerDescription !== '' ? $providerDescription : null,
            ],
        );
    }

    private function authorizeCallbackErrorMessage(string $providerError, string $providerDescription): string
    {
        return match ($providerError) {
            'access_denied' => 'A bejelentkezes nem folytathato, mert ehhez az alkalmazashoz nincs hozzaferese.',
            'invalid_request' => 'A bejelentkezesi kerest a szolgaltato elutasitotta. Inditsa ujra a folyamatot.',
            default => $providerDescription !== ''
                ? 'A kozponti bejelentkezes nem sikerult. '.$providerDescription
                : 'A kozponti bejelentkezes nem sikerult. Probald ujra kesobb.',
        };
    }

    /**
     * Az SSO szerver bázis URL-jének normalizált feloldása.
     */
    private function serverBaseUrl(): ?string
    {
        $baseUrl = trim((string) config('sso.server_base_url'));

        return $baseUrl === '' ? null : rtrim($baseUrl, '/');
    }

    /**
     * A callback redirect URI feloldása konfigurációból vagy route-ból.
     */
    private function redirectUri(): ?string
    {
        $configured = trim((string) config('sso.redirect_uri'));

        if ($configured !== '') {
            return $configured;
        }

        return route('auth.sso.callback', absolute: true);
    }

    private function logoutReturnUri(): string
    {
        $configured = trim((string) config('sso.logout_return_uri'));

        if ($configured !== '') {
            return $configured;
        }

        return route('auth.logout.return', absolute: true);
    }

    /**
     * Egy konfigurált SSO végpont teljes URL-jének feloldása.
     */
    private function configuredEndpoint(string $key): ?string
    {
        $endpoint = trim((string) config("sso.{$key}"));

        if ($endpoint !== '') {
            if (Str::startsWith($endpoint, ['http://', 'https://'])) {
                return $endpoint;
            }

            $baseUrl = $this->serverBaseUrl();

            return $baseUrl === null ? null : $baseUrl.'/'.ltrim($endpoint, '/');
        }

        $discoveryKey = match ($key) {
            'authorize_endpoint' => 'authorization_endpoint',
            'token_endpoint' => 'token_endpoint',
            'userinfo_endpoint' => 'userinfo_endpoint',
            'logout_endpoint' => 'end_session_endpoint',
            default => null,
        };

        if ($discoveryKey !== null) {
            $discoveredEndpoint = $this->oidcDiscoveryService->resolveDiscoveryValue($discoveryKey);

            if ($discoveredEndpoint !== null) {
                return $discoveredEndpoint;
            }
        }

        $fallbackPath = match ($key) {
            'authorize_endpoint' => '/oauth/authorize',
            'token_endpoint' => '/api/oauth/token',
            'userinfo_endpoint' => '/api/oauth/userinfo',
            'logout_endpoint' => '/oidc/logout',
            default => null,
        };

        if ($fallbackPath === null) {
            return null;
        }

        $baseUrl = $this->serverBaseUrl();

        return $baseUrl === null ? null : $baseUrl.$fallbackPath;
    }

    /**
     * PKCE code verifier alapján S256 challenge előállítása.
     */
    private function codeChallengeFromVerifier(string $codeVerifier): string
    {
        $hash = hash('sha256', $codeVerifier, true);
        $encoded = base64_encode($hash);

        if ($encoded === false) {
            throw new RuntimeException('PKCE challenge generation failed.');
        }

        return rtrim(strtr($encoded, '+/', '-_'), '=');
    }

    /**
     * HTTP válasz JSON testének biztonságos dekódolása.
     *
     * @return SsoUserInfo|null
     */
    private function decodeJsonResponse(Response $response): ?array
    {
        $decoded = $response->json();

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Hibaesetben naplózható, biztonságos diagnosztikai metaadatok felépítése.
     *
     * @param SsoUserInfo|null $payload
     * @return SsoDiagnostics
     */
    private function buildResponseDiagnostics(
        string $phase,
        ?string $endpoint,
        Response $response,
        ?array $payload,
        bool $hasAccessToken,
        ?string $responseMessage,
        ?string $oauthError,
    ): array {
        return [
            'sso_phase' => $phase,
            'sso_endpoint' => $endpoint,
            'http_status' => $response->status(),
            'is_json_response' => $payload !== null,
            'has_access_token' => $hasAccessToken,
            'oauth_error' => filled($oauthError) ? $oauthError : null,
            'response_message' => filled($responseMessage) ? Str::limit($responseMessage, 160) : null,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function normalizedScopes(): array
    {
        return collect(config('sso.scopes', []))
            ->map(static fn (mixed $scope): string => trim((string) $scope))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param array<int, string> $scopes
     */
    private function scopeContainsOpenId(array $scopes): bool
    {
        return in_array('openid', $scopes, true);
    }

    /**
     * @param PendingAuthorizationContext $context
     */
    private function storePendingAuthorizationContext(Request $request, array $context): void
    {
        $contexts = $request->session()->get(config('sso.pending_auth_session_key'), []);
        $contexts = is_array($contexts) ? $contexts : [];
        $contexts[$context['state']] = $context;

        $request->session()->put(config('sso.pending_auth_session_key'), $contexts);
    }

    /**
     * @return PendingAuthorizationContext|null
     */
    public function getPendingAuthorizationContextByState(Session $session, string $state): ?array
    {
        $contexts = $session->get(config('sso.pending_auth_session_key'), []);

        if (! is_array($contexts)) {
            return null;
        }

        $context = $contexts[$state] ?? null;

        return is_array($context) ? $context : null;
    }

    /**
     * @return IdentityValidationContext|null
     */
    public function getIdentityValidationContextByState(Session $session, string $state): ?array
    {
        $contexts = $session->get(config('sso.identity_validation_session_key'), []);

        if (! is_array($contexts)) {
            return null;
        }

        $context = $contexts[$state] ?? null;

        return is_array($context) ? $context : null;
    }

    public function getExpectedNonceByState(Session $session, string $state): ?string
    {
        $identityContext = $this->getIdentityValidationContextByState($session, $state);

        if (is_array($identityContext)) {
            $expectedNonce = trim((string) ($identityContext['expected_nonce'] ?? ''));

            return $expectedNonce !== '' ? $expectedNonce : null;
        }

        $pendingContext = $this->getPendingAuthorizationContextByState($session, $state);
        $expectedNonce = trim((string) ($pendingContext['nonce'] ?? ''));

        return $expectedNonce !== '' ? $expectedNonce : null;
    }

    /**
     * @return OidcSessionContext|null
     */
    public function getOidcSessionContext(Session $session): ?array
    {
        $context = $session->get(config('sso.oidc_session_context_key'));

        return is_array($context) ? $context : null;
    }

    /**
     * @return LogoutStateContext|null
     */
    public function getLogoutStateContext(Session $session): ?array
    {
        $context = $session->get(config('sso.logout_state_session_key'));

        return is_array($context) ? $context : null;
    }

    public function validateExpectedNonce(
        Request $request,
        ?string $expectedNonce,
        ?string $returnedNonce,
        bool $required,
        bool $allowDeferredWhenReturnedNonceMissing = true,
    ): void {
        $normalizedExpectedNonce = trim((string) ($expectedNonce ?? ''));
        $normalizedReturnedNonce = trim((string) ($returnedNonce ?? ''));

        if ($required && $normalizedExpectedNonce === '') {
            $this->auditLogService->logFailure(
                logName: AuditLogService::LOG_CLIENT_AUTH,
                event: 'client_auth.nonce.validation_failed',
                description: 'Client nonce validation failed.',
                causer: $request->user(),
                properties: [
                    'reason' => 'missing_expected_nonce',
                    'has_nonce' => false,
                    'scope_contains_openid' => true,
                    ...$this->auditLogService->requestContext($request),
                ],
            );

            $this->throwCallbackFailure(
                request: $request,
                message: 'Hianyzo vart OIDC nonce miatt nem folytathato a bejelentkezes. Inditsd ujra a login folyamatot.',
                status: 401,
                reason: 'missing_expected_nonce',
            );
        }

        if ($normalizedReturnedNonce === '') {
            if ($required) {
                if (! $allowDeferredWhenReturnedNonceMissing) {
                    $this->auditLogService->logFailure(
                        logName: AuditLogService::LOG_CLIENT_AUTH,
                        event: 'client_auth.nonce.validation_failed',
                        description: 'Client nonce validation failed.',
                        causer: $request->user(),
                        properties: [
                            'reason' => 'missing_returned_nonce',
                            'has_nonce' => $normalizedExpectedNonce !== '',
                            'scope_contains_openid' => true,
                            ...$this->auditLogService->requestContext($request),
                        ],
                    );

                    $this->throwCallbackFailure(
                        request: $request,
                        message: 'Az SSO ID token nem tartalmaz ervenyes nonce claimet. Inditsd ujra a bejelentkezest.',
                        status: 401,
                        reason: 'missing_returned_nonce',
                    );
                }

                $this->auditLogService->logSuccess(
                    logName: AuditLogService::LOG_CLIENT_AUTH,
                    event: 'client_auth.nonce.validation_deferred',
                    description: 'Client nonce validation deferred until identity response is available.',
                    causer: $request->user(),
                    properties: [
                        'has_nonce' => $normalizedExpectedNonce !== '',
                        'scope_contains_openid' => $required,
                        ...$this->auditLogService->requestContext($request),
                    ],
                );
            }

            return;
        }

        if ($normalizedExpectedNonce === '' || ! hash_equals($normalizedExpectedNonce, $normalizedReturnedNonce)) {
            $this->auditLogService->logFailure(
                logName: AuditLogService::LOG_CLIENT_AUTH,
                event: 'client_auth.nonce.validation_failed',
                description: 'Client nonce validation failed.',
                causer: $request->user(),
                properties: [
                    'reason' => 'invalid_nonce',
                    'has_nonce' => $normalizedExpectedNonce !== '',
                    'scope_contains_openid' => $required,
                    ...$this->auditLogService->requestContext($request),
                ],
            );

            $this->throwCallbackFailure(
                request: $request,
                message: 'Az OIDC nonce ellenorzes sikertelen. Inditsd ujra a bejelentkezest.',
                status: 401,
                reason: 'invalid_nonce',
            );
        }

        if ($required) {
            $this->auditLogService->logSuccess(
                logName: AuditLogService::LOG_CLIENT_AUTH,
                event: 'client_auth.nonce.validated',
                description: 'Client nonce validated against ID token.',
                causer: $request->user(),
                properties: [
                    'has_nonce' => true,
                    'scope_contains_openid' => true,
                    ...$this->auditLogService->requestContext($request),
                ],
            );
        }
    }

    /**
     * @param OidcSessionContext $context
     */
    private function storeOidcSessionContext(Request $request, array $context): void
    {
        $request->session()->put(config('sso.oidc_session_context_key'), $context);
    }

    /**
     * @param LogoutStateContext $logoutContext
     */
    private function clearLocalSession(Request $request, ?User $user, array $logoutContext): void
    {
        if ($user instanceof User) {
            $this->auditLogService->logSuccess(
                logName: AuditLogService::LOG_CLIENT_AUTH,
                event: 'client_auth.session.cleared',
                description: 'Client session cleared.',
                subject: $user,
                causer: $user,
                properties: $this->auditLogService->requestContext($request),
            );
        }

        Auth::guard('web')->logout();

        $request->session()->forget(config('sso.pending_auth_session_key'));
        $request->session()->forget(config('sso.identity_validation_session_key'));
        $request->session()->forget(config('sso.oidc_session_context_key'));
        $request->session()->invalidate();
        $request->session()->put(config('sso.logout_state_session_key'), $logoutContext);
        $request->session()->regenerateToken();

        if ($user instanceof User) {
            $this->auditLogService->logSuccess(
                logName: AuditLogService::LOG_CLIENT_AUTH,
                event: 'client_auth.logout.local_completed',
                description: 'Client local logout completed.',
                subject: $user,
                causer: $user,
                properties: [
                    'status' => 'logged_out',
                    ...$this->auditLogService->requestContext($request),
                ],
            );

            $this->auditLogService->logSuccess(
                logName: AuditLogService::LOG_CLIENT_AUTH,
                event: 'client_auth.logout.completed',
                description: 'Client logout completed.',
                subject: $user,
                causer: $user,
                properties: [
                    'status' => 'logged_out',
                    ...$this->auditLogService->requestContext($request),
                ],
            );
        }
    }

    private function buildProviderLogoutUrl(Request $request, string $logoutState): string
    {
        $endpoint = $this->configuredEndpoint('logout_endpoint');

        if ($endpoint === null) {
            throw new SsoAuthenticationException('Az SSO logout vegpont nincs konfiguralva.', 500);
        }

        $oidcSessionContext = $this->getOidcSessionContext($request->session());
        $idTokenHint = $this->normalizedOptionalString($oidcSessionContext['id_token_hint'] ?? null);
        $query = http_build_query(array_filter([
            'id_token_hint' => $idTokenHint,
            'post_logout_redirect_uri' => $this->logoutReturnUri(),
            'state' => $logoutState,
        ], static fn (mixed $value): bool => filled($value)), arg_separator: '&', encoding_type: PHP_QUERY_RFC3986);

        return $query === '' ? $endpoint : $endpoint.'?'.$query;
    }

    private function normalizedOptionalString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @param PendingAuthorizationContext $pendingAuthorization
     */
    private function retainIdentityValidationContext(Request $request, array $pendingAuthorization, string $validationStatus): void
    {
        $contexts = $request->session()->get(config('sso.identity_validation_session_key'), []);
        $contexts = is_array($contexts) ? $contexts : [];

        $contexts[$pendingAuthorization['state']] = [
            'state' => $pendingAuthorization['state'],
            'expected_nonce' => $pendingAuthorization['nonce'],
            'scope_contains_openid' => $pendingAuthorization['scope_contains_openid'],
            'retained_at' => now()->toIso8601String(),
            'validation_status' => $validationStatus,
            'validated_at' => $validationStatus === 'validated_from_id_token' ? now()->toIso8601String() : null,
        ];

        $request->session()->put(config('sso.identity_validation_session_key'), $contexts);

        if ((bool) ($pendingAuthorization['scope_contains_openid'] ?? false)) {
            $this->auditLogService->logSuccess(
                logName: AuditLogService::LOG_CLIENT_AUTH,
                event: 'client_auth.nonce.context_retained',
                description: 'Client nonce context retained for downstream identity validation.',
                causer: $request->user(),
                properties: [
                    'has_nonce' => trim((string) ($pendingAuthorization['nonce'] ?? '')) !== '',
                    'scope_contains_openid' => true,
                    ...$this->auditLogService->requestContext($request),
                ],
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function verifyIdToken(Request $request, string $idToken): array
    {
        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_CLIENT_AUTH,
            event: 'client_auth.id_token.received',
            description: 'Client ID token received.',
            causer: $request->user(),
            properties: [
                'has_nonce' => true,
                'scope_contains_openid' => true,
                ...$this->auditLogService->requestContext($request),
            ],
        );

        try {
            $claims = $this->oidcIdTokenVerifier->verify($idToken);
        } catch (SsoAuthenticationException $exception) {
            $reason = match ($exception->getMessage()) {
                'Az SSO ID token alairasa ervenytelen.' => 'invalid_signature',
                'Az SSO ID token issuer claimje ervenytelen.',
                'Az SSO ID token audience claimje ervenytelen.',
                'Az SSO ID token lejart.',
                'Az SSO ID token idobelyege ervenytelen.' => 'invalid_claims',
                default => 'invalid_id_token',
            };

            $event = $reason === 'invalid_signature'
                ? 'client_auth.id_token.signature_verification_failed'
                : 'client_auth.id_token.claim_validation_failed';

            $this->auditLogService->logFailure(
                logName: AuditLogService::LOG_CLIENT_AUTH,
                event: $event,
                description: $reason === 'invalid_signature'
                    ? 'Client ID token signature verification failed.'
                    : 'Client ID token claim validation failed.',
                causer: $request->user(),
                properties: [
                    'reason' => $reason,
                    'has_nonce' => true,
                    'scope_contains_openid' => true,
                    ...$this->auditLogService->requestContext($request),
                ],
            );

            throw $exception;
        }

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_CLIENT_AUTH,
            event: 'client_auth.id_token.signature_verified',
            description: 'Client ID token signature verified.',
            causer: $request->user(),
            properties: [
                'has_nonce' => trim((string) ($claims['nonce'] ?? '')) !== '',
                'scope_contains_openid' => true,
                ...$this->auditLogService->requestContext($request),
            ],
        );

        return $claims;
    }

    private function forgetPendingAuthorizationContext(Request $request, string $state): void
    {
        $contexts = $request->session()->get(config('sso.pending_auth_session_key'), []);

        if (! is_array($contexts) || ! array_key_exists($state, $contexts)) {
            return;
        }

        unset($contexts[$state]);
        $request->session()->put(config('sso.pending_auth_session_key'), $contexts);
    }
}
