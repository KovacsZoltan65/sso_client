<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
class AuditLogRepository extends BaseRepository implements AuditLogRepositoryInterface
{
    public function model(): string
    {
        return Activity::class;
    }

    /**
     * @param array $filters
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function paginateForIndex(array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 10);
        $sortField = (string) ($filters['sort_field'] ?? 'created_at');
        $sortOrder = (string) ($filters['sort_order'] ?? 'desc');
        $global = trim((string) ($filters['global'] ?? ''));
        $event = trim((string) ($filters['event'] ?? ''));
        $subjectType = trim((string) ($filters['subject_type'] ?? ''));
        $userId = isset($filters['user_id']) && $filters['user_id'] !== '' && $filters['user_id'] !== null
            ? (int) $filters['user_id']
            : null;

        $query = $this->model->newQuery()->with(['causer', 'subject']);

        if ($global !== '') {
            $query->where(function (Builder $builder) use ($global): void {
                $builder
                    ->where('description', 'like', "%{$global}%")
                    ->orWhere('event', 'like', "%{$global}%")
                    ->orWhere('log_name', 'like', "%{$global}%")
                    ->orWhere('subject_type', 'like', "%{$global}%");

                if (ctype_digit($global)) {
                    $numericValue = (int) $global;

                    $builder
                        ->orWhere('id', $numericValue)
                        ->orWhere('subject_id', $numericValue)
                        ->orWhere('causer_id', $numericValue);
                }

                $builder->orWhereHasMorph('causer', [User::class], function (Builder $causerQuery) use ($global): void {
                    $causerQuery->where(function (Builder $userQuery) use ($global): void {
                        $userQuery
                            ->where('name', 'like', "%{$global}%")
                            ->orWhere('email', 'like', "%{$global}%");
                    });
                });
            });
        }

        if ($event !== '') {
            $query->where('event', 'like', "%{$event}%");
        }

        if ($userId !== null) {
            $query
                ->where('causer_type', User::class)
                ->where('causer_id', $userId);
        }

        if ($subjectType !== '') {
            $query->where(function (Builder $builder) use ($subjectType): void {
                $builder->where('subject_type', $subjectType);

                if (! str_contains($subjectType, '\\')) {
                    $builder->orWhere('subject_type', 'like', '%' . $subjectType);
                }
            });
        }

        return $query
            ->orderBy($sortField, $sortOrder)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param int $auditLogId
     * @return Activity
     */
    public function findById(int $auditLogId): Activity
    {
        $activity = $this->model->newQuery()
            ->with(['causer', 'subject'])
            ->find($auditLogId);

        if (! $activity instanceof Activity) {
            throw (new ModelNotFoundException())->setModel(Activity::class, [$auditLogId]);
        }

        return $activity;
    }
}
