<?php

namespace App\Repositories\Eloquent;

use App\Models\Company;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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
class CompanyRepository extends BaseRepository implements CompanyRepositoryInterface
{
    /**
     * A repositoryhoz tartozó Eloquent modell osztályneve.
     *
     * @return class-string<Company>
     */
    public function model(): string
    {
        return Company::class;
    }

    /**
     * Céges lista lekérése szűréssel, rendezéssel és lapozással.
     *
     * @param CompanyListFilters $filters
     */
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

    /**
     * Új cég létrehozása az átadott attribútumokból.
     *
     * @param CompanyWriteAttributes $attributes
     */
    public function create(array $attributes): Company
    {
        return $this->model->newQuery()->create($attributes);
    }

    /**
     * Cég frissítése az átadott attribútumokkal.
     *
     * @param CompanyWriteAttributes $attributes
     */
    public function update(Company $company, array $attributes): Company
    {
        $company->fill($attributes);
        $company->save();

        return $company->refresh();
    }

    /**
     * Cég törlése.
     */
    public function delete(Company $company): void
    {
        $company->delete();
    }

    /**
     * Cég keresése elsődleges kulcs alapján, kivétellel ha nem található.
     */
    public function findById(int $companyId): Company
    {
        $company = $this->model->newQuery()->find($companyId);

        if (! $company instanceof Company) {
            throw (new ModelNotFoundException())->setModel(Company::class, [$companyId]);
        }

        return $company;
    }
}
