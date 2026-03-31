<?php

namespace App\Services\Emergency;

use App\Models\Company;
use App\Models\EmergencyAccount;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

class EmergencyReadOnlyService
{
    /**
     * @return array<string, mixed>
     */
    public function dashboard(EmergencyAccount $account): array
    {
        return [
            'account' => [
                'id' => $account->getKey(),
                'username' => $account->username,
                'role' => $account->role,
            ],
            'summary' => [
                'users' => User::query()->count(),
                'companies' => Company::query()->count(),
                'activityEntries' => Activity::query()->count(),
            ],
            'recentActivity' => Activity::query()
                ->latest()
                ->limit(6)
                ->get()
                ->map(static fn (Activity $activity): array => [
                    'id' => $activity->id,
                    'log_name' => $activity->log_name,
                    'description' => $activity->description,
                    'event' => $activity->event,
                    'created_at' => optional($activity->created_at)?->toDateTimeString(),
                ])
                ->all(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function auditLogs(): array
    {
        return Activity::query()
            ->latest()
            ->limit(25)
            ->get()
            ->map(static fn (Activity $activity): array => [
                'id' => $activity->id,
                'log_name' => $activity->log_name,
                'description' => $activity->description,
                'event' => $activity->event,
                'subject_type' => class_basename((string) $activity->subject_type),
                'created_at' => optional($activity->created_at)?->toDateTimeString(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function users(): array
    {
        return User::query()
            ->latest()
            ->limit(25)
            ->get(['id', 'sso_user_id', 'name', 'email', 'local_status', 'last_authenticated_at', 'created_at'])
            ->map(static fn (User $user): array => [
                'id' => $user->id,
                'sso_user_id' => $user->sso_user_id,
                'name' => $user->name,
                'email' => $user->email,
                'local_status' => $user->local_status,
                'last_authenticated_at' => optional($user->last_authenticated_at)?->toDateTimeString(),
                'created_at' => optional($user->created_at)?->toDateTimeString(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function companies(): array
    {
        return Company::query()
            ->latest()
            ->limit(25)
            ->get(['id', 'name', 'code', 'email', 'phone', 'is_active', 'created_at'])
            ->map(static fn (Company $company): array => [
                'id' => $company->id,
                'name' => $company->name,
                'code' => $company->code,
                'email' => $company->email,
                'phone' => $company->phone,
                'is_active' => $company->is_active,
                'created_at' => optional($company->created_at)?->toDateTimeString(),
            ])
            ->all();
    }
}
