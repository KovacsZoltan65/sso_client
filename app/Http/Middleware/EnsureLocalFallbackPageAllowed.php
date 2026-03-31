<?php

namespace App\Http\Middleware;

use App\Services\Audit\AuditLogService;
use App\Services\Auth\LocalFallbackAuthService;
use App\Services\Sso\SsoReachabilityService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLocalFallbackPageAllowed
{
    public function __construct(
        private readonly LocalFallbackAuthService $localFallbackAuthService,
        private readonly AuditLogService $auditLogService,
        private readonly SsoReachabilityService $ssoReachabilityService,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $decision = $this->localFallbackAuthService->buildLoginDecisionData($request);
        $reachability = $this->ssoReachabilityService->current();

        if ($this->localFallbackAuthService->isFallbackAllowed($request)) {
            $this->auditLogService->logSuccess(
                logName: AuditLogService::LOG_CLIENT_AUTH,
                event: 'client_auth.local_fallback.page_allowed',
                description: 'Local fallback login page allowed.',
                properties: [
                    'fallback_mode' => 'allowed',
                    'reachability_state' => $reachability->status,
                    'fallback_reason' => $decision['fallbackReason'],
                    'allow_degraded_enabled' => $this->localFallbackAuthService->allowsDegraded(),
                    'incident_id' => $this->localFallbackAuthService->incidentId(),
                    'failure_count' => $reachability->failureCount,
                    ...$this->auditLogService->requestContext($request),
                ],
            );

            return $next($request);
        }

        $reason = $this->localFallbackAuthService->isFeatureEnabled() ? 'sso_reachable' : 'feature_disabled';
        $isMaintenance = $reachability->status === SsoReachabilityService::STATUS_MAINTENANCE;
        $isDegraded = $reachability->status === SsoReachabilityService::STATUS_DEGRADED;

        $this->auditLogService->logFailure(
            logName: AuditLogService::LOG_CLIENT_AUTH,
            event: 'client_auth.local_fallback.page_blocked',
            description: 'Local fallback login page blocked.',
            properties: [
                'reason' => $reason,
                'fallback_mode' => 'blocked',
                'reachability_state' => $reason === 'sso_reachable'
                    ? $reachability->status
                    : 'disabled',
                'fallback_reason' => $decision['fallbackReason'],
                'allow_degraded_enabled' => $this->localFallbackAuthService->allowsDegraded(),
                'incident_id' => $this->localFallbackAuthService->incidentId(),
                'failure_count' => $reachability->failureCount,
                ...$this->auditLogService->requestContext($request),
            ],
        );

        return redirect()
            ->route('login')
            ->with('error', $reason === 'sso_reachable'
                ? ($isMaintenance
                    ? 'A local fallback login jelenleg tiltott, mert az SSO szerver karbantartas alatt van.'
                    : ($isDegraded
                        ? 'A local fallback login jelenleg tiltott, mert a degraded fallback nincs engedelyezve.'
                        : 'A local fallback login jelenleg tiltott, mert az SSO szerver elerheto.'))
                : 'A local fallback login nincs engedelyezve ebben a kornyezetben.');
    }
}
