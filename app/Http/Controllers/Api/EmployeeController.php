<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\EmployeeIndexRequest;
use App\Http\Requests\Employees\EmployeeStoreRequest;
use App\Http\Requests\Employees\EmployeeUpdateRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Company;
use App\Models\Employee;
use App\Services\EmployeeService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly EmployeeService $employeeService,
    ) {
    }

    /**
     * @return \Inertia\Response
     */
    public function index(): Response
    {
        $this->authorize('viewAny', Employee::class);

        return Inertia::render('Employees/Index', [
            'employeesApi' => [
                'endpoints' => [
                    'index' => route('api.employees.fetch'),
                    'store' => route('api.employees.store'),
                ],
            ],
            'permissions' => [
                'view' => request()->user()?->can('employees.view') ?? false,
                'create' => request()->user()?->can('employees.create') ?? false,
                'update' => request()->user()?->can('employees.update') ?? false,
                'delete' => request()->user()?->can('employees.delete') ?? false,
            ],
            'companies' => Company::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Company $company): array => [
                    'id' => $company->id,
                    'name' => $company->name,
                ])
                ->values(),
        ]);
    }

    /**
     * @param EmployeeIndexRequest $request
     * @return JsonResponse
     */
    public function fetch(EmployeeIndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Employee::class);

        $paginator = $this->employeeService->paginate(
            filters: $request->validatedFilters(),
            sortField: $request->string('sort_field')->toString() ?: 'name',
            sortOrder: $request->integer('sort_order', 1),
            perPage: $request->integer('per_page', 10),
            page: $request->integer('page', 1),
        );

        return response()->json([
            'message' => 'Employees fetched successfully.',
            'data' => EmployeeResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'errors' => null,
        ]);
    }

    /**
     * @param EmployeeStoreRequest $request
     * @return JsonResponse
     */
    public function store(EmployeeStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Employee::class);

        $employee = $this->employeeService->create($request->validated());

        return response()->json([
            'message' => 'Employee created successfully.',
            'data' => new EmployeeResource($employee),
            'meta' => null,
            'errors' => null,
        ], 201);
    }

    /**
     * @param EmployeeUpdateRequest $request
     * @param Employee $employee
     * @return JsonResponse
     */
    public function update(EmployeeUpdateRequest $request, Employee $employee): JsonResponse
    {
        $this->authorize('update', $employee);

        $employee = $this->employeeService->update($employee, $request->validated());

        return response()->json([
            'message' => 'Employee updated successfully.',
            'data' => new EmployeeResource($employee),
            'meta' => null,
            'errors' => null,
        ]);
    }

    /**
     * @param Employee $employee
     * @return JsonResponse
     */
    public function destroy(Employee $employee): JsonResponse
    {
        $this->authorize('delete', $employee);

        $this->employeeService->delete($employee);

        return response()->json([
            'message' => 'Employee deleted successfully.',
            'data' => null,
            'meta' => null,
            'errors' => null,
        ]);
    }
}