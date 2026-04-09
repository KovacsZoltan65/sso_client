<?php

namespace App\Support;

use App\Models\Permission;
use App\Models\Role;

class ProtectedAuthorizationArtifacts
{
    private const LOCAL_GUARD = 'web';

    /**
     * @return array<int, string>
     */
    public static function protectedRoleNames(): array
    {
        return [
            'superadmin',
            'admin',
            'user',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function protectedPermissionNames(): array
    {
        return PermissionRegistry::all();
    }

    public static function isProtectedRole(Role|string|null $role, ?string $guardName = null): bool
    {
        if ($role instanceof Role) {
            return self::isProtectedRoleName($role->name, $role->guard_name);
        }

        return self::isProtectedRoleName($role, $guardName);
    }

    /**
     * @param Permission|string|null $permission
     * @param mixed $guardName
     * @return bool
     */
    public static function isProtectedPermission(Permission|string|null $permission, ?string $guardName = null): bool
    {
        if ($permission instanceof Permission) {
            return self::isProtectedPermissionName($permission->name, $permission->guard_name);
        }

        return self::isProtectedPermissionName($permission, $guardName);
    }

    /**
     * @param Role $role
     * @param array $attributes
     * @return bool
     */
    public static function blocksProtectedRoleIdentityUpdate(Role $role, array $attributes): bool
    {
        if (! self::isProtectedRole($role)) {
            return false;
        }

        $nextName = (string) ($attributes['name'] ?? $role->name);
        $nextGuard = (string) ($attributes['guard_name'] ?? $role->guard_name);

        return $nextName !== $role->name || $nextGuard !== $role->guard_name;
    }

    /**
     * @param Permission $permission
     * @param array $attributes
     * @return bool
     */
    public static function blocksProtectedPermissionIdentityUpdate(Permission $permission, array $attributes): bool
    {
        if (! self::isProtectedPermission($permission)) {
            return false;
        }

        $nextName = (string) ($attributes['name'] ?? $permission->name);
        $nextGuard = (string) ($attributes['guard_name'] ?? $permission->guard_name);

        return $nextName !== $permission->name || $nextGuard !== $permission->guard_name;
    }

    /**
     * @return string
     */
    public static function protectionLabel(): string
    {
        return 'Rendszer';
    }

    /**
     * @param mixed $name
     * @param mixed $guardName
     * @return bool
     */
    private static function isProtectedRoleName(?string $name, ?string $guardName): bool
    {
        if (! self::isLocalGuard($guardName) || blank($name)) {
            return false;
        }

        return in_array(mb_strtolower((string) $name), self::protectedRoleNames(), true);
    }

    /**
     * @param mixed $name
     * @param mixed $guardName
     * @return bool
     */
    private static function isProtectedPermissionName(?string $name, ?string $guardName): bool
    {
        if (! self::isLocalGuard($guardName) || blank($name)) {
            return false;
        }

        return in_array((string) $name, self::protectedPermissionNames(), true);
    }

    /**
     * @param mixed $guardName
     * @return bool
     */
    private static function isLocalGuard(?string $guardName): bool
    {
        return ($guardName ?? self::LOCAL_GUARD) === self::LOCAL_GUARD;
    }
}
