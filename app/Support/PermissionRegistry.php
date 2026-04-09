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

            'companies.view',
            'companies.create',
            'companies.update',
            'companies.delete',

            'employees.view',
            'employees.create',
            'employees.update',
            'employees.delete',

            'users.view',
            'users.manage',

            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',

            'permissions.view',
            'permissions.create',
            'permissions.update',
            'permissions.delete',

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
            'companies.view',
            'companies.create',
            'companies.update',
            'companies.delete',
            'employees.view',
            'employees.create',
            'employees.update',
            'employees.delete',
            'users.view',
            'users.manage',
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',
            'permissions.view',
            'permissions.create',
            'permissions.update',
            'permissions.delete',
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
