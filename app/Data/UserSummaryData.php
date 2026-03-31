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
        public string $sessionMode,
        public array $roles,
        public array $permissions,
        public array $capabilities,
    ) {
    }

    /**
     * Admin listához és API válaszhoz használható felhasználói összegző DTO létrehozása.
     */
    public static function fromModel(User $user, string $sessionMode = 'sso', array $capabilities = []): self
    {
        $isFallbackSession = $sessionMode === 'local_fallback';

        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            sessionMode: $sessionMode,
            roles: $isFallbackSession ? ['local_fallback'] : $user->getRoleNames()->values()->all(),
            permissions: $isFallbackSession ? [] : $user->getAllPermissions()->pluck('name')->values()->all(),
            capabilities: $capabilities,
        );
    }
}
