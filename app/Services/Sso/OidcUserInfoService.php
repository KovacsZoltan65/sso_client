<?php

namespace App\Services\Sso;

use App\Exceptions\SsoAuthenticationException;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class OidcUserInfoService
{
    /**
     * @var array<int, string>
     */
    private const MINIMAL_ID_TOKEN_CLAIMS = ['iss', 'sub', 'aud', 'iat', 'exp', 'nonce', 'sid'];

    /**
     * @var array<string, array<int, string>>
     */
    private const USERINFO_SCOPE_TO_CLAIMS = [
        'openid' => ['sub'],
        'profile' => ['name'],
        'email' => ['email', 'email_verified'],
    ];

    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly OidcDiscoveryService $oidcDiscoveryService,
    ) {
    }

    public function resolveEndpoint(): ?string
    {
        $configured = trim((string) config('sso.userinfo_endpoint', ''));

        if ($configured !== '') {
            if (str_starts_with($configured, 'http://') || str_starts_with($configured, 'https://')) {
                return $configured;
            }

            $baseUrl = rtrim((string) config('sso.server_base_url'), '/');

            return $baseUrl === '' ? null : $baseUrl.'/'.ltrim($configured, '/');
        }

        $discoveredEndpoint = $this->oidcDiscoveryService->resolveDiscoveryValue('userinfo_endpoint');

        if ($discoveredEndpoint !== null) {
            return $discoveredEndpoint;
        }

        $baseUrl = rtrim((string) config('sso.server_base_url'), '/');

        return $baseUrl === '' ? null : $baseUrl.'/api/oauth/userinfo';
    }

    /**
     * @return array<string, mixed>
     */
    public function fetch(string $accessToken): array
    {
        $endpoint = $this->resolveEndpoint();

        if ($endpoint === null) {
            throw new SsoAuthenticationException('Az SSO userinfo vegpont nem erheto el.', 502);
        }

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('sso.timeout', 10))
                ->withToken($accessToken)
                ->get($endpoint);
        } catch (ConnectionException $exception) {
            throw new SsoAuthenticationException('Az SSO userinfo vegpont nem erheto el.', 502, $exception);
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new SsoAuthenticationException('Az SSO userinfo vegpont ervenytelen, nem JSON valaszt adott.', 502);
        }

        if (! $response->successful()) {
            throw new SsoAuthenticationException('Az SSO userinfo vegpont hibaval valaszolt.', 502);
        }

        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            throw new SsoAuthenticationException('Ervenytelen userinfo valasz erkezett az SSO szervertol.', 502);
        }

        if (trim((string) ($data['sub'] ?? '')) === '') {
            throw new SsoAuthenticationException('Ervenytelen userinfo valasz erkezett az SSO szervertol.', 502);
        }

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_CLIENT_AUTH,
            event: 'client_auth.userinfo.loaded',
            description: 'OIDC userinfo loaded.',
            properties: [
                'status' => 'loaded',
            ],
        );

        return $data;
    }

    /**
     * @return array<int, string>
     */
    public function expectedIdTokenClaims(): array
    {
        return self::MINIMAL_ID_TOKEN_CLAIMS;
    }

    /**
     * @param array<int, string> $scopes
     * @return array<int, string>
     */
    public function expectedUserInfoClaimsForScopes(array $scopes): array
    {
        return collect($scopes)
            ->map(static fn (mixed $scope): string => trim((string) $scope))
            ->filter()
            ->flatMap(static fn (string $scope): array => self::USERINFO_SCOPE_TO_CLAIMS[$scope] ?? [])
            ->unique()
            ->values()
            ->all();
    }

    public function assertSubjectMatches(?string $expectedSubject, array $userInfo): void
    {
        $returnedSubject = trim((string) ($userInfo['sub'] ?? ''));
        $expectedSubject = trim((string) $expectedSubject);

        if ($expectedSubject === '') {
            return;
        }

        if ($returnedSubject === '' || hash_equals($expectedSubject, $returnedSubject)) {
            if ($returnedSubject !== '') {
                return;
            }

            $this->auditLogService->logFailure(
                logName: AuditLogService::LOG_CLIENT_AUTH,
                event: 'client_auth.userinfo.validation_failed',
                description: 'OIDC userinfo validation failed.',
                properties: [
                    'reason' => 'missing_subject',
                    'status' => 'invalid',
                ],
            );

            throw new SsoAuthenticationException('Az SSO userinfo subject claimje ervenytelen.', 401);
        }

        $this->auditLogService->logFailure(
            logName: AuditLogService::LOG_CLIENT_AUTH,
            event: 'client_auth.userinfo.validation_failed',
            description: 'OIDC userinfo validation failed.',
            properties: [
                'reason' => 'subject_mismatch',
                'status' => 'invalid',
            ],
        );

        throw new SsoAuthenticationException('Az SSO userinfo subject claimje ervenytelen.', 401);
    }
}
