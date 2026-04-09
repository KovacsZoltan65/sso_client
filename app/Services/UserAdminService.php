<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @phpstan-type UserAdminFilters array{
 *     global?: string|null,
 *     has_sso_link?: bool|int|string|null,
 *     sort_field?: string|null,
 *     sort_order?: string|null,
 *     per_page?: int|string|null,
 *     page?: int|string|null
 * }
 * @phpstan-type UserMetadataPayload array{
 *     name?: string,
 *     email?: string,
 *     local_status?: string|null,
 *     notes?: string|null
 * }
 */
class UserAdminService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {
    }

    /**
     * Felhasználói admin lista lekérése szűréssel, rendezéssel és lapozással.
     *
     * @param  UserAdminFilters  $filters
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

    /**
     * Egy admin felületen szerkeszthető felhasználó betöltése.
     */
    public function findForAdmin(int $id): User
    {
        return $this->users->findForAdmin($id);
    }

    /**
     * Felhasználó helyi metaadatainak frissítése.
     *
     * @param  UserMetadataPayload  $attributes
     */
    public function update(User $user, array $attributes): User
    {
        return $this->users->updateLocalMetadata($user, $attributes);
    }
}
