<?php

namespace Database\Seeders;

use App\Support\PermissionRegistry;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $allPermissions = collect(PermissionRegistry::all())->map(function (string $permission) {
            Permission::findOrCreate($permission, 'web');
            return $permission;
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $superadmin = Role::findOrCreate('superadmin', 'web');
        $admin = Role::findOrCreate('admin', 'web');
        $user = Role::findOrCreate('user', 'web');

        $superadmin->syncPermissions(Permission::query()->whereIn('name', $allPermissions)->get());
        $admin->syncPermissions(Permission::query()->whereIn('name', PermissionRegistry::admin())->get());
        $user->syncPermissions(Permission::query()->whereIn('name', PermissionRegistry::user())->get());
    }
}
