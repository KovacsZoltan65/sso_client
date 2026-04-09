<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\PermissionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Spatie\Permission\Models\Permission;

class PermissionRepository extends BaseRepository implements PermissionRepositoryInterface
{
    /**
     * @var array<string, string>
     */
    private array $sortableFields = [
        'id' => 'id',
        'name' => 'name',
        'guard_name' => 'guard_name',
        'roles_count' => 'roles_count',
        'created_at' => 'created_at',
        'updated_at' => 'updated_at',
    ];

    public function model(): string
    {
        return Permission::class;
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
            ->withCount('roles')
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

    public function create(array $attributes): Permission
    {
        /** @var Permission $permission */
        $permission = $this->model->newQuery()->create($attributes);

        return $permission->loadCount('roles');
    }

    public function update(Permission $permission, array $attributes): Permission
    {
        $permission->fill($attributes);
        $permission->save();

        return $permission->loadCount('roles');
    }

    public function delete(Permission $permission): void
    {
        $permission->roles()->detach();
        $permission->users()->detach();
        $permission->delete();
    }

    public function findById(int $permissionId): Permission
    {
        $permission = $this->model->newQuery()->withCount('roles')->find($permissionId);

        if (! $permission instanceof Permission) {
            throw (new ModelNotFoundException())->setModel(Permission::class, [$permissionId]);
        }

        return $permission;
    }
}
