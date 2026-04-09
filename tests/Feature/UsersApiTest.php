<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UsersApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function users_page_is_available_for_authorized_users(): void
    {
        $user = $this->userWithPermission('users.view');

        $this->actingAs($user)
            ->get('/users')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Users/Index')
                ->where('permissions.view', true)
                ->where('permissions.manage', false)
                ->where('usersApi.endpoints.index', route('api.users.index'))
            );
    }

    #[Test]
    public function users_index_requires_view_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/users')
            ->assertForbidden()
            ->assertJson([
                'message' => 'Forbidden.',
            ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.api',
            'event' => 'client_api.request.forbidden',
        ]);
    }

    #[Test]
    public function users_index_returns_projection_fields_and_supports_filters(): void
    {
        $user = $this->userWithPermission('users.view');

        User::factory()->create([
            'sso_user_id' => 'server-100',
            'name' => 'Alpha User',
            'email' => 'alpha@example.test',
            'local_status' => 'active',
        ]);
        User::factory()->create([
            'sso_user_id' => 'server-200',
            'name' => 'Beta User',
            'email' => 'beta@example.test',
            'local_status' => 'inactive',
        ]);

        $this->actingAs($user)
            ->getJson('/api/users?global=beta&local_status=inactive&sort_field=sso_user_id&sort_order=asc')
            ->assertOk()
            ->assertJsonPath('message', 'Users retrieved successfully.')
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.sso_user_id', 'server-200')
            ->assertJsonPath('data.items.0.local_status', 'inactive')
            ->assertJsonPath('meta.filters.global', 'beta')
            ->assertJsonPath('meta.filters.local_status', 'inactive');
    }

    #[Test]
    public function users_update_only_modifies_local_metadata(): void
    {
        $manager = $this->userWithPermission('users.manage');
        $target = User::factory()->create([
            'sso_user_id' => 'server-777',
            'name' => 'Remote Name',
            'email' => 'remote@example.test',
            'local_status' => 'active',
            'notes' => null,
        ]);

        $this->actingAs($manager)
            ->putJson("/api/users/{$target->id}", [
                'local_status' => 'inactive',
                'notes' => 'Needs follow-up review.',
                'name' => 'Tampered',
                'email' => 'tampered@example.test',
            ])
            ->assertOk()
            ->assertJsonPath('data.user.local_status', 'inactive')
            ->assertJsonPath('data.user.notes', 'Needs follow-up review.')
            ->assertJsonPath('data.user.name', 'Remote Name')
            ->assertJsonPath('data.user.email', 'remote@example.test');

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'sso_user_id' => 'server-777',
            'name' => 'Remote Name',
            'email' => 'remote@example.test',
            'local_status' => 'inactive',
            'notes' => 'Needs follow-up review.',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.admin.user',
            'event' => 'client_admin.user.updated',
        ]);

        $activity = Activity::query()
            ->where('event', 'client_admin.user.updated')
            ->latest()
            ->firstOrFail();

        $this->assertArrayNotHasKey('access_token', $activity->properties->toArray());
    }

    #[Test]
    public function users_update_does_not_create_an_audit_entry_when_local_metadata_is_unchanged(): void
    {
        $manager = $this->userWithPermission('users.manage');
        $target = User::factory()->create([
            'local_status' => 'inactive',
            'notes' => 'Needs follow-up review.',
        ]);

        $existingLogs = Activity::query()
            ->where('log_name', 'client.admin.user')
            ->where('event', 'client_admin.user.updated')
            ->where('subject_type', User::class)
            ->where('subject_id', $target->id)
            ->count();

        $this->actingAs($manager)
            ->putJson("/api/users/{$target->id}", [
                'local_status' => 'inactive',
                'notes' => 'Needs follow-up review.',
            ])
            ->assertOk();

        $this->assertSame(
            $existingLogs,
            Activity::query()
                ->where('log_name', 'client.admin.user')
                ->where('event', 'client_admin.user.updated')
                ->where('subject_type', User::class)
                ->where('subject_id', $target->id)
                ->count(),
        );
    }

    #[Test]
    public function users_update_requires_manage_permission(): void
    {
        $viewer = $this->userWithPermission('users.view');
        $target = User::factory()->create();

        $this->actingAs($viewer)
            ->putJson("/api/users/{$target->id}", [
                'local_status' => 'inactive',
                'notes' => 'Blocked',
            ])
            ->assertForbidden()
            ->assertJson([
                'message' => 'Forbidden.',
            ]);
    }

    #[Test]
    public function users_update_validates_local_metadata_payload(): void
    {
        $manager = $this->userWithPermission('users.manage');
        $target = User::factory()->create();

        $this->actingAs($manager)
            ->putJson("/api/users/{$target->id}", [
                'local_status' => 'archived',
                'notes' => str_repeat('x', 2001),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure([
                'errors' => ['local_status', 'notes'],
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
