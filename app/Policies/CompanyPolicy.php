<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    /**
     * Eldönti, hogy a felhasználó listázhatja-e a cégeket.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('companies.view');
    }

    /**
     * Eldönti, hogy a felhasználó megtekinthet-e egy adott céget.
     */
    public function view(User $user, Company $company): bool
    {
        return $user->can('companies.view');
    }

    /**
     * Eldönti, hogy a felhasználó létrehozhat-e új céget.
     */
    public function create(User $user): bool
    {
        return $user->can('companies.create');
    }

    /**
     * Eldönti, hogy a felhasználó szerkeszthet-e egy adott céget.
     */
    public function update(User $user, Company $company): bool
    {
        return $user->can('companies.update');
    }

    /**
     * Eldönti, hogy a felhasználó törölhet-e egy adott céget.
     */
    public function delete(User $user, Company $company): bool
    {
        return $user->can('companies.delete');
    }
}
