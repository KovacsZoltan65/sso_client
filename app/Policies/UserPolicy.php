<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Eldönti, hogy a felhasználó listázhatja-e a felhasználókat.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    /**
     * Eldönti, hogy a felhasználó megtekinthet-e egy adott felhasználót.
     */
    public function view(User $user, User $targetUser): bool
    {
        return $user->can('users.view');
    }

    /**
     * Eldönti, hogy a felhasználó frissítheti-e a célfelhasználó helyi adatait.
     */
    public function update(User $user, User $targetUser): bool
    {
        return $user->can('users.manage');
    }
}
