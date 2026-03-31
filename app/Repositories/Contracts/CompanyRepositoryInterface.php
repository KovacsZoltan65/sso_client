<?php

namespace App\Repositories\Contracts;

use App\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @phpstan-type CompanyListFilters array{
 *     per_page?: int|string|null,
 *     sort_field?: string|null,
 *     sort_order?: string|null,
 *     search?: string|null,
 *     is_active?: bool|int|string|null
 * }
 * @phpstan-type CompanyWriteAttributes array{
 *     name: string,
 *     code: string,
 *     email?: string|null,
 *     phone?: string|null,
 *     address?: string|null,
 *     is_active: bool
 * }
 */
interface CompanyRepositoryInterface
{
    /**
     * Céges lista lekérése lapozható formában.
     *
     * @param  CompanyListFilters  $filters
     */
    public function paginateForIndex(array $filters): LengthAwarePaginator;

    /**
     * Új cég létrehozása.
     *
     * @param  CompanyWriteAttributes  $attributes
     */
    public function create(array $attributes): Company;

    /**
     * Meglévő cég frissítése.
     *
     * @param  CompanyWriteAttributes  $attributes
     */
    public function update(Company $company, array $attributes): Company;

    /**
     * Cég törlése.
     */
    public function delete(Company $company): void;

    /**
     * Cég keresése azonosító alapján.
     */
    public function findById(int $companyId): Company;
}
