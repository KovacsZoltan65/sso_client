<?php

namespace App\Data;

use App\Models\User;
use Spatie\LaravelData\Data;

class UserSummaryData extends Data
{
    /**
     * @param  array<int, string>  $roles
     * @param  array<int, string>  $permissions
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public array $roles,
        public array $permissions,
    ) {
    }

    public static function fromModel(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            roles: $user->getRoleNames()->values()->all(),
            permissions: $user->getAllPermissions()->pluck('name')->values()->all(),
        );
    }
}
