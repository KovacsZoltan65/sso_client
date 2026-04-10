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
 *
 * Thin orchestration layer for the read-only audit log UI.
 *
 * The repository owns filtering and pagination details, while this service
 * stays responsible for exposing an audit-log-specific application API to the
 * controller without leaking repository implementation choices upward.
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
     * Returns the paginated audit log listing used by the admin DataTable.
     *
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return $this->auditLogs->paginateForIndex($filters);
    }

    /**
     * Loads a single audit record for the read-only detail dialog.
     *
     * The repository is expected to eager load the relationships needed by the
     * frontend so the controller can shape a stable payload without issuing
     * follow-up queries.
     *
     * @param int $auditLogId
     * @return Activity
     */
    public function show(int $auditLogId): Activity
    {
        return $this->auditLogs->findById($auditLogId);
    }
}
