<?php

namespace App\Http\Middleware;

use App\Services\Audit\AuditLogService;
use App\Services\Auth\LocalFallbackAuthService;
use App\Services\Sso\SsoReachabilityService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLocalFallbackSubmitAllowed
{
    public function __construct(
        private readonly LocalFallbackAuthService $localFallbackAuthService,
        private readonly AuditLogService $auditLogService,
        private readonly SsoReachabilityService $ssoReachabilityService,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->localFallbackAuthService->isFallbackAllowed($request, true)) {
            return $next($request);
        }

        $reachability = $this->ssoReachabilityService->current();
        $isMaintenance = $reachability->status === SsoReachabilityService::STATUS_MAINTENANCE;
        $isDegraded = $reachability->status === SsoReachabilityService::STATUS_DEGRADED;

        $this->auditLogService->logFailure(
            logName: AuditLogService::LOG_CLIENT_AUTH,
            event: 'client_auth.local_fallback.page_blocked',
            description: 'Local fallback login submit blocked.',
            properties: [
                'reason' => 'sso_reachable_on_submit',
                'fallback_mode' => 'blocked',
                'reachability_state' => $reachability->status,
                'fallback_reason' => $this->localFallbackAuthService->buildLoginDecisionData($request)['fallbackReason'],
                'allow_degraded_enabled' => $this->localFallbackAuthService->allowsDegraded(),
                'incident_id' => $this->localFallbackAuthService->incidentId(),
                'failure_count' => $reachability->failureCount,
                ...$this->auditLogService->requestContext($request),
            ],
        );

        return redirect()
            ->route('login')
            ->with('error', $isMaintenance
                ? 'A local fallback login jelenleg tiltott, mert az SSO szerver karbantartas alatt van.'
                : ($isDegraded
                    ? 'A local fallback login jelenleg tiltott, mert a degraded fallback nincs engedelyezve.'
                    : 'A local fallback login mar nem erheto el, mert az SSO szerver ujra elerhetove valt.'));
    }
}
