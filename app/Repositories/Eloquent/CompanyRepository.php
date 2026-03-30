<?php

namespace App\Repositories\Eloquent;

use App\Models\Company;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CompanyRepository extends BaseRepository implements CompanyRepositoryInterface
{
    public function model(): string
    {
        return Company::class;
    }

    public function paginateForIndex(array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 10);
        $sortField = (string) ($filters['sort_field'] ?? 'created_at');
        $sortOrder = (string) ($filters['sort_order'] ?? 'desc');
        $search = trim((string) ($filters['search'] ?? ''));

        $query = $this->model->newQuery();

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (\array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        return $query
            ->orderBy($sortField, $sortOrder)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $attributes): Company
    {
        return $this->model->newQuery()->create($attributes);
    }

    public function update(Company $company, array $attributes): Company
    {
        $company->fill($attributes);
        $company->save();

        return $company->refresh();
    }

    public function delete(Company $company): void
    {
        $company->delete();
    }

    public function findById(int $companyId): Company
    {
        $company = $this->model->newQuery()->find($companyId);

        if (! $company instanceof Company) {
            throw (new ModelNotFoundException())->setModel(Company::class, [$companyId]);
        }

        return $company;
    }
}
