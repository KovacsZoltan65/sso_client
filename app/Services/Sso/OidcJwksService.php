<?php

namespace App\Services\Sso;

use App\Exceptions\SsoAuthenticationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class OidcJwksService
{
    public function __construct(
        private readonly OidcDiscoveryService $discoveryService,
    ) {
    }

    /**
     * @return array{keys: array<int, array<string, mixed>>}
     */
    public function getJwkSet(): array
    {
        $cacheKey = 'sso.oidc.jwks.'.sha1($this->jwksUrl());
        $ttl = max(60, (int) config('sso.oidc_jwks_cache_seconds', 300));

        /** @var array{keys: array<int, array<string, mixed>>} $jwks */
        $jwks = Cache::remember($cacheKey, $ttl, function (): array {
            return $this->fetchJwkSet();
        });

        return $jwks;
    }

    /**
     * @return array<string, mixed>
     */
    public function findKeyByKid(string $kid): array
    {
        $keys = $this->getJwkSet()['keys'] ?? [];

        foreach ($keys as $key) {
            if (is_array($key) && trim((string) ($key['kid'] ?? '')) === $kid) {
                return $key;
            }
        }

        throw new SsoAuthenticationException('Az SSO JWKS nem tartalmazza a szukseges alairasi kulcsot.', 502);
    }

    public function jwksUrl(): string
    {
        $configured = trim((string) config('sso.oidc_jwks_endpoint', ''));

        if ($configured !== '') {
            if (str_starts_with($configured, 'http://') || str_starts_with($configured, 'https://')) {
                return $configured;
            }

            $baseUrl = rtrim((string) config('sso.server_base_url'), '/');

            return $baseUrl.$configured;
        }

        $discoveryJwksUri = $this->discoveryService->resolveDiscoveryValue('jwks_uri');

        if ($discoveryJwksUri !== null) {
            return $discoveryJwksUri;
        }

        $baseUrl = rtrim((string) config('sso.server_base_url'), '/');

        return $baseUrl.'/.well-known/jwks.json';
    }

    /**
     * @return array{keys: array<int, array<string, mixed>>}
     */
    private function fetchJwkSet(): array
    {
        $response = Http::acceptJson()
            ->timeout((int) config('sso.timeout', 10))
            ->get($this->jwksUrl());

        if (! $response->successful()) {
            throw new SsoAuthenticationException('Az SSO JWKS vegpont hibaval valaszolt.', 502);
        }

        $payload = $response->json();

        if (! is_array($payload) || ! isset($payload['keys']) || ! is_array($payload['keys'])) {
            throw new SsoAuthenticationException('Az SSO JWKS valasz ervenytelen.', 502);
        }

        return [
            'keys' => array_values(array_filter($payload['keys'], 'is_array')),
        ];
    }
}
