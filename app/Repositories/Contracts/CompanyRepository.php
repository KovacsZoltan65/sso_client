<?php

namespace App\Repositories\Contracts;

use App\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CompanyRepository
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForIndex(array $filters): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Company;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Company $company, array $attributes): Company;

    public function delete(Company $company): void;

    public function findById(int $companyId): Company;
}
