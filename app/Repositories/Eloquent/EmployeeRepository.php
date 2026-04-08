<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class EmployeeRepository
{
    /**
     * @var array<string, string>
     */
    private array $sortableFields = [
        'name' => 'name',
        'email' => 'email',
        'position' => 'position',
        'employee_number' => 'employee_number',
        'is_active' => 'is_active',
        'created_at' => 'created_at',
    ];

    public function paginateForAdminIndex(
        array $filters,
        ?string $sortField,
        ?int $sortOrder,
        int $perPage = 10,
        int $page = 1,
    ): LengthAwarePaginator {
        $query = Employee::query()->with('company');

        $this->applyFilters($query, $filters);

        $column = $this->sortableFields[$sortField ?? 'name'] ?? 'name';
        $direction = ($sortOrder ?? 1) === -1 ? 'desc' : 'asc';

        return $query
            ->orderBy($column, $direction)
            ->paginate(
                perPage: $perPage,
                columns: ['*'],
                pageName: 'page',
                page: $page,
            );
    }

    public function create(array $data): Employee
    {
        return Employee::create($data)->load('company');
    }

    public function update(Employee $employee, array $data): Employee
    {
        $employee->update($data);

        return $employee->fresh(['company']);
    }

    public function delete(Employee $employee): void
    {
        $employee->delete();
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $global = trim((string) ($filters['global'] ?? ''));
        $companyId = $filters['company_id'] ?? null;
        $status = $filters['status'] ?? null;

        if ($global !== '') {
            $query->where(function (Builder $innerQuery) use ($global): void {
                $innerQuery
                    ->where('name', 'like', "%{$global}%")
                    ->orWhere('email', 'like', "%{$global}%")
                    ->orWhere('position', 'like', "%{$global}%")
                    ->orWhere('employee_number', 'like', "%{$global}%")
                    ->orWhere('phone', 'like', "%{$global}%");
            });
        }

        if ($companyId !== null) {
            $query->where('company_id', (int) $companyId);
        }

        if ($status !== null && $status !== '') {
            $query->where('is_active', filter_var($status, FILTER_VALIDATE_BOOL));
        }
    }
}