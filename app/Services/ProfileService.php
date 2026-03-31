<?php

namespace App\Services;

use App\Data\UserSummaryData;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Sso\SsoClientService;

class ProfileService
{
    public function __construct(
        private readonly SsoClientService $ssoClientService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function profilePageData(User $user): array
    {
        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_CLIENT_ACCOUNT,
            event: 'client_profile.page.viewed',
            description: 'Client profile page viewed.',
            subject: $user,
            causer: $user,
            properties: [
                'status' => 'visible',
            ],
        );

        return [
            'authUser' => UserSummaryData::fromModel($user)->toArray(),
            'profileApi' => $this->ssoClientService->selfServiceProfileApi(),
        ];
    }
}
