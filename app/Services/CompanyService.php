<?php

namespace App\Services;

use App\Models\Company;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CompanyService
{
    public function __construct(
        private readonly CompanyRepositoryInterface $companies,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return $this->companies->paginateForIndex($filters);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function store(array $payload): Company
    {
        return $this->companies->create($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(int $companyId, array $payload): Company
    {
        $company = $this->companies->findById($companyId);

        return $this->companies->update($company, $payload);
    }

    public function delete(int $companyId): void
    {
        $company = $this->companies->findById($companyId);

        $this->companies->delete($company);
    }
}
