<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * @phpstan-type UserAdminFilters array{
 *     global?: string|null,
 *     local_status?: string|null,
 *     has_sso_link?: bool|int|string|null
 * }
 * @phpstan-type UserWriteAttributes array{
 *     name?: string,
 *     email?: string,
 *     password?: string,
 *     local_status?: string|null,
 *     notes?: string|null
 * }
 */
interface UserRepositoryInterface
{
    /**
     * Admin felhasználói lista lekérése.
     *
     * @param  UserAdminFilters  $filters
     */
    public function paginateForAdminIndex(
        array $filters,
        ?string $sortField,
        ?string $sortOrder,
        int $perPage = 10,
        int $page = 1,
    ): LengthAwarePaginator;

    /**
     * Egy admin felületen szerkeszthető felhasználó betöltése.
     */
    public function findForAdmin(int $id): User;

    /**
     * Összes felhasználó darabszámának lekérése.
     */
    public function countAll(): int;

    /**
     * @return Collection<int, string>
     */
    /**
     * Elérhető szerepkörnevek listázása.
     *
     * @return Collection<int, string>
     */
    public function getRoleNames(): Collection;

    /**
     * Legutóbb létrehozott felhasználók lekérése.
     *
     * @return Collection<int, User>
     */
    public function recent(int $limit = 5): Collection;

    /**
     * Új felhasználó létrehozása szerepkörökkel együtt.
     *
     * @param  UserWriteAttributes  $attributes
     * @param  array<int, string>  $roles
     */
    public function createWithRoles(array $attributes, array $roles = []): User;

    /**
     * Meglévő felhasználó frissítése szerepkörökkel együtt.
     *
     * @param  UserWriteAttributes  $attributes
     * @param  array<int, string>  $roles
     */
    public function updateWithRoles(User $user, array $attributes, array $roles = []): User;

    /**
     * Profil adatok frissítése.
     *
     * @param  UserWriteAttributes  $attributes
     */
    public function updateProfile(User $user, array $attributes): User;

    /**
     * Helyi admin metaadatok frissítése.
     *
     * @param  UserWriteAttributes  $attributes
     */
    public function updateLocalMetadata(User $user, array $attributes): User;

    /**
     * Titkosított jelszó mentése.
     */
    public function updatePassword(User $user, string $hashedPassword): User;

    /**
     * Felhasználó friss újratöltése az adatbázisból.
     */
    public function refreshUser(User $user): User;

    /**
     * @param  array<int, int>  $ids
     * @return Collection<int, User>
     */
    /**
     * Felhasználók lekérése azonosítók alapján.
     *
     * @param  array<int, int>  $ids
     * @return Collection<int, User>
     */
    public function getByIds(array $ids): Collection;

    /**
     * Egy felhasználó törlése.
     */
    public function deleteUser(User $user): void;

    /**
     * Több felhasználó törlése azonosítók alapján.
     *
     * @param  array<int, int>  $ids
     */
    public function deleteByIds(array $ids): void;
}
