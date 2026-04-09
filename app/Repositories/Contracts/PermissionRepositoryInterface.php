<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Models\Permission;

/**
 * @phpstan-type PermissionListFilters array{
 *     search?: string|null,
 *     page?: int|string|null,
 *     per_page?: int|string|null,
 *     sort_field?: string|null,
 *     sort_order?: string|null
 * }
 * @phpstan-type PermissionWriteAttributes array{
 *     name: string,
 *     guard_name: string
 * }
 */
interface PermissionRepositoryInterface
{
    /**
     * @param PermissionListFilters $filters
     */
    public function paginateForIndex(array $filters): LengthAwarePaginator;

    /**
     * @param PermissionWriteAttributes $attributes
     */
    public function create(array $attributes): Permission;

    /**
     * @param PermissionWriteAttributes $attributes
     */
    public function update(Permission $permission, array $attributes): Permission;

    public function delete(Permission $permission): void;

    public function findById(int $permissionId): Permission;
}
