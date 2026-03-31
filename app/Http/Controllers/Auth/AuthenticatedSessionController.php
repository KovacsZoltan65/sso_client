<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogService;
use App\Services\Auth\LocalFallbackAuthService;
use App\Services\Sso\SsoClientService;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(
        SsoClientService $ssoClientService,
        LocalFallbackAuthService $localFallbackAuthService,
        AuditLogService $auditLogService,
    ): Response
    {
        $decision = $localFallbackAuthService->buildLoginDecisionData(request());

        if ($decision['featureEnabled'] && $decision['blockedBecauseSsoHealthy']) {
            $auditLogService->logSuccess(
                logName: AuditLogService::LOG_CLIENT_SECURITY,
                event: 'client_security.local_fallback.sso_reachable_warning',
                description: 'Local fallback remains enabled while the SSO server is reachable.',
                causer: request()->user(),
                properties: [
                    'fallback_mode' => 'enabled_but_blocked',
                    'reachability_state' => 'reachable',
                    'incident_id' => $decision['incidentId'],
                    'failure_count' => (int) data_get($decision, 'reachability.failureCount', 0),
                    ...$auditLogService->requestContext(request()),
                ],
            );
        }

        return Inertia::render('Auth/Login', [
            'loginUrl' => route('auth.sso.redirect'),
            'localLoginUrl' => route('local-login'),
            'status' => session('status'),
            'ssoStatus' => $ssoClientService->status()->toArray(),
            'decision' => $decision,
        ]);
    }
}
