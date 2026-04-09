<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Permissions\IndexPermissionRequest;
use App\Http\Requests\Permissions\StorePermissionRequest;
use App\Http\Requests\Permissions\UpdatePermissionRequest;
use App\Services\PermissionService;
use App\Support\ApiResponse;
use App\Support\ProtectedAuthorizationArtifacts;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function __construct(
        private readonly PermissionService $permissionService,
    ) {
    }

    /**
     * @param IndexPermissionRequest $request
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function index(IndexPermissionRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Permission::class);

        $permissions = $this->permissionService->list($request->validated());

        return ApiResponse::success(
            'Permissions retrieved successfully.',
            data: [
                'items' => collect($permissions->items())
                    ->map(fn (Permission $permission) => $this->toArray($permission, $request))
                    ->values()
                    ->all(),
            ],
            meta: [
                'pagination' => [
                    'current_page' => $permissions->currentPage(),
                    'last_page' => $permissions->lastPage(),
                    'per_page' => $permissions->perPage(),
                    'total' => $permissions->total(),
                    'from' => $permissions->firstItem(),
                    'to' => $permissions->lastItem(),
                ],
                'filters' => [
                    'search' => $request->validated('search'),
                    'sort_field' => $request->validated('sort_field', 'created_at'),
                    'sort_order' => $request->validated('sort_order', 'desc'),
                ],
            ],
        );
    }

    /**
     * @param StorePermissionRequest $request
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function store(StorePermissionRequest $request): JsonResponse
    {
        $this->authorize('create', Permission::class);

        $permission = $this->permissionService->store($request->validated());

        return ApiResponse::success(
            'Permission created successfully.',
            data: [
                'permission' => $this->toArray($permission, $request),
            ],
            status: 201,
        );
    }

    /**
     * @param UpdatePermissionRequest $request
     * @param Permission $permission
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function update(UpdatePermissionRequest $request, Permission $permission): JsonResponse
    {
        $this->authorize('update', $permission);

        $permission = $this->permissionService->update((int) $permission->id, $request->validated());

        return ApiResponse::success(
            'Permission updated successfully.',
            data: [
                'permission' => $this->toArray($permission, $request),
            ],
        );
    }

    /**
     * @param Request $request
     * @param Permission $permission
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function destroy(Request $request, Permission $permission): JsonResponse
    {
        $this->authorize('delete', $permission);

        $this->permissionService->delete((int) $permission->id);

        return ApiResponse::success('Permission deleted successfully.');
    }

    /**
     * @param Permission $permission
     * @param Request $request
     * @return array<string, mixed>
     */
    private function toArray(Permission $permission, Request $request): array
    {
        $permission->loadCount('roles');
        $isProtected = ProtectedAuthorizationArtifacts::isProtectedPermission($permission);

        return [
            'id' => (int) $permission->id,
            'name' => $permission->name,
            'guard_name' => $permission->guard_name,
            'roles_count' => (int) ($permission->roles_count ?? 0),
            'created_at' => optional($permission->created_at)?->toDateTimeString(),
            'updated_at' => optional($permission->updated_at)?->toDateTimeString(),
            'is_protected' => $isProtected,
            'protection_label' => $isProtected ? ProtectedAuthorizationArtifacts::protectionLabel() : null,
            'can' => [
                'update' => $request->user()?->can('update', $permission) ?? false,
                'delete' => ($request->user()?->can('delete', $permission) ?? false) && ! $isProtected,
            ],
        ];
    }
}

