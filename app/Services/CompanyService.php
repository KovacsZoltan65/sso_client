<?php

namespace App\Services;

use App\Models\Company;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @phpstan-type CompanyListFilters array{
 *     per_page?: int|string|null,
 *     sort_field?: string|null,
 *     sort_order?: string|null,
 *     search?: string|null,
 *     is_active?: bool|int|string|null
 * }
 * @phpstan-type CompanyWritePayload array{
 *     name: string,
 *     code: string,
 *     email?: string|null,
 *     phone?: string|null,
 *     address?: string|null,
 *     is_active: bool
 * }
 */
class CompanyService
{
    public function __construct(
        private readonly CompanyRepositoryInterface $companies,
    ) {}

    /**
     * Céges lista lekérése a szűrőkkel és lapozási beállításokkal.
     *
     * @param  CompanyListFilters  $filters
     * @return LengthAwarePaginator
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return $this->companies->paginateForIndex($filters);
    }

    /**
     * Új cég létrehozása validált admin payload alapján.
     *
     * @param  CompanyWritePayload  $payload
     * @return Company
     */
    public function store(array $payload): Company
    {
        return $this->companies->create($payload);
    }

    /**
     * Meglévő cég frissítése azonosító alapján.
     *
     * @param  CompanyWritePayload  $payload
     * @return Company
     */
    public function update(int $companyId, array $payload): Company
    {
        $company = $this->companies->findById($companyId);

        return $this->companies->update($company, $payload);
    }

    /**
     * Cég törlése azonosító alapján.
     * 
     * @param int $companyId
     */
    public function delete(int $companyId): void
    {
        $company = $this->companies->findById($companyId);

        $this->companies->delete($company);
    }
}
