<?php

namespace App\Services;

use App\Data\UserSummaryData;
use App\Models\User;
use App\Services\Sso\SsoClientService;

class ProfileService
{
    public function __construct(
        private readonly SsoClientService $ssoClientService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function profilePageData(User $user): array
    {
        return [
            'authUser' => UserSummaryData::fromModel($user)->toArray(),
            'profileApi' => $this->ssoClientService->selfServiceProfileApi(),
        ];
    }
}
