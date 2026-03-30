<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserAdminService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return $this->users->paginateForAdminIndex(
            filters: $filters,
            sortField: $filters['sort_field'] ?? 'created_at',
            sortOrder: $filters['sort_order'] ?? 'desc',
            perPage: (int) ($filters['per_page'] ?? 10),
            page: (int) ($filters['page'] ?? 1),
        );
    }

    public function findForAdmin(int $id): User
    {
        return $this->users->findForAdmin($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $user, array $attributes): User
    {
        return $this->users->updateLocalMetadata($user, $attributes);
    }
}
