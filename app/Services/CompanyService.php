<?php

namespace App\Services;

use App\Models\Company;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use App\Services\Audit\AuditLogService;
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
        private readonly AuditLogService $auditLogService,
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
        $company = $this->companies->create($payload);

        $this->auditLogService->logClientAdminCrud(
            resource: 'company',
            action: 'created',
            description: 'Client company created.',
            subject: $company,
            causer: auth()->user(),
            properties: [
                'target_company_id' => $company->id,
                'updated_fields' => array_keys($payload),
                'status' => $company->is_active ? 'active' : 'inactive',
            ],
        );

        return $company;
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
        $original = $company->only([
            'name',
            'code',
            'email',
            'phone',
            'address',
            'is_active',
        ]);

        $updatedCompany = $this->companies->update($company, $payload);
        $changedFields = $this->changedFields($original, $updatedCompany->only(array_keys($original)));

        if ($changedFields !== []) {
            $this->auditLogService->logClientAdminCrud(
                resource: 'company',
                action: 'updated',
                description: 'Client company updated.',
                subject: $updatedCompany,
                causer: auth()->user(),
                properties: [
                    'target_company_id' => $updatedCompany->id,
                    'updated_fields' => $changedFields,
                    'status' => $updatedCompany->is_active ? 'active' : 'inactive',
                ],
            );
        }

        return $updatedCompany;
    }

    /**
     * Cég törlése azonosító alapján.
     * 
     * @param int $companyId
     */
    public function delete(int $companyId): void
    {
        $company = $this->companies->findById($companyId);

        $this->auditLogService->logClientAdminCrud(
            resource: 'company',
            action: 'deleted',
            description: 'Client company deleted.',
            subject: $company,
            causer: auth()->user(),
            properties: [
                'target_company_id' => $company->id,
            ],
        );

        $this->companies->delete($company);
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return list<string>
     */
    private function changedFields(array $before, array $after): array
    {
        $changed = [];

        foreach ($before as $field => $value) {
            if (($after[$field] ?? null) !== $value) {
                $changed[] = $field;
            }
        }

        return $changed;
    }
}
