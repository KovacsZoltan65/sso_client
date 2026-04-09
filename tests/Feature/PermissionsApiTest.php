<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionsApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function permissions_page_requires_view_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/dashboard')
            ->get('/permissions')
            ->assertRedirect('/dashboard')
            ->assertSessionHas('error', 'Nincs jogosultsagod a kert oldal megtekintesehez.');
    }

    #[Test]
    public function permissions_page_is_available_for_authorized_users(): void
    {
        $user = $this->userWithPermission('permissions.view');

        $this->actingAs($user)
            ->get('/permissions')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Permissions/Index')
                ->where('permissions.view', true)
            );
    }

    #[Test]
    public function permissions_index_requires_view_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/permissions')
            ->assertForbidden()
            ->assertJson([
                'message' => 'Forbidden.',
            ]);
    }

    #[Test]
    public function permissions_index_returns_paginated_results_for_authorized_users(): void
    {
        $user = $this->userWithPermission('permissions.view');
        Permission::findOrCreate('companies.view', 'web');
        Permission::findOrCreate('employees.view', 'web');

        $this->actingAs($user)
            ->getJson('/api/permissions?per_page=1&page=1')
            ->assertOk()
            ->assertJsonPath('message', 'Permissions retrieved successfully.')
            ->assertJsonPath('meta.pagination.per_page', 1)
            ->assertJsonPath('meta.pagination.total', 3)
            ->assertJsonCount(1, 'data.items');
    }

    #[Test]
    public function permissions_store_creates_a_permission_with_valid_input(): void
    {
        $user = $this->userWithPermission('permissions.create');

        $this->actingAs($user)
            ->postJson('/api/permissions', [
                'name' => 'roles.assign',
                'guard_name' => 'web',
            ])
            ->assertCreated()
            ->assertJsonPath('data.permission.name', 'roles.assign');

        $this->assertDatabaseHas(config('permission.table_names.permissions'), [
            'name' => 'roles.assign',
            'guard_name' => 'web',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.admin.permission',
            'event' => 'client_admin.permission.created',
        ]);
    }

    #[Test]
    public function permissions_store_returns_validation_errors_for_invalid_input(): void
    {
        $user = $this->userWithPermission('permissions.create');
        Permission::findOrCreate('roles.assign', 'web');

        $this->actingAs($user)
            ->postJson('/api/permissions', [
                'name' => 'roles.assign',
                'guard_name' => 'web',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function permissions_update_modifies_the_selected_permission(): void
    {
        $user = $this->userWithPermission('permissions.update');
        $permission = Permission::findOrCreate('roles.assign', 'web');

        $this->actingAs($user)
            ->putJson("/api/permissions/{$permission->id}", [
                'name' => 'roles.attach',
                'guard_name' => 'web',
            ])
            ->assertOk()
            ->assertJsonPath('data.permission.name', 'roles.attach');

        $this->assertDatabaseHas(config('permission.table_names.permissions'), [
            'id' => $permission->id,
            'name' => 'roles.attach',
        ]);
    }

    #[Test]
    public function permissions_delete_removes_the_selected_permission_and_detaches_roles(): void
    {
        $user = $this->userWithPermission('permissions.delete');
        $permission = Permission::findOrCreate('roles.assign', 'web');
        $role = Role::findOrCreate('admin', 'web');
        $role->givePermissionTo($permission);

        $this->actingAs($user)
            ->deleteJson("/api/permissions/{$permission->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Permission deleted successfully.');

        $this->assertDatabaseMissing(config('permission.table_names.permissions'), [
            'id' => $permission->id,
        ]);

        $this->assertCount(0, $role->fresh()->permissions);
    }

    private function userWithPermission(string $permission): User
    {
        Permission::findOrCreate($permission, 'web');

        $user = User::factory()->create();
        $user->givePermissionTo($permission);

        return $user;
    }
}



