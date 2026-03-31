<?php

namespace App\Services\Sso;

use App\Data\SsoReachabilityData;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SsoReachabilityService
{
    public const STATUS_HEALTHY = 'healthy';
    public const STATUS_MAINTENANCE = 'maintenance';
    public const STATUS_DEGRADED = 'degraded';
    public const STATUS_UNREACHABLE = 'unreachable';

    private const SNAPSHOT_CACHE_KEY = 'sso.reachability.snapshot';
    private const FAILURE_COUNT_CACHE_KEY = 'sso.reachability.failure_count';

    public function current(): SsoReachabilityData
    {
        $snapshot = Cache::get(self::SNAPSHOT_CACHE_KEY);

        if (is_array($snapshot)) {
            return $this->hydrate($snapshot);
        }

        return $this->refresh();
    }

    public function refresh(): SsoReachabilityData
    {
        $probe = $this->probe();
        $threshold = max(1, (int) config('sso.local_auth_failure_threshold', 2));

        if ($probe['healthy']) {
            Cache::forget(self::FAILURE_COUNT_CACHE_KEY);

            $snapshot = [
                'status' => self::STATUS_HEALTHY,
                'reachable' => true,
                'isReachable' => true,
                'isMaintenance' => false,
                'reason' => 'reachable',
                'httpStatus' => $probe['httpStatus'] ?? 200,
                'failureCount' => 0,
                'checkedAt' => now()->toIso8601String(),
                'retryAfter' => null,
                'source' => $probe['source'],
            ];

            Cache::put(
                self::SNAPSHOT_CACHE_KEY,
                $snapshot,
                now()->addSeconds(max(1, (int) config('sso.local_auth_healthy_cache_seconds', 20))),
            );

            return $this->hydrate($snapshot);
        }

        if (($probe['status'] ?? null) === self::STATUS_MAINTENANCE) {
            Cache::forget(self::FAILURE_COUNT_CACHE_KEY);

            $snapshot = [
                'status' => self::STATUS_MAINTENANCE,
                'reachable' => true,
                'isReachable' => true,
                'isMaintenance' => true,
                'reason' => $probe['reason'] ?? 'maintenance_mode',
                'httpStatus' => $probe['httpStatus'] ?? 503,
                'failureCount' => 0,
                'checkedAt' => now()->toIso8601String(),
                'retryAfter' => $probe['retryAfter'] ?? null,
                'source' => $probe['source'],
            ];

            Cache::put(
                self::SNAPSHOT_CACHE_KEY,
                $snapshot,
                now()->addSeconds(max(1, (int) config('sso.local_auth_healthy_cache_seconds', 20))),
            );

            return $this->hydrate($snapshot);
        }

        $failureCount = (int) Cache::increment(self::FAILURE_COUNT_CACHE_KEY);
        $reachable = $failureCount < $threshold;

        $snapshot = [
            'status' => $reachable ? self::STATUS_DEGRADED : self::STATUS_UNREACHABLE,
            'reachable' => $reachable,
            'isReachable' => $reachable,
            'isMaintenance' => false,
            'reason' => $reachable ? 'failure_threshold_not_met' : ($probe['reason'] ?? 'unreachable'),
            'httpStatus' => $probe['httpStatus'] ?? null,
            'failureCount' => $failureCount,
            'checkedAt' => now()->toIso8601String(),
            'retryAfter' => null,
            'source' => $probe['source'],
        ];

        Cache::put(
            self::SNAPSHOT_CACHE_KEY,
            $snapshot,
            now()->addSeconds($reachable ? 1 : max(1, (int) config('sso.local_auth_unreachable_cache_seconds', 5))),
        );

        return $this->hydrate($snapshot);
    }

    /**
     * @return array{healthy: bool, status?: string, reason?: string, httpStatus?: int|null, retryAfter?: string|null, source: string}
     */
    private function probe(): array
    {
        $timeoutMs = max(250, (int) config('sso.local_auth_check_timeout_ms', 1500));
        $readinessEndpoint = $this->configuredEndpoint('readiness_endpoint');

        if ($readinessEndpoint !== null) {
            return $this->probeEndpoint($readinessEndpoint, 'readiness_endpoint', $timeoutMs, true);
        }

        $authorizeEndpoint = $this->configuredEndpoint('authorize_endpoint');

        if ($authorizeEndpoint === null) {
            return [
                'healthy' => true,
                'reason' => 'missing_probe_endpoint_configuration',
                'source' => 'configuration',
            ];
        }

        return $this->probeEndpoint($authorizeEndpoint, 'authorize_probe', $timeoutMs, false);
    }

    /**
     * @return array{healthy: bool, status?: string, reason?: string, httpStatus?: int|null, retryAfter?: string|null, source: string}
     */
    private function probeEndpoint(string $url, string $source, int $timeoutMs, bool $strictServerErrors): array
    {
        try {
            $response = Http::acceptJson()
                ->connectTimeout((int) max(1, floor($timeoutMs / 1000)))
                ->timeout((int) max(1, ceil($timeoutMs / 1000)))
                ->get($url);
        } catch (ConnectionException) {
            return [
                'healthy' => false,
                'status' => self::STATUS_UNREACHABLE,
                'reason' => 'connection_exception',
                'httpStatus' => null,
                'source' => $source,
            ];
        }

        return $this->interpretResponse($response, $source, $strictServerErrors);
    }

    /**
     * A 4xx authorize valasz is elo upstreamot jelezhet, ezert itt csak a halozati
     * hibak es a szerveroldali hibak nyithatjak ki a fallback modot.
     *
     * @return array{healthy: bool, status?: string, reason?: string, httpStatus?: int|null, retryAfter?: string|null, source: string}
     */
    private function interpretResponse(Response $response, string $source, bool $strictServerErrors): array
    {
        if ($response->status() === 503) {
            return [
                'healthy' => false,
                'status' => self::STATUS_MAINTENANCE,
                'reason' => 'maintenance_mode',
                'httpStatus' => 503,
                'retryAfter' => $response->header('Retry-After'),
                'source' => $source,
            ];
        }

        if ($response->serverError()) {
            return [
                'healthy' => false,
                'status' => self::STATUS_UNREACHABLE,
                'reason' => 'server_error',
                'httpStatus' => $response->status(),
                'source' => $source,
            ];
        }

        if ($strictServerErrors && ! $response->successful()) {
            return [
                'healthy' => false,
                'status' => self::STATUS_UNREACHABLE,
                'reason' => 'readiness_probe_failed',
                'httpStatus' => $response->status(),
                'source' => $source,
            ];
        }

        return [
            'healthy' => true,
            'status' => self::STATUS_HEALTHY,
            'reason' => 'reachable',
            'httpStatus' => $response->status(),
            'source' => $source,
        ];
    }

    private function hydrate(array $snapshot): SsoReachabilityData
    {
        return new SsoReachabilityData(
            status: (string) ($snapshot['status'] ?? self::STATUS_HEALTHY),
            reachable: (bool) ($snapshot['reachable'] ?? true),
            isReachable: (bool) ($snapshot['isReachable'] ?? true),
            isMaintenance: (bool) ($snapshot['isMaintenance'] ?? false),
            reason: (string) ($snapshot['reason'] ?? 'unknown'),
            httpStatus: isset($snapshot['httpStatus']) ? (int) $snapshot['httpStatus'] : null,
            failureCount: (int) ($snapshot['failureCount'] ?? 0),
            checkedAt: isset($snapshot['checkedAt']) ? (string) $snapshot['checkedAt'] : null,
            retryAfter: isset($snapshot['retryAfter']) ? (string) $snapshot['retryAfter'] : null,
            source: (string) ($snapshot['source'] ?? 'unknown'),
        );
    }

    private function configuredEndpoint(string $key): ?string
    {
        $endpoint = trim((string) config("sso.{$key}"));

        if ($endpoint === '') {
            return null;
        }

        if (str_starts_with($endpoint, 'http://') || str_starts_with($endpoint, 'https://')) {
            return $endpoint;
        }

        $baseUrl = trim((string) config('sso.server_base_url'));

        if ($baseUrl === '') {
            return null;
        }

        return rtrim($baseUrl, '/').'/'.ltrim($endpoint, '/');
    }
}
