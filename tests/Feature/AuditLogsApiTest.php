<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AuditLogsApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function audit_logs_index_requires_view_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/audit-logs')
            ->assertForbidden()
            ->assertJson([
                'message' => 'Forbidden.',
            ]);
    }

    #[Test]
    public function audit_logs_index_returns_paginated_results_for_authorized_users(): void
    {
        $viewer = $this->userWithPermission('audit-logs.view');
        $company = Company::factory()->create();
        $causer = User::factory()->create();

        $this->createActivity('client_admin.company.created', 'Alpha company created.', $company, $causer);
        $this->createActivity('client_admin.company.updated', 'Alpha company updated.', $company, $causer);
        $this->createActivity('client_admin.company.deleted', 'Alpha company deleted.', $company, $causer);

        $this->actingAs($viewer)
            ->getJson('/api/audit-logs?per_page=2&page=1')
            ->assertOk()
            ->assertJsonPath('message', 'Audit logs retrieved successfully.')
            ->assertJsonPath('meta.pagination.per_page', 2)
            ->assertJsonPath('meta.pagination.total', 3)
            ->assertJsonCount(2, 'data.items');
    }

    #[Test]
    public function audit_logs_index_supports_global_and_field_filters(): void
    {
        $viewer = $this->userWithPermission('audit-logs.view');
        $company = Company::factory()->create(['name' => 'Alpha Kft.']);
        $causerA = User::factory()->create(['name' => 'Alice Auditor', 'email' => 'alice@example.test']);
        $causerB = User::factory()->create(['name' => 'Bob Operator', 'email' => 'bob@example.test']);

        $this->createActivity('client_admin.company.created', 'Alpha company created.', $company, $causerA);
        $this->createActivity('client.account.updated', 'User profile adjusted.', $causerB, $causerB, AuditLogService::LOG_CLIENT_ACCOUNT);

        $this->actingAs($viewer)
            ->getJson('/api/audit-logs?global=Alice')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.causer.name', 'Alice Auditor');

        $this->actingAs($viewer)
            ->getJson('/api/audit-logs?event=created')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.event', 'client_admin.company.created');

        $this->actingAs($viewer)
            ->getJson('/api/audit-logs?user_id=' . $causerB->id)
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.causer.id', $causerB->id);

        $this->actingAs($viewer)
            ->getJson('/api/audit-logs?subject_type=Company')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.subject_type', 'Company');
    }

    #[Test]
    public function audit_logs_index_supports_sorting(): void
    {
        $viewer = $this->userWithPermission('audit-logs.view');
        $company = Company::factory()->create();
        $causer = User::factory()->create();

        $first = $this->createActivity('client_admin.company.created', 'First activity.', $company, $causer);
        $second = $this->createActivity('client_admin.company.updated', 'Second activity.', $company, $causer);

        $this->actingAs($viewer)
            ->getJson('/api/audit-logs?sort_field=id&sort_order=asc')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', min($first->id, $second->id));
    }

    #[Test]
    public function audit_logs_show_returns_the_detail_payload_for_authorized_users(): void
    {
        $viewer = $this->userWithPermission('audit-logs.view');
        $company = Company::factory()->create(['name' => 'Alpha Kft.']);
        $causer = User::factory()->create(['name' => 'Alice Auditor', 'email' => 'alice@example.test']);
        $activity = $this->createActivity(
            'client_admin.company.updated',
            'Alpha company updated.',
            $company,
            $causer,
            AuditLogService::LOG_CLIENT_ADMIN_COMPANY,
            [
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Vitest Browser',
                'route' => 'api.audit-logs.show',
                'status' => 'active',
                'result' => 'success',
                'updated_fields' => ['name'],
            ],
        );

        $this->actingAs($viewer)
            ->getJson('/api/audit-logs/' . $activity->id)
            ->assertOk()
            ->assertJsonPath('message', 'Audit log retrieved successfully.')
            ->assertJsonPath('data.audit_log.id', $activity->id)
            ->assertJsonPath('data.audit_log.log_name', AuditLogService::LOG_CLIENT_ADMIN_COMPANY)
            ->assertJsonPath('data.audit_log.causer.name', 'Alice Auditor')
            ->assertJsonPath('data.audit_log.subject_type', 'Company')
            ->assertJsonPath('data.audit_log.context.ip_address', '127.0.0.1')
            ->assertJsonPath('data.audit_log.properties.updated_fields.0', 'name');
    }

    #[Test]
    public function audit_logs_show_requires_view_permission(): void
    {
        $user = User::factory()->create();
        $activity = $this->createActivity('client_admin.company.created', 'Alpha company created.');

        $this->actingAs($user)
            ->getJson('/api/audit-logs/' . $activity->id)
            ->assertForbidden()
            ->assertJson([
                'message' => 'Forbidden.',
            ]);
    }

    #[Test]
    public function audit_logs_show_returns_not_found_for_missing_records(): void
    {
        $viewer = $this->userWithPermission('audit-logs.view');

        $this->actingAs($viewer)
            ->getJson('/api/audit-logs/999999')
            ->assertNotFound();
    }

    private function userWithPermission(string $permission): User
    {
        Permission::findOrCreate($permission, 'web');

        $user = User::factory()->create();
        $user->givePermissionTo($permission);

        return $user;
    }

    private function createActivity(
        string $event,
        string $description,
        mixed $subject = null,
        ?User $causer = null,
        string $logName = AuditLogService::LOG_CLIENT_ADMIN_COMPANY,
        array $properties = [],
    ): mixed {
        $entry = activity($logName)->event($event);

        if ($subject !== null) {
            $entry->performedOn($subject);
        }

        if ($causer !== null) {
            $entry->causedBy($causer);
        }

        if ($properties !== []) {
            $entry->withProperties($properties);
        }

        $entry->log($description);

        return \Spatie\Activitylog\Models\Activity::query()->latest('id')->firstOrFail();
    }
}
