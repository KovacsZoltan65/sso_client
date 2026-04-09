<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Models\Role;

/**
 * @phpstan-type RoleListFilters array{
 *     search?: string|null,
 *     page?: int|string|null,
 *     per_page?: int|string|null,
 *     sort_field?: string|null,
 *     sort_order?: string|null
 * }
 * @phpstan-type RoleWriteAttributes array{
 *     name: string,
 *     guard_name: string
 * }
 */
interface RoleRepositoryInterface
{
    /**
     * @param RoleListFilters $filters
     */
    public function paginateForIndex(array $filters): LengthAwarePaginator;

    /**
     * @param RoleWriteAttributes $attributes
     * @param array<int, int> $permissionIds
     */
    public function create(array $attributes, array $permissionIds = []): Role;

    /**
     * @param RoleWriteAttributes $attributes
     * @param array<int, int> $permissionIds
     */
    public function update(Role $role, array $attributes, array $permissionIds = []): Role;

    public function delete(Role $role): void;

    public function findById(int $roleId): Role;
}
