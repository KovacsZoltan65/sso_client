<?php

namespace App\Services\Emergency;

use App\Data\Emergency\EmergencyStatusData;
use App\Services\Sso\SsoClientService;
use Illuminate\Support\Facades\Http;
use Throwable;

class EmergencyStatusService
{
    public function __construct(
        private readonly EmergencyModeService $emergencyModeService,
        private readonly SsoClientService $ssoClientService,
    ) {
    }

    public function status(): EmergencyStatusData
    {
        $ssoReachable = $this->resolveSsoReachable();
        $state = $this->emergencyModeService->currentState($ssoReachable);
        $activation = $this->emergencyModeService->activation();

        return new EmergencyStatusData(
            state: $state,
            featureEnabled: $this->emergencyModeService->featureEnabled(),
            manualActivationRequired: $this->emergencyModeService->manualActivationRequired(),
            healthcheckEnabled: $this->emergencyModeService->healthcheckEnabled(),
            ssoReachable: $ssoReachable,
            emergencyLoginAvailable: $this->emergencyModeService->emergencyLoginAvailable($ssoReachable),
            bannerMessage: $state === EmergencyModeService::STATE_EMERGENCY_ACTIVE
                ? (string) config('emergency.banner_message')
                : null,
            activationReference: $activation['reference_id'] ?? null,
            reason: $activation['reason'] ?? null,
            activatedBy: $activation['activated_by'] ?? null,
            activatedAt: $activation['activated_at'] ?? null,
            expiresAt: $activation['expires_at'] ?? null,
            capabilities: $this->capabilities(),
        );
    }

    /**
     * @return array<int, string>
     */
    private function capabilities(): array
    {
        $capabilities = ['Emergency status'];

        if ((bool) config('emergency.allow_view_audit_logs', true)) {
            $capabilities[] = 'Read-only audit logs';
        }

        if ((bool) config('emergency.allow_view_users', true)) {
            $capabilities[] = 'Read-only users index';
        }

        if ((bool) config('emergency.allow_view_companies', true)) {
            $capabilities[] = 'Read-only companies index';
        }

        return $capabilities;
    }

    private function resolveSsoReachable(): bool
    {
        if (! $this->emergencyModeService->healthcheckEnabled()) {
            return true;
        }

        try {
            $status = $this->ssoClientService->status();

            if (! $status->configured || $status->serverBaseUrl === null) {
                return false;
            }

            $response = Http::timeout(3)->get(rtrim($status->serverBaseUrl, '/').'/up');

            return $response->successful();
        } catch (Throwable) {
            return false;
        }
    }
}
