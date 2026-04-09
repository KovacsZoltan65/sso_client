<?php

namespace App\Repositories\Eloquent;

use App\Models\Role;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class RoleRepository extends BaseRepository implements RoleRepositoryInterface
{
    /**
     * @var array<string, string>
     */
    private array $sortableFields = [
        'id' => 'id',
        'name' => 'name',
        'guard_name' => 'guard_name',
        'permissions_count' => 'permissions_count',
        'created_at' => 'created_at',
        'updated_at' => 'updated_at',
    ];

    public function model(): string
    {
        return Role::class;
    }

    public function paginateForIndex(array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 10);
        $sortField = (string) ($filters['sort_field'] ?? 'created_at');
        $sortOrder = (string) ($filters['sort_order'] ?? 'desc');
        $search = trim((string) ($filters['search'] ?? ''));
        $column = $this->sortableFields[$sortField] ?? 'created_at';
        $direction = $sortOrder === 'asc' ? 'asc' : 'desc';

        return $this->model
            ->newQuery()
            ->with(['permissions:id,name'])
            ->withCount('permissions')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $builder) use ($search): void {
                    $builder
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('guard_name', 'like', "%{$search}%");
                });
            })
            ->orderBy($column, $direction)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $attributes, array $permissionIds = []): Role
    {
        /** @var Role $role */
        $role = $this->model->newQuery()->create($attributes);
        $role->syncPermissions($permissionIds);

        return $role->load('permissions:id,name')->loadCount('permissions');
    }

    public function update(Role $role, array $attributes, array $permissionIds = []): Role
    {
        $role->fill($attributes);
        $role->save();
        $role->syncPermissions($permissionIds);

        return $role->load('permissions:id,name')->loadCount('permissions');
    }

    public function delete(Role $role): void
    {
        $role->delete();
    }

    public function findById(int $roleId): Role
    {
        $role = $this->model->newQuery()->with(['permissions:id,name'])->withCount('permissions')->find($roleId);

        if (! $role instanceof Role) {
            throw (new ModelNotFoundException())->setModel(Role::class, [$roleId]);
        }

        return $role;
    }
}
