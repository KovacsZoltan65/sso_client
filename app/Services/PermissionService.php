<?php

namespace App\Services;

use App\Exceptions\ProtectedAuthorizationArtifactException;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use App\Services\Audit\AuditLogService;
use App\Support\ProtectedAuthorizationArtifacts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\Permission\Models\Permission;

/**
 * @phpstan-type PermissionListFilters array{
 *     search?: string|null,
 *     page?: int|string|null,
 *     per_page?: int|string|null,
 *     sort_field?: string|null,
 *     sort_order?: string|null
 * }
 * @phpstan-type PermissionWritePayload array{
 *     name: string,
 *     guard_name: string
 * }
 */
class PermissionService
{
    public function __construct(
        private readonly PermissionRepositoryInterface $permissions,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return $this->permissions->paginateForIndex($filters);
    }

    /**
     * @param array $payload
     * @return Permission
     */
    public function store(array $payload): Permission
    {
        $permission = $this->permissions->create([
            'name' => $payload['name'],
            'guard_name' => $payload['guard_name'] ?? 'web',
        ]);

        $this->auditLogService->logClientAdminCrud(
            resource: 'permission',
            action: 'created',
            description: 'Client permission created.',
            subject: $permission,
            causer: auth()->user(),
            properties: [
                'target_permission_id' => (int) $permission->id,
                'updated_fields' => ['name', 'guard_name'],
                'guard_name' => $permission->guard_name,
                'role_count' => (int) $permission->roles_count,
            ],
        );

        return $permission;
    }

    /**
     * @param int $permissionId
     * @param array $payload
     * @return Permission
     */
    public function update(int $permissionId, array $payload): Permission
    {
        $permission = $this->permissions->findById($permissionId);

        if (ProtectedAuthorizationArtifacts::blocksProtectedPermissionIdentityUpdate($permission, $payload)) {
            throw ProtectedAuthorizationArtifactException::permissionIdentityUpdate($permission->name);
        }

        $updatedPermission = $this->permissions->update($permission, [
            'name' => $payload['name'],
            'guard_name' => $payload['guard_name'] ?? $permission->guard_name,
        ]);

        $this->auditLogService->logClientAdminCrud(
            resource: 'permission',
            action: 'updated',
            description: 'Client permission updated.',
            subject: $updatedPermission,
            causer: auth()->user(),
            properties: [
                'target_permission_id' => (int) $updatedPermission->id,
                'updated_fields' => ['name', 'guard_name'],
                'guard_name' => $updatedPermission->guard_name,
                'role_count' => (int) $updatedPermission->roles_count,
            ],
        );

        return $updatedPermission;
    }

    /**
     * @param int $permissionId
     * @return void
     */
    public function delete(int $permissionId): void
    {
        $permission = $this->permissions->findById($permissionId);

        if (ProtectedAuthorizationArtifacts::isProtectedPermission($permission)) {
            throw ProtectedAuthorizationArtifactException::permissionDeletion($permission->name);
        }

        $this->auditLogService->logClientAdminCrud(
            resource: 'permission',
            action: 'deleted',
            description: 'Client permission deleted.',
            subject: $permission,
            causer: auth()->user(),
            properties: [
                'target_permission_id' => (int) $permission->id,
                'guard_name' => $permission->guard_name,
                'role_count' => (int) $permission->roles_count,
            ],
        );

        $this->permissions->delete($permission);
    }
}
