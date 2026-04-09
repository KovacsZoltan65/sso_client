<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuditLogs\IndexAuditLogRequest;
use App\Services\Audit\AuditLogQueryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Spatie\Activitylog\Models\Activity;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly AuditLogQueryService $auditLogQueryService,
    ) {
    }

    public function index(IndexAuditLogRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Activity::class);

        $auditLogs = $this->auditLogQueryService->list($request->validated());

        return ApiResponse::success(
            'Audit logs retrieved successfully.',
            data: [
                'items' => collect($auditLogs->items())
                    ->map(fn (Activity $activity) => $this->toArray($activity))
                    ->values()
                    ->all(),
            ],
            meta: [
                'pagination' => [
                    'current_page' => $auditLogs->currentPage(),
                    'last_page' => $auditLogs->lastPage(),
                    'per_page' => $auditLogs->perPage(),
                    'total' => $auditLogs->total(),
                    'from' => $auditLogs->firstItem(),
                    'to' => $auditLogs->lastItem(),
                ],
                'filters' => [
                    'global' => $request->validated('global'),
                    'event' => $request->validated('event'),
                    'user_id' => $request->validated('user_id'),
                    'subject_type' => $request->validated('subject_type'),
                    'sort_field' => $request->validated('sort_field', 'created_at'),
                    'sort_order' => $request->validated('sort_order', 'desc'),
                ],
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(Activity $activity): array
    {
        $causer = $activity->causer;
        $displayName = Arr::first([
            $causer?->getAttribute('name'),
            $causer?->getAttribute('email'),
        ]);

        return [
            'id' => $activity->id,
            'log_name' => $activity->log_name,
            'description' => $activity->description,
            'event' => $activity->event,
            'subject_type' => $activity->subject_type ? class_basename((string) $activity->subject_type) : null,
            'subject_id' => $activity->subject_id,
            'causer' => $causer ? [
                'id' => $causer->getAttribute('id'),
                'name' => $causer->getAttribute('name'),
                'email' => $causer->getAttribute('email'),
                'display' => $displayName,
            ] : null,
            'created_at' => optional($activity->created_at)?->toDateTimeString(),
        ];
    }
}
