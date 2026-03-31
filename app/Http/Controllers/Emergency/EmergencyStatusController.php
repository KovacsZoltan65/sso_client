<?php

namespace App\Http\Controllers\Emergency;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogService;
use App\Services\Emergency\EmergencyStatusService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmergencyStatusController extends Controller
{
    public function __construct(
        private readonly EmergencyStatusService $emergencyStatusService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $status = $this->emergencyStatusService->status();

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_CLIENT_EMERGENCY,
            event: 'client_emergency.status.viewed',
            description: 'Emergency status viewed.',
            causer: auth('emergency')->user(),
            properties: [
                'status' => $status->state,
                ...$this->auditLogService->requestContext($request),
            ],
        );

        return Inertia::render('Emergency/Status', [
            'status' => $status->toArray(),
        ]);
    }
}
