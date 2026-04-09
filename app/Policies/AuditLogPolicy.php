<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Activitylog\Models\Activity;

class AuditLogPolicy
{
    /**
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return $user->can('audit-logs.view');
    }

    /**
     * @param User $user
     * @param Activity $activity
     * @return bool
     */
    public function view(User $user, Activity $activity): bool
    {
        return $user->can('audit-logs.view');
    }
}
