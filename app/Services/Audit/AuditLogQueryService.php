<?php

namespace App\Services\Audit;

use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @phpstan-type AuditLogListFilters array{
 *     page?: int|string|null,
 *     per_page?: int|string|null,
 *     sort_field?: string|null,
 *     sort_order?: string|null,
 *     global?: string|null,
 *     event?: string|null,
 *     user_id?: int|string|null,
 *     subject_type?: string|null
 * }
 */
class AuditLogQueryService
{
    public function __construct(
        private readonly AuditLogRepositoryInterface $auditLogs,
    ) {
    }

    /**
     * @param AuditLogListFilters $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return $this->auditLogs->paginateForIndex($filters);
    }
}
