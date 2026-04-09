<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Eldönti, hogy a felhasználó listázhatja-e a felhasználókat.
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    /**
     * Eldönti, hogy a felhasználó megtekinthet-e egy adott felhasználót.
     * @param User $user
     * @param User $targetUser
     * @return bool
     */
    public function view(User $user, User $targetUser): bool
    {
        return $user->can('users.view');
    }

    /**
     * Eldönti, hogy a felhasználó frissítheti-e a célfelhasználó helyi adatait.
     * @param User $user
     * @param User $targetUser
     * @return bool
     */
    public function update(User $user, User $targetUser): bool
    {
        return $user->can('users.manage');
    }
}
