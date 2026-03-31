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

class EmergencyDashboardController extends Controller
{
    public function __construct(
        private readonly EmergencyReadOnlyService $emergencyReadOnlyService,
        private readonly EmergencyStatusService $emergencyStatusService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        /** @var EmergencyAccount $account */
        $account = auth('emergency')->user();

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_CLIENT_EMERGENCY,
            event: 'client_emergency.dashboard.viewed',
            description: 'Emergency dashboard viewed.',
            subject: $account,
            causer: $account,
            properties: [
                'status' => 'read_only',
                'emergency_account_id' => $account->getKey(),
                'emergency_role' => $account->role,
                ...$this->auditLogService->requestContext($request),
            ],
        );

        return Inertia::render('Emergency/Dashboard', [
            ...$this->emergencyReadOnlyService->dashboard($account),
            'status' => $this->emergencyStatusService->status()->toArray(),
        ]);
    }
}
