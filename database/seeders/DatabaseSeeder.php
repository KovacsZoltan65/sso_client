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

        $seedUsers = [
            [
                'email' => 'superadmin@sso-client.test',
                'name' => 'SSO Client Superadmin',
                'role' => 'superadmin',
            ],
            [
                'email' => 'admin@sso-client.test',
                'name' => 'SSO Client Admin',
                'role' => 'admin',
            ],
            [
                'email' => 'user@sso-client.test',
                'name' => 'SSO Client User',
                'role' => 'user',
            ],
            [
                'email' => 'superadmin@sso.test',
                'name' => 'SSO Superadmin',
                'role' => 'superadmin',
            ],
            [
                'email' => 'admin@sso.test',
                'name' => 'SSO Admin',
                'role' => 'admin',
            ],
            [
                'email' => 'user@sso.test',
                'name' => 'SSO User',
                'role' => 'user',
            ],
        ];

        foreach ($seedUsers as $seedUser) {
            $user = User::query()->updateOrCreate(
                ['email' => $seedUser['email']],
                [
                    'name' => $seedUser['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ],
            );

            $user->syncRoles([$seedUser['role']]);
        }
    }
}
