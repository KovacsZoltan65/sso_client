<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Role;

class RolePolicy
{
    /**
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return $user->can('roles.view');
    }

    /**
     * @param User $user
     * @param Role $role
     * @return bool
     */
    public function view(User $user, Role $role): bool
    {
        return $user->can('roles.view');
    }

    /**
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->can('roles.create');
    }

    /**
     * @param User $user
     * @param Role $role
     * @return bool
     */
    public function update(User $user, Role $role): bool
    {
        return $user->can('roles.update');
    }

    /**
     * @param User $user
     * @param Role $role
     * @return bool
     */
    public function delete(User $user, Role $role): bool
    {
        return $user->can('roles.delete');
    }
}
