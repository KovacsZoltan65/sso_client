<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Roles\IndexRoleRequest;
use App\Http\Requests\Roles\StoreRoleRequest;
use App\Http\Requests\Roles\UpdateRoleRequest;
use App\Services\RoleService;
use App\Support\ApiResponse;
use App\Support\ProtectedAuthorizationArtifacts;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(
        private readonly RoleService $roleService,
    ) {
    }

    /**
     * @param IndexRoleRequest $request
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function index(IndexRoleRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        $roles = $this->roleService->list($request->validated());

        return ApiResponse::success(
            'Roles retrieved successfully.',
            data: [
                'items' => $roles->getCollection()
                    ->map(fn (Role $role) => $this->toArray($role, $request))
                    ->values()
                    ->all(),
            ],
            meta: [
                'pagination' => [
                    'current_page' => $roles->currentPage(),
                    'last_page' => $roles->lastPage(),
                    'per_page' => $roles->perPage(),
                    'total' => $roles->total(),
                    'from' => $roles->firstItem(),
                    'to' => $roles->lastItem(),
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
     * @param StoreRoleRequest $request
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        $this->authorize('create', Role::class);

        $role = $this->roleService->store($request->validated());

        return ApiResponse::success(
            'Role created successfully.',
            data: [
                'role' => $this->toArray($role->loadMissing('permissions:id,name'), $request),
            ],
            status: 201,
        );
    }

    /**
     * @param UpdateRoleRequest $request
     * @param Role $role
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $this->authorize('update', $role);

        $role = $this->roleService->update((int) $role->id, $request->validated());

        return ApiResponse::success(
            'Role updated successfully.',
            data: [
                'role' => $this->toArray($role->loadMissing('permissions:id,name'), $request),
            ],
        );
    }

    /**
     * @param Request $request
     * @param Role $role
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function destroy(Request $request, Role $role): JsonResponse
    {
        $this->authorize('delete', $role);

        $this->roleService->delete((int) $role->id);

        return ApiResponse::success('Role deleted successfully.');
    }

    /**
     * @param Role $role
     * @param Request $request
     * @return array{
     *      can: array{
     *          delete: bool, 
     *          update: bool, 
     *          created_at: mixed, 
     *          guard_name: string, 
     *          id: int, 
     *          is_protected: bool, 
     *          name: string, 
     *          permission_ids: int[], 
     *          permission_names: array, 
     *          permissions_count: int, 
     *          protection_label: string|null, 
     *          updated_at: mixed
     *      }
     * }
     * @return array<string, mixed>
     */
    private function toArray(Role $role, Request $request): array
    {
        $role->loadMissing('permissions:id,name')->loadCount('permissions');
        $isProtected = ProtectedAuthorizationArtifacts::isProtectedRole($role);

        return [
            'id' => (int) $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'permissions_count' => (int) ($role->permissions_count ?? $role->permissions->count()),
            'permission_ids' => $role->permissions->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'permission_names' => $role->permissions->pluck('name')->values()->all(),
            'created_at' => optional($role->created_at)?->toDateTimeString(),
            'updated_at' => optional($role->updated_at)?->toDateTimeString(),
            'is_protected' => $isProtected,
            'protection_label' => $isProtected ? ProtectedAuthorizationArtifacts::protectionLabel() : null,
            'can' => [
                'update' => $request->user()?->can('update', $role) ?? false,
                'delete' => ($request->user()?->can('delete', $role) ?? false) && ! $isProtected,
            ],
        ];
    }
}
