<?php

namespace App\Services;

use App\Data\DashboardStatsData;
use App\Models\User;
use App\Repositories\Contracts\UserRepository;
use App\Services\Sso\SsoClientService;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DashboardService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly SsoClientService $ssoClientService,
    ) {
    }

    public function build(User $user): array
    {
        return [
            'stats' => new DashboardStatsData(
                users: $this->users->countAll(),
                roles: Role::query()->count(),
                permissions: Permission::query()->count(),
                activityEntries: Activity::query()->count(),
            ),
            'recentUsers' => $this->users->recent()
                ->map(fn (User $recentUser) => [
                    'id' => $recentUser->id,
                    'name' => $recentUser->name,
                    'email' => $recentUser->email,
                    'roles' => $recentUser->getRoleNames()->values()->all(),
                ]),
            'recentActivity' => Activity::query()
                ->latest()
                ->limit(6)
                ->get()
                ->map(fn (Activity $activity) => [
                    'id' => $activity->id,
                    'description' => $activity->description,
                    'event' => $activity->event,
                    'created_at' => optional($activity->created_at)?->diffForHumans(),
                ]),
            'ssoStatus' => $this->ssoClientService->status()->toArray(),
            'userContext' => [
                'name' => $user->name,
                'roles' => $user->getRoleNames()->values()->all(),
            ],
        ];
    }
}
