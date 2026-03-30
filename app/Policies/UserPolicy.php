<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function view(User $user, User $targetUser): bool
    {
        return $user->can('users.view');
    }

    public function update(User $user, User $targetUser): bool
    {
        return $user->can('users.manage');
    }
}
