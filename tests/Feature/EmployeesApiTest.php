<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EmployeesApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function employee_update_creates_an_activity_log_entry_with_the_changed_fields(): void
    {
        $user = $this->userWithPermission('employees.update');
        $company = Company::factory()->create();
        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'employee_number' => 'EMP-001',
            'name' => 'Jane Doe',
            'email' => 'jane@example.test',
            'phone' => '+36 1 111 1111',
            'position' => 'Support',
            'is_active' => true,
        ]);

        $payload = [
            'company_id' => $company->id,
            'employee_number' => 'EMP-001',
            'name' => 'Jane Smith',
            'email' => 'jane.smith@example.test',
            'phone' => '+36 1 222 2222',
            'position' => 'Team Lead',
            'is_active' => false,
        ];

        $this->actingAs($user)
            ->putJson('/api/employees/' . $employee->id, $payload)
            ->assertOk()
            ->assertJsonPath('message', 'Employee updated successfully.')
            ->assertJsonPath('data.id', $employee->id)
            ->assertJsonPath('data.name', 'Jane Smith');

        $activity = Activity::query()
            ->where('log_name', 'client.admin.employee')
            ->where('subject_type', Employee::class)
            ->where('subject_id', $employee->id)
            ->where('description', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame($user->id, $activity->causer_id);
        $this->assertSame(User::class, $activity->causer_type);
        $this->assertSame('Jane Smith', $activity->properties['attributes']['name']);
        $this->assertSame('Jane Doe', $activity->properties['old']['name']);
        $this->assertSame('Team Lead', $activity->properties['attributes']['position']);
        $this->assertSame('Support', $activity->properties['old']['position']);
        $this->assertSame(false, $activity->properties['attributes']['is_active']);
        $this->assertSame(true, $activity->properties['old']['is_active']);
        $this->assertArrayNotHasKey('company_id', $activity->properties['old']);
        $this->assertArrayNotHasKey('company_id', $activity->properties['attributes']);
    }

    #[Test]
    public function employee_update_does_not_create_a_new_activity_log_when_nothing_changes(): void
    {
        $user = $this->userWithPermission('employees.update');
        $company = Company::factory()->create();
        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'employee_number' => 'EMP-002',
            'name' => 'John Doe',
            'email' => 'john@example.test',
            'phone' => '+36 1 333 3333',
            'position' => 'Operator',
            'is_active' => true,
        ]);

        $payload = [
            'company_id' => $company->id,
            'employee_number' => 'EMP-002',
            'name' => 'John Doe',
            'email' => 'john@example.test',
            'phone' => '+36 1 333 3333',
            'position' => 'Operator',
            'is_active' => true,
        ];

        $existingUpdatedLogs = Activity::query()
            ->where('log_name', 'client.admin.employee')
            ->where('subject_type', Employee::class)
            ->where('subject_id', $employee->id)
            ->where('description', 'updated')
            ->count();

        $this->actingAs($user)
            ->putJson('/api/employees/' . $employee->id, $payload)
            ->assertOk()
            ->assertJsonPath('message', 'Employee updated successfully.');

        $updatedLogs = Activity::query()
            ->where('log_name', 'client.admin.employee')
            ->where('subject_type', Employee::class)
            ->where('subject_id', $employee->id)
            ->where('description', 'updated')
            ->count();

        $this->assertSame($existingUpdatedLogs, $updatedLogs);
    }

    private function userWithPermission(string $permission): User
    {
        Permission::findOrCreate($permission, 'web');

        $user = User::factory()->create();
        $user->givePermissionTo($permission);

        return $user;
    }
}
