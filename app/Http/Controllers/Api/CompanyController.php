<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Companies\IndexCompanyRequest;
use App\Http\Requests\Companies\StoreCompanyRequest;
use App\Http\Requests\Companies\UpdateCompanyRequest;
use App\Models\Company;
use App\Services\CompanyService;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

/**
 * Company API Controller
 *
 * Felelős a Company erőforrás admin API végpontjaiért.
 *
 * Architektúra:
 * - Controller: request kezelés + authorization + response
 * - Service: business logic delegálás (CompanyService)
 *
 * Fontos:
 * - Minden végpont policy alapú authorize hívást használ
 * - Minden válasz egységes ApiResponse formátumban érkezik
 * - Controller NEM tartalmaz business logikát
 */
class CompanyController extends Controller
{
    /**
     * @param CompanyService $companyService
     */
    public function __construct(
        private readonly CompanyService $companyService,
    ) {
    }

    /**
     * Cégek listázása paginált formában.
     *
     * @param IndexCompanyRequest $request Validált szűrők és rendezési paraméterek
     *
     * @return JsonResponse
     *
     * @throws AuthorizationException
     */
    public function index(IndexCompanyRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Company::class);

        $companies = $this->companyService->list($request->validated());

        return ApiResponse::success(
            'Companies retrieved successfully.',
            data: [
                'items' => collect($companies->items())
                    ->map(fn (Company $company) => $this->toArray($company))
                    ->values()
                    ->all(),
            ],
            meta: [
                'pagination' => [
                    'current_page' => $companies->currentPage(),
                    'last_page' => $companies->lastPage(),
                    'per_page' => $companies->perPage(),
                    'total' => $companies->total(),
                    'from' => $companies->firstItem(),
                    'to' => $companies->lastItem(),
                ],
                'filters' => [
                    'search' => $request->validated('search'),
                    'is_active' => $request->validated('is_active'),
                    'sort_field' => $request->validated('sort_field', 'created_at'),
                    'sort_order' => $request->validated('sort_order', 'desc'),
                ],
            ],
        );
    }

    /**
     * Új cég létrehozása.
     *
     * @param StoreCompanyRequest $request Validált input adatok
     *
     * @return JsonResponse
     *
     * @throws AuthorizationException
     */
    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $this->authorize('create', Company::class);

        $company = $this->companyService->store($request->validated());

        return ApiResponse::success(
            'Company created successfully.',
            data: [
                'company' => $this->toArray($company),
            ],
            status: 201,
        );
    }

    /**
     * Meglévő cég frissítése.
     *
     * @param UpdateCompanyRequest $request Validált input adatok
     * @param Company $company Route model binding alapján feloldott entitás
     *
     * @return JsonResponse
     *
     * @throws AuthorizationException
     */
    public function update(UpdateCompanyRequest $request, Company $company): JsonResponse
    {
        $this->authorize('update', $company);

        $company = $this->companyService->update($company->id, $request->validated());

        return ApiResponse::success(
            'Company updated successfully.',
            data: [
                'company' => $this->toArray($company),
            ],
        );
    }

    /**
     * Cég törlése.
     *
     * @param Company $company Route model binding alapján feloldott entitás
     *
     * @return JsonResponse
     *
     * @throws AuthorizationException
     */
    public function destroy(Company $company): JsonResponse
    {
        $this->authorize('delete', $company);

        $this->companyService->delete($company->id);

        return ApiResponse::success('Company deleted successfully.');
    }

    /**
     * API payload builder Company entitáshoz.
     *
     * Ez a metódus biztosítja az egységes response formátumot.
     *
     * @param Company $company
     *
     * @return array{
     *     id: int,
     *     name: string,
     *     code: string,
     *     email: string|null,
     *     phone: string|null,
     *     address: string|null,
     *     is_active: bool,
     *     created_at: string|null,
     *     updated_at: string|null
     * }
     */
    private function toArray(Company $company): array
    {
        return [
            'id' => $company->id,
            'name' => $company->name,
            'code' => $company->code,
            'email' => $company->email,
            'phone' => $company->phone,
            'address' => $company->address,
            'is_active' => $company->is_active,
            'created_at' => optional($company->created_at)?->toDateTimeString(),
            'updated_at' => optional($company->updated_at)?->toDateTimeString(),
        ];
    }
}

