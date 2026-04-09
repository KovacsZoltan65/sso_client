<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use App\Models\Permission;
use Tests\TestCase;

class CompaniesApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function companies_index_requires_view_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/companies')
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
    public function companies_index_returns_paginated_results_for_authorized_users(): void
    {
        $user = $this->userWithPermission('companies.view');
        Company::factory()->count(3)->create();

        $this->actingAs($user)
            ->getJson('/api/companies?per_page=2&page=1')
            ->assertOk()
            ->assertJsonPath('message', 'Companies retrieved successfully.')
            ->assertJsonPath('meta.pagination.per_page', 2)
            ->assertJsonPath('meta.pagination.total', 3)
            ->assertJsonCount(2, 'data.items');
    }

    #[Test]
    public function companies_store_creates_a_company_with_valid_input(): void
    {
        $user = $this->userWithPermission('companies.create');

        $payload = [
            'name' => 'Acme Kft.',
            'code' => 'ACME',
            'email' => 'info@acme.test',
            'phone' => '+36 1 555 0000',
            'address' => 'Budapest',
            'is_active' => true,
        ];

        $this->actingAs($user)
            ->postJson('/api/companies', $payload)
            ->assertCreated()
            ->assertJsonPath('data.company.name', 'Acme Kft.')
            ->assertJsonPath('data.company.code', 'ACME');

        $this->assertDatabaseHas('companies', [
            'name' => 'Acme Kft.',
            'code' => 'ACME',
            'is_active' => true,
        ]);

        $company = Company::query()->where('code', 'ACME')->firstOrFail();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.admin.company',
            'subject_type' => Company::class,
            'subject_id' => $company->id,
            'description' => 'created',
        ]);
    }

    #[Test]
    public function companies_store_returns_validation_errors_for_invalid_input(): void
    {
        $user = $this->userWithPermission('companies.create');
        Company::factory()->create(['code' => 'ACME']);

        $this->actingAs($user)
            ->postJson('/api/companies', [
                'name' => '',
                'code' => 'ACME',
                'email' => 'not-an-email',
                'is_active' => 'maybe',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure([
                'errors' => ['name', 'code', 'email', 'is_active'],
            ]);
    }

    #[Test]
    public function companies_update_modifies_the_selected_company(): void
    {
        $user = $this->userWithPermission('companies.update');
        $company = Company::factory()->create([
            'name' => 'Acme Kft.',
            'code' => 'ACME',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->putJson("/api/companies/{$company->id}", [
                'name' => 'Acme Zrt.',
                'code' => 'ACME',
                'email' => 'hello@acme.test',
                'phone' => '12345',
                'address' => 'Gyor',
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.company.name', 'Acme Zrt.')
            ->assertJsonPath('data.company.is_active', false);

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'name' => 'Acme Zrt.',
            'is_active' => false,
        ]);

        $activity = Activity::query()
            ->where('log_name', 'client.admin.company')
            ->where('subject_type', Company::class)
            ->where('subject_id', $company->id)
            ->where('description', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame($user->id, $activity->causer_id);
        $this->assertSame(User::class, $activity->causer_type);
        $this->assertSame('Acme Zrt.', $activity->properties['attributes']['name']);
        $this->assertSame('Acme Kft.', $activity->properties['old']['name']);
        $this->assertSame(false, $activity->properties['attributes']['is_active']);
        $this->assertSame(true, $activity->properties['old']['is_active']);
        $this->assertSame('hello@acme.test', $activity->properties['attributes']['email']);
        $this->assertArrayNotHasKey('code', $activity->properties['old']);
        $this->assertArrayNotHasKey('code', $activity->properties['attributes']);
    }

    #[Test]
    public function companies_update_does_not_create_an_audit_entry_when_nothing_changes(): void
    {
        $user = $this->userWithPermission('companies.update');
        $company = Company::factory()->create([
            'name' => 'Acme Kft.',
            'code' => 'ACME',
            'email' => 'hello@acme.test',
            'phone' => '12345',
            'address' => 'Gyor',
            'is_active' => false,
        ]);

        $existingLogs = Activity::query()
            ->where('log_name', 'client.admin.company')
            ->where('subject_type', Company::class)
            ->where('subject_id', $company->id)
            ->where('description', 'updated')
            ->count();

        $this->actingAs($user)
            ->putJson("/api/companies/{$company->id}", [
                'name' => 'Acme Kft.',
                'code' => 'ACME',
                'email' => 'hello@acme.test',
                'phone' => '12345',
                'address' => 'Gyor',
                'is_active' => false,
            ])
            ->assertOk();

        $this->assertSame(
            $existingLogs,
            Activity::query()
                ->where('log_name', 'client.admin.company')
                ->where('subject_type', Company::class)
                ->where('subject_id', $company->id)
                ->where('description', 'updated')
                ->count(),
        );
    }

    #[Test]
    public function companies_delete_removes_the_selected_company(): void
    {
        $user = $this->userWithPermission('companies.delete');
        $company = Company::factory()->create();

        $this->actingAs($user)
            ->deleteJson("/api/companies/{$company->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Company deleted successfully.');

        $this->assertDatabaseMissing('companies', [
            'id' => $company->id,
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.admin.company',
            'subject_type' => Company::class,
            'subject_id' => $company->id,
            'description' => 'deleted',
        ]);
    }

    #[Test]
    public function companies_index_supports_search(): void
    {
        $user = $this->userWithPermission('companies.view');
        Company::factory()->create(['name' => 'Alpha Kft.', 'code' => 'ALPHA', 'email' => 'alpha@test.local']);
        Company::factory()->create(['name' => 'Beta Kft.', 'code' => 'BETA', 'email' => 'beta@test.local']);

        $this->actingAs($user)
            ->getJson('/api/companies?search=beta')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Beta Kft.');
    }

    #[Test]
    public function companies_index_supports_pagination(): void
    {
        $user = $this->userWithPermission('companies.view');
        Company::factory()->count(5)->create();

        $this->actingAs($user)
            ->getJson('/api/companies?per_page=2&page=2')
            ->assertOk()
            ->assertJsonPath('meta.pagination.current_page', 2)
            ->assertJsonPath('meta.pagination.per_page', 2)
            ->assertJsonCount(2, 'data.items');
    }

    #[Test]
    public function companies_index_supports_status_filtering(): void
    {
        $user = $this->userWithPermission('companies.view');
        Company::factory()->create(['name' => 'Active Co', 'is_active' => true]);
        Company::factory()->create(['name' => 'Inactive Co', 'is_active' => false]);

        $this->actingAs($user)
            ->getJson('/api/companies?is_active=0')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Inactive Co');
    }

    private function userWithPermission(string $permission): User
    {
        Permission::findOrCreate($permission, 'web');

        $user = User::factory()->create();
        $user->givePermissionTo($permission);

        return $user;
    }
}
