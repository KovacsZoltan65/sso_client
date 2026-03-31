<?php

namespace App\Http\Controllers\Emergency;

use App\Http\Controllers\Controller;
use App\Models\EmergencyAccount;
use App\Services\Audit\AuditLogService;
use App\Services\Emergency\EmergencyReadOnlyService;
use App\Services\Emergency\EmergencyStatusService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmergencyReadOnlyController extends Controller
{
    public function __construct(
        private readonly EmergencyReadOnlyService $emergencyReadOnlyService,
        private readonly EmergencyStatusService $emergencyStatusService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function auditLogs(Request $request): Response
    {
        /** @var EmergencyAccount $account */
        $account = auth('emergency')->user();

        $this->trackView($request, $account, 'client_emergency.audit_logs.viewed', 'Emergency audit logs viewed.');

        return Inertia::render('Emergency/AuditLogs', [
            'entries' => $this->emergencyReadOnlyService->auditLogs(),
            'status' => $this->emergencyStatusService->status()->toArray(),
        ]);
    }

    public function users(Request $request): Response
    {
        /** @var EmergencyAccount $account */
        $account = auth('emergency')->user();

        abort_unless((bool) config('emergency.allow_view_users', true), 403);

        $this->trackView($request, $account, 'client_emergency.users.viewed', 'Emergency users view accessed.');

        return Inertia::render('Emergency/Users', [
            'users' => $this->emergencyReadOnlyService->users(),
            'status' => $this->emergencyStatusService->status()->toArray(),
        ]);
    }

    public function companies(Request $request): Response
    {
        /** @var EmergencyAccount $account */
        $account = auth('emergency')->user();

        abort_unless((bool) config('emergency.allow_view_companies', true), 403);

        $this->trackView($request, $account, 'client_emergency.companies.viewed', 'Emergency companies view accessed.');

        return Inertia::render('Emergency/Companies', [
            'companies' => $this->emergencyReadOnlyService->companies(),
            'status' => $this->emergencyStatusService->status()->toArray(),
        ]);
    }

    private function trackView(Request $request, EmergencyAccount $account, string $event, string $description): void
    {
        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_CLIENT_EMERGENCY,
            event: $event,
            description: $description,
            subject: $account,
            causer: $account,
            properties: [
                'status' => 'read_only',
                'emergency_account_id' => $account->getKey(),
                'emergency_role' => $account->role,
                ...$this->auditLogService->requestContext($request),
            ],
        );
    }
}
