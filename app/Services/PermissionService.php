<?php

namespace App\Services;

use App\Exceptions\ProtectedAuthorizationArtifactException;
use App\Models\Permission;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use App\Support\ProtectedAuthorizationArtifacts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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
        return $this->permissions->create([
            'name' => $payload['name'],
            'guard_name' => $payload['guard_name'] ?? 'web',
        ]);
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

        return $this->permissions->update($permission, [
            'name' => $payload['name'],
            'guard_name' => $payload['guard_name'] ?? $permission->guard_name,
        ]);
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

        $this->permissions->delete($permission);
    }
}
