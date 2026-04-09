<?php

namespace App\Repositories\Contracts;

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
interface AuditLogRepositoryInterface
{
    /**
     * @param array $filters
     * @return void
     */
    public function paginateForIndex(array $filters): LengthAwarePaginator;

    /**
     * @param int $auditLogId
     * @return void
     */
    public function findById(int $auditLogId): Activity;
}
