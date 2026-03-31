<?php

namespace App\Services\Emergency;

use App\Data\Emergency\EmergencyActivationData;
use App\Services\Audit\AuditLogService;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;

class EmergencyModeService
{
    public const STATE_NORMAL = 'normal';
    public const STATE_DEGRADED_AVAILABLE = 'degraded_available';
    public const STATE_EMERGENCY_ACTIVE = 'emergency_active';

    public function __construct(
        private readonly CacheRepository $cache,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function currentState(bool $ssoReachable): string
    {
        $this->expireIfNeeded();

        $activation = $this->activation();

        if ($activation !== null) {
            return self::STATE_EMERGENCY_ACTIVE;
        }

        if (! $ssoReachable && $this->healthcheckEnabled()) {
            return self::STATE_DEGRADED_AVAILABLE;
        }

        return self::STATE_NORMAL;
    }

    public function featureEnabled(): bool
    {
        return (bool) config('emergency.enabled', false);
    }

    public function manualActivationRequired(): bool
    {
        return (bool) config('emergency.require_manual_activation', true);
    }

    public function healthcheckEnabled(): bool
    {
        return (bool) config('emergency.healthcheck_enabled', true);
    }

    /**
     * @return array<string, string>|null
     */
    public function activation(): ?array
    {
        $activation = $this->cache->get($this->cacheKey());

        return is_array($activation) ? $activation : null;
    }

    public function isEmergencyActive(): bool
    {
        $this->expireIfNeeded();

        return $this->activation() !== null;
    }

    public function emergencyLoginAvailable(bool $ssoReachable): bool
    {
        if (! $this->featureEnabled()) {
            return false;
        }

        if (! $this->manualActivationRequired()) {
            return ! $ssoReachable;
        }

        return $this->isEmergencyActive();
    }

    public function activate(EmergencyActivationData $data, ?Request $request = null): array
    {
        if (! $this->featureEnabled()) {
            throw new InvalidArgumentException('Emergency mode feature is disabled.');
        }

        $ttlMinutes = $data->ttlMinutes ?? (int) config('emergency.activation_ttl_minutes', 60);
        $referenceId = $data->referenceId ?: (string) Str::uuid();
        $now = now();
        $expiresAt = $now->copy()->addMinutes($ttlMinutes);

        $payload = [
            'reason' => trim($data->reason),
            'activated_by' => trim($data->operator),
            'activated_at' => $now->toIso8601String(),
            'expires_at' => $expiresAt->toIso8601String(),
            'reference_id' => $referenceId,
        ];

        $this->cache->put($this->cacheKey(), $payload, $expiresAt);

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_CLIENT_SECURITY,
            event: 'client_security.emergency_mode.enabled',
            description: 'Client emergency mode enabled.',
            properties: [
                'reason' => $payload['reason'],
                'status' => self::STATE_EMERGENCY_ACTIVE,
                'activation_reference' => $referenceId,
                'activated_by' => $payload['activated_by'],
                ...($request ? $this->auditLogService->requestContext($request) : []),
            ],
        );

        return $payload;
    }

    public function deactivate(string $reason, string $operator, ?Request $request = null): void
    {
        $activation = $this->activation();

        $this->cache->forget($this->cacheKey());

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_CLIENT_SECURITY,
            event: 'client_security.emergency_mode.disabled',
            description: 'Client emergency mode disabled.',
            properties: [
                'reason' => trim($reason),
                'status' => self::STATE_NORMAL,
                'activation_reference' => $activation['reference_id'] ?? null,
                'activated_by' => trim($operator),
                ...($request ? $this->auditLogService->requestContext($request) : []),
            ],
        );
    }

    public function expireIfNeeded(): void
    {
        $activation = $this->activation();

        if ($activation === null) {
            return;
        }

        $expiresAt = $activation['expires_at'] ?? null;

        if (! is_string($expiresAt) || now()->lt($expiresAt)) {
            return;
        }

        $this->cache->forget($this->cacheKey());

        $this->auditLogService->logFailure(
            logName: AuditLogService::LOG_CLIENT_SECURITY,
            event: 'client_security.emergency_mode.expired',
            description: 'Client emergency mode expired.',
            properties: [
                'reason' => 'activation_ttl_expired',
                'status' => self::STATE_NORMAL,
                'activation_reference' => $activation['reference_id'] ?? null,
                'activated_by' => $activation['activated_by'] ?? null,
            ],
        );
    }

    private function cacheKey(): string
    {
        return (string) config('emergency.state_cache_key', 'sso_client.emergency.state');
    }
}
