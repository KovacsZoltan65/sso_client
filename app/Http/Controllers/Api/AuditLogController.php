<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuditLogs\IndexAuditLogRequest;
use App\Services\Audit\AuditLogQueryService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
                    ->map(fn (Activity $activity) => $this->toIndexArray($activity))
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

    public function show(Request $request, int $auditLog): JsonResponse
    {
        $activity = $this->auditLogQueryService->show($auditLog);

        $this->authorize('view', $activity);

        return ApiResponse::success(
            'Audit log retrieved successfully.',
            data: [
                'audit_log' => $this->toDetailArray($activity),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toIndexArray(Activity $activity): array
    {
        return [
            'id' => $activity->id,
            'log_name' => $activity->log_name,
            'description' => $activity->description,
            'event' => $activity->event,
            'subject_type' => $activity->subject_type ? class_basename((string) $activity->subject_type) : null,
            'subject_id' => $activity->subject_id,
            'causer' => $this->causerArray($activity),
            'created_at' => optional($activity->created_at)?->toDateTimeString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toDetailArray(Activity $activity): array
    {
        $properties = $activity->properties?->toArray() ?? [];

        return [
            'id' => $activity->id,
            'event' => $activity->event,
            'description' => $activity->description,
            'log_name' => $activity->log_name,
            'subject_type' => $activity->subject_type ? class_basename((string) $activity->subject_type) : null,
            'subject_type_fqn' => $activity->subject_type,
            'subject_id' => $activity->subject_id,
            'subject' => $this->subjectArray($activity->subject),
            'causer' => $this->causerArray($activity),
            'properties' => $properties,
            'context' => [
                'ip_address' => Arr::get($properties, 'ip_address'),
                'user_agent' => Arr::get($properties, 'user_agent'),
                'route' => Arr::get($properties, 'route'),
                'reason' => Arr::get($properties, 'reason'),
                'status' => Arr::get($properties, 'status'),
                'result' => Arr::get($properties, 'result'),
            ],
            'created_at' => optional($activity->created_at)?->toDateTimeString(),
            'updated_at' => optional($activity->updated_at)?->toDateTimeString(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function causerArray(Activity $activity): ?array
    {
        $causer = $activity->causer;
        $displayName = Arr::first([
            $causer?->getAttribute('name'),
            $causer?->getAttribute('email'),
        ]);

        if (! $causer instanceof Model) {
            return null;
        }

        return [
            'id' => $causer->getAttribute('id'),
            'name' => $causer->getAttribute('name'),
            'email' => $causer->getAttribute('email'),
            'display' => $displayName,
            'type' => class_basename($causer::class),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function subjectArray(?Model $subject): ?array
    {
        if (! $subject instanceof Model) {
            return null;
        }

        return [
            'id' => $subject->getAttribute('id'),
            'type' => class_basename($subject::class),
            'display' => Arr::first([
                $subject->getAttribute('name'),
                $subject->getAttribute('email'),
                $subject->getAttribute('title'),
            ]),
        ];
    }
}
