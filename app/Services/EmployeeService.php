<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;
use App\Repositories\Eloquent\EmployeeRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EmployeeService
{
    public function __construct(
        private readonly EmployeeRepository $employeeRepository,
    ) {
    }

    public function paginate(
        array $filters,
        ?string $sortField,
        ?int $sortOrder,
        int $perPage,
        int $page,
    ): LengthAwarePaginator {
        return $this->employeeRepository->paginateForAdminIndex(
            filters: $filters,
            sortField: $sortField,
            sortOrder: $sortOrder,
            perPage: $perPage,
            page: $page,
        );
    }

    public function create(array $data): Employee
    {
        return $this->employeeRepository->create($data);
    }

    public function update(Employee $employee, array $data): Employee
    {
        return $this->employeeRepository->update($employee, $data);
    }

    public function delete(Employee $employee): void
    {
        $this->employeeRepository->delete($employee);
    }
}