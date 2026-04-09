<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function roles_page_requires_view_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/dashboard')
            ->get('/roles')
            ->assertRedirect('/dashboard')
            ->assertSessionHas('error', 'Nincs jogosultsagod a kert oldal megtekintesehez.');
    }

    #[Test]
    public function roles_page_is_available_for_authorized_users(): void
    {
        $user = $this->userWithPermission('roles.view');
        Permission::findOrCreate('companies.view', 'web');

        $this->actingAs($user)
            ->get('/roles')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Roles/Index')
                ->where('permissions.view', true)
                ->has('permissionOptions')
            );
    }

    #[Test]
    public function roles_index_requires_view_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/roles')
            ->assertForbidden()
            ->assertJson([
                'message' => 'Forbidden.',
            ]);
    }

    #[Test]
    public function roles_index_returns_paginated_results_for_authorized_users(): void
    {
        $user = $this->userWithPermission('roles.view');
        $permission = Permission::findOrCreate('companies.view', 'web');

        $role = Role::findOrCreate('editor', 'web');
        $role->syncPermissions([$permission]);
        Role::findOrCreate('auditor', 'web');

        $this->actingAs($user)
            ->getJson('/api/roles?per_page=1&page=1')
            ->assertOk()
            ->assertJsonPath('message', 'Roles retrieved successfully.')
            ->assertJsonPath('meta.pagination.per_page', 1)
            ->assertJsonPath('meta.pagination.total', 2)
            ->assertJsonCount(1, 'data.items');
    }

    #[Test]
    public function roles_store_creates_a_role_and_syncs_permissions(): void
    {
        $user = $this->userWithPermission('roles.create');
        $permissionA = Permission::findOrCreate('companies.view', 'web');
        $permissionB = Permission::findOrCreate('employees.view', 'web');

        $this->actingAs($user)
            ->postJson('/api/roles', [
                'name' => 'operator',
                'guard_name' => 'web',
                'permission_ids' => [$permissionA->id, $permissionB->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.role.name', 'operator')
            ->assertJsonPath('data.role.permissions_count', 2);

        $role = Role::query()->where('name', 'operator')->firstOrFail();

        $this->assertEqualsCanonicalizing(
            [$permissionA->id, $permissionB->id],
            $role->permissions()->pluck('id')->all(),
        );

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.admin.role',
            'event' => 'client_admin.role.created',
        ]);
    }

    #[Test]
    public function roles_store_returns_validation_errors_for_invalid_input(): void
    {
        $user = $this->userWithPermission('roles.create');
        Role::findOrCreate('operator', 'web');

        $this->actingAs($user)
            ->postJson('/api/roles', [
                'name' => 'operator',
                'guard_name' => 'web',
                'permission_ids' => [9999],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonValidationErrors(['name', 'permission_ids.0']);
    }

    #[Test]
    public function roles_update_modifies_the_selected_role_and_resyncs_permissions(): void
    {
        $user = $this->userWithPermission('roles.update');
        $permissionA = Permission::findOrCreate('companies.view', 'web');
        $permissionB = Permission::findOrCreate('employees.view', 'web');
        $role = Role::findOrCreate('operator', 'web');
        $role->syncPermissions([$permissionA]);

        $this->actingAs($user)
            ->putJson("/api/roles/{$role->id}", [
                'name' => 'team-lead',
                'guard_name' => 'web',
                'permission_ids' => [$permissionB->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.role.name', 'team-lead')
            ->assertJsonPath('data.role.permissions_count', 1);

        $role->refresh();

        $this->assertSame('team-lead', $role->name);
        $this->assertSame([$permissionB->id], $role->permissions()->pluck('id')->all());
    }

    #[Test]
    public function roles_update_does_not_create_an_audit_entry_when_identity_and_permissions_are_unchanged(): void
    {
        $user = $this->userWithPermission('roles.update');
        $permission = Permission::findOrCreate('companies.view', 'web');
        $role = Role::findOrCreate('operator', 'web');
        $role->syncPermissions([$permission]);

        $existingLogs = Activity::query()
            ->where('log_name', 'client.admin.role')
            ->where('event', 'client_admin.role.updated')
            ->where('subject_type', Role::class)
            ->where('subject_id', $role->id)
            ->count();

        $this->actingAs($user)
            ->putJson("/api/roles/{$role->id}", [
                'name' => 'operator',
                'guard_name' => 'web',
                'permission_ids' => [$permission->id],
            ])
            ->assertOk();

        $this->assertSame(
            $existingLogs,
            Activity::query()
                ->where('log_name', 'client.admin.role')
                ->where('event', 'client_admin.role.updated')
                ->where('subject_type', Role::class)
                ->where('subject_id', $role->id)
                ->count(),
        );
    }

    #[Test]
    public function protected_roles_cannot_be_deleted(): void
    {
        $user = $this->userWithPermission('roles.delete');
        $role = Role::findOrCreate('admin', 'web');

        $this->actingAs($user)
            ->deleteJson("/api/roles/{$role->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'A(z) admin vedett rendszer-szerepkor, ezert nem torolheto.');

        $this->assertDatabaseHas(config('permission.table_names.roles'), [
            'id' => $role->id,
            'name' => 'admin',
        ]);
    }

    #[Test]
    public function protected_roles_cannot_be_renamed(): void
    {
        $user = $this->userWithPermission('roles.update');
        $permission = Permission::findOrCreate('companies.view', 'web');
        $role = Role::findOrCreate('admin', 'web');
        $role->syncPermissions([$permission]);

        $this->actingAs($user)
            ->putJson("/api/roles/{$role->id}", [
                'name' => 'admin-renamed',
                'guard_name' => 'web',
                'permission_ids' => [$permission->id],
            ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'A(z) admin vedett rendszer-szerepkor neve vagy guardja nem modositheto.');

        $this->assertDatabaseHas(config('permission.table_names.roles'), [
            'id' => $role->id,
            'name' => 'admin',
        ]);
    }

    #[Test]
    public function protected_roles_can_still_sync_permissions_when_identity_is_unchanged(): void
    {
        $user = $this->userWithPermission('roles.update');
        $permissionA = Permission::findOrCreate('companies.view', 'web');
        $permissionB = Permission::findOrCreate('employees.view', 'web');
        $role = Role::findOrCreate('admin', 'web');
        $role->syncPermissions([$permissionA]);

        $this->actingAs($user)
            ->putJson("/api/roles/{$role->id}", [
                'name' => 'admin',
                'guard_name' => 'web',
                'permission_ids' => [$permissionB->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.role.name', 'admin')
            ->assertJsonPath('data.role.permissions_count', 1);

        $this->assertSame([$permissionB->id], $role->fresh()->permissions()->pluck('id')->all());
    }

    #[Test]
    public function roles_update_requires_update_permission(): void
    {
        $user = User::factory()->create();
        $role = Role::findOrCreate('operator', 'web');

        $this->actingAs($user)
            ->putJson("/api/roles/{$role->id}", [
                'name' => 'operator-updated',
                'guard_name' => 'web',
                'permission_ids' => [],
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');
    }

    #[Test]
    public function roles_delete_requires_delete_permission(): void
    {
        $user = User::factory()->create();
        $role = Role::findOrCreate('operator', 'web');

        $this->actingAs($user)
            ->deleteJson("/api/roles/{$role->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');
    }

    #[Test]
    public function roles_delete_removes_the_selected_role(): void
    {
        $user = $this->userWithPermission('roles.delete');
        $role = Role::findOrCreate('operator', 'web');

        $this->actingAs($user)
            ->deleteJson("/api/roles/{$role->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Role deleted successfully.');

        $this->assertDatabaseMissing(config('permission.table_names.roles'), [
            'id' => $role->id,
        ]);
    }

    private function userWithPermission(string $permission): User
    {
        Permission::findOrCreate($permission, 'web');

        $user = User::factory()->create();
        $user->givePermissionTo($permission);

        return $user;
    }
}
