<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleAndPermissionSeeder::class);

        $superAdmin = User::query()->updateOrCreate(
            ['email' => 'superadmin@sso-client.test'],
            [
                'name' => 'SSO Client Superadmin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@sso-client.test'],
            [
                'name' => 'SSO Client Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $user = User::query()->updateOrCreate(
            ['email' => 'user@sso-client.test'],
            [
                'name' => 'SSO Client User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $superAdmin->syncRoles(['superadmin']);
        $admin->syncRoles(['admin']);
        $user->syncRoles(['user']);
    }
}
