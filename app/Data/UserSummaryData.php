<?php

namespace App\Data;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
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

    /**
     * Admin listához és API válaszhoz használható felhasználói összegző DTO létrehozása.
     */
    public static function fromModel(User $user): self
    {
        $permissionTablesAvailable = self::permissionTablesAvailable();

        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            roles: $permissionTablesAvailable ? $user->getRoleNames()->values()->all() : [],
            permissions: $permissionTablesAvailable ? $user->getAllPermissions()->pluck('name')->values()->all() : [],
        );
    }

    private static function permissionTablesAvailable(): bool
    {
        $tableNames = config('permission.table_names', []);

        foreach (['roles', 'permissions', 'model_has_roles', 'model_has_permissions'] as $tableKey) {
            $tableName = $tableNames[$tableKey] ?? null;

            if (! is_string($tableName) || $tableName === '' || ! Schema::hasTable($tableName)) {
                return false;
            }
        }

        return true;
    }
}
