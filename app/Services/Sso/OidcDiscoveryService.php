<?php

namespace App\Services\Sso;

use App\Exceptions\SsoAuthenticationException;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class OidcDiscoveryService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getProviderMetadataOrNull(): ?array
    {
        $url = $this->discoveryUrl();

        if ($url === null) {
            return null;
        }

        try {
            return $this->getProviderMetadata();
        } catch (SsoAuthenticationException $exception) {
            $this->auditLogService->logFailure(
                logName: AuditLogService::LOG_CLIENT_AUTH,
                event: 'client_auth.oidc.discovery_validation_failed',
                description: 'OIDC discovery metadata validation failed.',
                properties: [
                    'reason' => 'invalid_discovery_document',
                    'status' => 'invalid',
                ],
            );

            return null;
        }
    }

    /**
     * @return array{
     *     issuer: string,
     *     authorization_endpoint: string,
     *     token_endpoint: string,
     *     userinfo_endpoint?: string,
     *     end_session_endpoint?: string,
     *     jwks_uri: string,
     *     id_token_signing_alg_values_supported: array<int, string>,
     *     claims_supported?: array<int, string>,
     *     frontchannel_logout_session_supported?: bool,
     *     backchannel_logout_supported?: bool,
     *     backchannel_logout_session_supported?: bool
     * }
     */
    public function getProviderMetadata(): array
    {
        $url = $this->discoveryUrl();

        if ($url === null) {
            throw new SsoAuthenticationException('Az SSO discovery vegpont nincs konfiguralva.', 502);
        }

        $cacheKey = 'sso.oidc.discovery.'.sha1($url);
        $ttl = max(60, (int) config('sso.oidc_discovery_cache_seconds', 300));

        /** @var array{
         *     issuer: string,
         *     authorization_endpoint: string,
         *     token_endpoint: string,
         *     userinfo_endpoint?: string,
         *     end_session_endpoint?: string,
         *     jwks_uri: string,
         *     id_token_signing_alg_values_supported: array<int, string>,
         *     claims_supported?: array<int, string>,
         *     frontchannel_logout_session_supported?: bool,
         *     backchannel_logout_supported?: bool,
         *     backchannel_logout_session_supported?: bool
         * } $metadata
         */
        $metadata = Cache::remember($cacheKey, $ttl, function () use ($url): array {
            return $this->fetchProviderMetadata($url);
        });

        return $metadata;
    }

    public function resolveDiscoveryValue(string $key): ?string
    {
        $metadata = $this->getProviderMetadataOrNull();

        if (! is_array($metadata)) {
            return null;
        }

        $value = trim((string) ($metadata[$key] ?? ''));

        return $value === '' ? null : $value;
    }

    public function discoveryUrl(): ?string
    {
        $configured = trim((string) config('sso.oidc_discovery_endpoint', ''));

        if ($configured !== '') {
            if (str_starts_with($configured, 'http://') || str_starts_with($configured, 'https://')) {
                return $configured;
            }

            $baseUrl = $this->serverBaseUrl();

            return $baseUrl === null ? null : $baseUrl.$configured;
        }

        $baseUrl = $this->serverBaseUrl();

        return $baseUrl === null ? null : $baseUrl.'/.well-known/openid-configuration';
    }

    private function serverBaseUrl(): ?string
    {
        $baseUrl = trim((string) config('sso.server_base_url'));

        return $baseUrl === '' ? null : rtrim($baseUrl, '/');
    }

    /**
     * @return array{
     *     issuer: string,
     *     authorization_endpoint: string,
     *     token_endpoint: string,
     *     userinfo_endpoint?: string,
     *     end_session_endpoint?: string,
     *     jwks_uri: string,
     *     id_token_signing_alg_values_supported: array<int, string>,
     *     claims_supported?: array<int, string>,
     *     frontchannel_logout_session_supported?: bool,
     *     backchannel_logout_supported?: bool,
     *     backchannel_logout_session_supported?: bool
     * }
     */
    private function fetchProviderMetadata(string $url): array
    {
        $response = Http::acceptJson()
            ->timeout((int) config('sso.timeout', 10))
            ->get($url);

        if (! $response->successful()) {
            throw new SsoAuthenticationException('Az SSO discovery vegpont hibaval valaszolt.', 502);
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new SsoAuthenticationException('Az SSO discovery dokumentum ervenytelen.', 502);
        }

        $issuer = trim((string) ($payload['issuer'] ?? ''));
        $authorizationEndpoint = trim((string) ($payload['authorization_endpoint'] ?? ''));
        $tokenEndpoint = trim((string) ($payload['token_endpoint'] ?? ''));
        $userinfoEndpoint = trim((string) ($payload['userinfo_endpoint'] ?? ''));
        $endSessionEndpoint = trim((string) ($payload['end_session_endpoint'] ?? ''));
        $jwksUri = trim((string) ($payload['jwks_uri'] ?? ''));
        $supportedAlgorithms = array_values(array_filter(
            $payload['id_token_signing_alg_values_supported'] ?? [],
            static fn (mixed $value): bool => is_string($value) && trim($value) !== '',
        ));
        $supportedClaims = array_values(array_filter(
            $payload['claims_supported'] ?? [],
            static fn (mixed $value): bool => is_string($value) && trim($value) !== '',
        ));

        if (
            $issuer === ''
            || $authorizationEndpoint === ''
            || $tokenEndpoint === ''
            || $jwksUri === ''
            || ! in_array('RS256', $supportedAlgorithms, true)
            || ($supportedClaims !== [] && ! in_array('sub', $supportedClaims, true))
        ) {
            throw new SsoAuthenticationException('Az SSO discovery dokumentum ervenytelen.', 502);
        }

        $metadata = [
            'issuer' => rtrim($issuer, '/'),
            'authorization_endpoint' => $authorizationEndpoint,
            'token_endpoint' => $tokenEndpoint,
            'jwks_uri' => $jwksUri,
            'id_token_signing_alg_values_supported' => $supportedAlgorithms,
        ];

        if ($supportedClaims !== []) {
            $metadata['claims_supported'] = $supportedClaims;
        }

        if ($userinfoEndpoint !== '') {
            $metadata['userinfo_endpoint'] = $userinfoEndpoint;
        }

        if ($endSessionEndpoint !== '') {
            $metadata['end_session_endpoint'] = $endSessionEndpoint;
        }

        foreach ([
            'frontchannel_logout_session_supported',
            'backchannel_logout_supported',
            'backchannel_logout_session_supported',
        ] as $booleanMetadataKey) {
            if (is_bool($payload[$booleanMetadataKey] ?? null)) {
                $metadata[$booleanMetadataKey] = $payload[$booleanMetadataKey];
            }
        }

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_CLIENT_AUTH,
            event: 'client_auth.oidc.discovery_loaded',
            description: 'OIDC discovery metadata loaded.',
            properties: [
                'status' => 'loaded',
            ],
        );

        return $metadata;
    }
}
