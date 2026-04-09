<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    /**
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return $user->can('employees.view');
    }

    /**
     * @param User $user
     * @param Employee $employee
     * @return bool
     */
    public function view(User $user, Employee $employee): bool
    {
        return $user->can('employees.view');
    }

    /**
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->can('employees.create');
    }

    /**
     * @param User $user
     * @param Employee $employee
     * @return bool
     */
    public function update(User $user, Employee $employee): bool
    {
        return $user->can('employees.update');
    }

    /**
     * @param User $user
     * @param Employee $employee
     * @return bool
     */
    public function delete(User $user, Employee $employee): bool
    {
        return $user->can('employees.delete');
    }
}