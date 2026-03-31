<?php

namespace App\Http\Middleware\Emergency;

use App\Models\EmergencyAccount;
use App\Services\Audit\AuditLogService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmergencyRole
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        /** @var EmergencyAccount|null $account */
        $account = auth('emergency')->user();

        if (! $account instanceof EmergencyAccount || ! in_array($account->role, $roles, true)) {
            $this->auditLogService->logFailure(
                logName: AuditLogService::LOG_CLIENT_SECURITY,
                event: 'client_security.emergency_access.denied',
                description: 'Emergency access denied.',
                subject: $account,
                causer: $account,
                properties: [
                    'reason' => 'role_denied',
                    'status' => 'denied',
                    'emergency_account_id' => $account?->getKey(),
                    'emergency_role' => $account?->role,
                    ...$this->auditLogService->requestContext($request),
                ],
            );

            abort(403);
        }

        return $next($request);
    }
}
