<?php

namespace App\Services\Sso;

use App\Data\SsoStatusData;
use App\Exceptions\SsoAuthenticationException;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\Client\ConnectionException;
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
 */
class SsoClientService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
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
            ->intersect(['openid', 'email'])
            ->count() === 2;
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

        $state = Str::random(64);
        $codeVerifier = Str::random(96);
        $codeChallenge = $this->codeChallengeFromVerifier($codeVerifier);

        $request->session()->put(config('sso.state_session_key'), $state);
        $request->session()->put(config('sso.pkce_verifier_session_key'), $codeVerifier);

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => (string) config('sso.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'scope' => implode(' ', config('sso.scopes', [])),
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);

        $redirectUrl = $this->configuredEndpoint('authorize_endpoint').'?'.$query;

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
        $expectedState = (string) $request->session()->pull(config('sso.state_session_key'));
        $codeVerifier = (string) $request->session()->pull(config('sso.pkce_verifier_session_key'));

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

        $accessToken = $this->exchangeCodeForAccessToken($code, $codeVerifier);
        $userInfo = $this->fetchUserInfo($accessToken);
        $user = $this->resolveLocalUser($userInfo);

        Auth::login($user, remember: false);
        $request->session()->regenerate();

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
    public function logout(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();

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

        $request->session()->forget(config('sso.state_session_key'));
        $request->session()->forget(config('sso.pkce_verifier_session_key'));
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($user instanceof User) {
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

    /**
     * Authorization code cseréje access tokenre az SSO szervernél.
     */
    private function exchangeCodeForAccessToken(string $code, string $codeVerifier): string
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

        return $accessToken;
    }

    /**
     * Userinfo adatok lekérése az SSO szervertől access tokennel.
     *
     * @return SsoUserInfo
     */
    private function fetchUserInfo(string $accessToken): array
    {
        $endpoint = $this->configuredEndpoint('userinfo_endpoint');

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('sso.timeout', 10))
                ->withToken($accessToken)
                ->get($endpoint);
        } catch (ConnectionException $exception) {
            $this->throwCallbackFailure(
                request: request(),
                message: 'Az SSO userinfo vegpont nem erheto el.',
                status: 502,
                reason: 'userinfo_endpoint_unreachable',
                previous: $exception,
                context: [
                    'sso_phase' => 'userinfo',
                    'sso_endpoint' => $endpoint,
                    'http_status' => null,
                    'is_json_response' => false,
                ],
            );
        }

        $payload = $this->decodeJsonResponse($response);
        $responseMessage = trim((string) data_get($payload, 'message'));
        $diagnostics = $this->buildResponseDiagnostics(
            phase: 'userinfo',
            endpoint: $endpoint,
            response: $response,
            payload: $payload,
            hasAccessToken: false,
            responseMessage: $responseMessage,
            oauthError: null,
        );

        if ($payload === null) {
            $this->throwCallbackFailure(
                request: request(),
                message: 'Az SSO userinfo vegpont ervenytelen, nem JSON valaszt adott.',
                status: 502,
                reason: 'userinfo_response_invalid_json',
                context: $diagnostics,
            );
        }

        if (! $response->successful()) {
            $this->throwCallbackFailure(
                request: request(),
                message: 'Az SSO userinfo vegpont hibaval valaszolt.',
                status: 502,
                reason: 'userinfo_endpoint_failed',
                context: $diagnostics,
            );
        }

        $userInfo = data_get($payload, 'data');

        if (! is_array($userInfo)) {
            $this->throwCallbackFailure(
                request: request(),
                message: 'Ervenytelen userinfo valasz erkezett az SSO szervertol.',
                status: 502,
                reason: 'userinfo_payload_invalid',
                context: $diagnostics,
            );
        }

        return $userInfo;
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
        return trim((string) (data_get($userInfo, 'id') ?: data_get($userInfo, 'sub')));
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
        $configuredScopes = collect(config('sso.scopes', []))
            ->map(static fn (mixed $scope): string => trim((string) $scope))
            ->filter()
            ->values();

        foreach (['openid', 'email'] as $requiredScope) {
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

    /**
     * Egy konfigurált SSO végpont teljes URL-jének feloldása.
     */
    private function configuredEndpoint(string $key): ?string
    {
        $endpoint = trim((string) config("sso.{$key}"));

        if ($endpoint === '') {
            return null;
        }

        if (Str::startsWith($endpoint, ['http://', 'https://'])) {
            return $endpoint;
        }

        $baseUrl = $this->serverBaseUrl();

        if ($baseUrl === null) {
            return null;
        }

        return $baseUrl.'/'.ltrim($endpoint, '/');
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
}
