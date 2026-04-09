<?php

namespace App\Services;

use App\Exceptions\ProtectedAuthorizationArtifactException;
use App\Models\Role;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Support\ProtectedAuthorizationArtifacts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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
        return $this->roles->create([
            'name' => $payload['name'],
            'guard_name' => $payload['guard_name'] ?? 'web',
        ], $permissionIds);
    }

    /**
     * @param int $roleId
     * @param array $payload
     * @return Role
     */
    public function update(int $roleId, array $payload): Role
    {
        $role = $this->roles->findById($roleId);
        if (ProtectedAuthorizationArtifacts::blocksProtectedRoleIdentityUpdate($role, $payload)) {
            throw ProtectedAuthorizationArtifactException::roleIdentityUpdate($role->name);
        }

        $permissionIds = array_values(array_map('intval', $payload['permission_ids'] ?? []));
        return $this->roles->update($role, [
            'name' => $payload['name'],
            'guard_name' => $payload['guard_name'] ?? $role->guard_name,
        ], $permissionIds);
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

        $this->roles->delete($role);
    }
}
