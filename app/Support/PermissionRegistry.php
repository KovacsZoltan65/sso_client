<?php

namespace App\Support;

class PermissionRegistry
{
    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            'dashboard.view',
            'profile.manage',
            'account.view',
            'users.view',
            'users.manage',
            'roles.view',
            'roles.manage',
            'permissions.view',
            'permissions.manage',
            'sso-status.view',
            'audit-logs.view',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function admin(): array
    {
        return [
            'dashboard.view',
            'profile.manage',
            'account.view',
            'users.view',
            'roles.view',
            'permissions.view',
            'sso-status.view',
            'audit-logs.view',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function user(): array
    {
        return [
            'dashboard.view',
            'profile.manage',
            'account.view',
            'sso-status.view',
        ];
    }
}
