<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Audit\AuditLogService;
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
        private readonly AuditLogService $auditLogService,
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
        $updatedUser = $this->users->updateLocalMetadata($user, $attributes);

        $this->auditLogService->logClientAdminCrud(
            resource: 'user',
            action: 'updated',
            description: 'Client local user metadata updated.',
            subject: $updatedUser,
            causer: auth()->user(),
            properties: [
                'target_local_user_id' => $updatedUser->id,
                'updated_fields' => array_keys($attributes),
                'status' => (string) ($updatedUser->local_status ?? 'unknown'),
            ],
        );

        return $updatedUser;
    }
}
