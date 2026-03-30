<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    /**
     * @var array<string, string>
     */
    private array $sortableFields = [
        'id' => 'id',
        'sso_user_id' => 'sso_user_id',
        'name' => 'name',
        'email' => 'email',
        'local_status' => 'local_status',
        'last_authenticated_at' => 'last_authenticated_at',
        'created_at' => 'created_at',
        'updated_at' => 'updated_at',
    ];

    public function model(): string
    {
        return User::class;
    }

    public function paginateForAdminIndex(
        array $filters,
        ?string $sortField,
        ?string $sortOrder,
        int $perPage = 10,
        int $page = 1,
    ): LengthAwarePaginator {
        $global = trim((string) ($filters['global'] ?? ''));
        $localStatus = $filters['local_status'] ?? null;
        $hasSsoLink = $filters['has_sso_link'] ?? null;

        $column = $this->sortableFields[$sortField ?? ''] ?? 'created_at';
        $direction = $sortOrder === 'asc' ? 'asc' : 'desc';

        return $this->model
            ->newQuery()
            ->when($global !== '', function ($query) use ($global): void {
                $query->where(function ($innerQuery) use ($global): void {
                    $innerQuery
                        ->where('id', 'like', "%{$global}%")
                        ->orWhere('sso_user_id', 'like', "%{$global}%")
                        ->orWhere('name', 'like', "%{$global}%")
                        ->orWhere('email', 'like', "%{$global}%");
                });
            })
            ->when($localStatus !== null, fn ($query) => $query->where('local_status', $localStatus))
            ->when($hasSsoLink !== null, function ($query) use ($hasSsoLink): void {
                if ((bool) $hasSsoLink) {
                    $query->whereNotNull('sso_user_id');
                    return;
                }

                $query->whereNull('sso_user_id');
            })
            ->orderBy($column, $direction)
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();
    }

    public function findForAdmin(int $id): User
    {
        /** @var User $user */
        $user = $this->model->newQuery()->findOrFail($id);

        return $user;
    }

    public function countAll(): int
    {
        return $this->model->newQuery()->count();
    }

    public function getRoleNames(): Collection
    {
        /** @var Collection<int, string> $roles */
        $roles = Role::query()
            ->orderBy('name')
            ->pluck('name');

        return $roles;
    }

    public function recent(int $limit = 5): Collection
    {
        return $this->model->newQuery()->latest()->limit($limit)->get();
    }

    public function createWithRoles(array $attributes, array $roles = []): User
    {
        /** @var User $user */
        $user = $this->model->newQuery()->create($attributes);
        $user->syncRoles($roles);

        return $user->load('roles');
    }

    public function updateWithRoles(User $user, array $attributes, array $roles = []): User
    {
        $user->fill($attributes);
        $user->save();
        $user->syncRoles($roles);

        return $user->load('roles');
    }

    public function updateProfile(User $user, array $attributes): User
    {
        $user->fill($attributes);
        $user->save();

        return $user->refresh();
    }

    public function updateLocalMetadata(User $user, array $attributes): User
    {
        $user->fill([
            'local_status' => $attributes['local_status'] ?? $user->local_status,
            'notes' => $attributes['notes'] ?? $user->notes,
        ]);
        $user->save();

        return $user->refresh();
    }

    public function updatePassword(User $user, string $hashedPassword): User
    {
        $user->forceFill([
            'password' => $hashedPassword,
        ])->save();

        return $user->refresh();
    }

    public function refreshUser(User $user): User
    {
        return $user->refresh();
    }

    public function getByIds(array $ids): Collection
    {
        /** @var Collection<int, User> $users */
        $users = $this->model
            ->newQuery()
            ->with('roles')
            ->whereIn('id', $ids)
            ->get();

        return $users;
    }

    public function deleteUser(User $user): void
    {
        $user->delete();
    }

    public function deleteByIds(array $ids): void
    {
        $this->model
            ->newQuery()
            ->whereIn('id', $ids)
            ->delete();
    }
}
