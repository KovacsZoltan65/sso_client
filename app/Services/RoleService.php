<?php

namespace App\Services;

use App\Exceptions\ProtectedAuthorizationArtifactException;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Services\Audit\AuditLogService;
use App\Support\ProtectedAuthorizationArtifacts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\Permission\Models\Role;

/**
 * @phpstan-type RoleListFilters array{
 *     search?: string|null,
 *     page?: int|string|null,
 *     per_page?: int|string|null,
 *     sort_field?: string|null,
 *     sort_order?: string|null
 * }
 * @phpstan-type RoleWritePayload array{
 *     name: string,
 *     guard_name: string,
 *     permission_ids?: array<int, int>
 * }
 */
class RoleService
{
    public function __construct(
        private readonly RoleRepositoryInterface $roles,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return $this->roles->paginateForIndex($filters);
    }

    /**
     * @param array $payload
     * @return Role
     */
    public function store(array $payload): Role
    {
        $permissionIds = array_values(array_map('intval', $payload['permission_ids'] ?? []));
        $role = $this->roles->create([
            'name' => $payload['name'],
            'guard_name' => $payload['guard_name'] ?? 'web',
        ], $permissionIds);

        $this->auditLogService->logClientAdminCrud(
            resource: 'role',
            action: 'created',
            description: 'Client role created.',
            subject: $role,
            causer: auth()->user(),
            properties: [
                'target_role_id' => (int) $role->id,
                'updated_fields' => ['name', 'guard_name', 'permission_ids'],
                'guard_name' => $role->guard_name,
                'permission_count' => (int) $role->permissions_count,
            ],
        );

        return $role;
    }

    /**
     * @param int $roleId
     * @param array $payload
     * @return Role
     */
    public function update(int $roleId, array $payload): Role
    {
        $role = $this->roles->findById($roleId);
        $original = [
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'permission_ids' => $role->permissions->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all(),
        ];

        if (ProtectedAuthorizationArtifacts::blocksProtectedRoleIdentityUpdate($role, $payload)) {
            throw ProtectedAuthorizationArtifactException::roleIdentityUpdate($role->name);
        }

        $permissionIds = array_values(array_map('intval', $payload['permission_ids'] ?? []));
        $updatedRole = $this->roles->update($role, [
            'name' => $payload['name'],
            'guard_name' => $payload['guard_name'] ?? $role->guard_name,
        ], $permissionIds);
        $changedFields = $this->changedFields($original, [
            'name' => $updatedRole->name,
            'guard_name' => $updatedRole->guard_name,
            'permission_ids' => $updatedRole->permissions->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all(),
        ]);

        if ($changedFields !== []) {
            $this->auditLogService->logClientAdminCrud(
                resource: 'role',
                action: 'updated',
                description: 'Client role updated.',
                subject: $updatedRole,
                causer: auth()->user(),
                properties: [
                    'target_role_id' => (int) $updatedRole->id,
                    'updated_fields' => $changedFields,
                    'guard_name' => $updatedRole->guard_name,
                    'permission_count' => (int) $updatedRole->permissions_count,
                ],
            );
        }

        return $updatedRole;
    }

    /**
     * @param int $roleId
     * @return void
     */
    public function delete(int $roleId): void
    {
        $role = $this->roles->findById($roleId);

        if (ProtectedAuthorizationArtifacts::isProtectedRole($role)) {
            throw ProtectedAuthorizationArtifactException::roleDeletion($role->name);
        }

        $this->auditLogService->logClientAdminCrud(
            resource: 'role',
            action: 'deleted',
            description: 'Client role deleted.',
            subject: $role,
            causer: auth()->user(),
            properties: [
                'target_role_id' => (int) $role->id,
                'guard_name' => $role->guard_name,
                'permission_count' => (int) $role->permissions_count,
            ],
        );

        $this->roles->delete($role);
    }

    /**
     * @param  array{name: string, guard_name: string, permission_ids: list<int>}  $before
     * @param  array{name: string, guard_name: string, permission_ids: list<int>}  $after
     * @return list<string>
     */
    private function changedFields(array $before, array $after): array
    {
        $changed = [];

        foreach ($before as $field => $value) {
            if (($after[$field] ?? null) !== $value) {
                $changed[] = $field;
            }
        }

        return $changed;
    }
}
