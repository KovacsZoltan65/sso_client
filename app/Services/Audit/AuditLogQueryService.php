<?php

namespace App\Services\Audit;

use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\Activitylog\Models\Activity;

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
    /**
     * @param AuditLogRepositoryInterface $auditLogs
     */
    public function __construct(
        private readonly AuditLogRepositoryInterface $auditLogs,
    ) {
    }

    /**
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return $this->auditLogs->paginateForIndex($filters);
    }

    /**
     * @param int $auditLogId
     * @return Activity
     */
    public function show(int $auditLogId): Activity
    {
        return $this->auditLogs->findById($auditLogId);
    }
}
