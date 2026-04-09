<?php

namespace App\Repositories\Contracts;

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
interface AuditLogRepositoryInterface
{
    /**
     * @param AuditLogListFilters $filters
     */
    public function paginateForIndex(array $filters): LengthAwarePaginator;
}
