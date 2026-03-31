<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\IndexUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\User;
use App\Services\UserAdminService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private readonly UserAdminService $userAdminService,
    ) {
    }

    /**
     * Felhasználói lista lekérése admin API válaszformátumban.
     */
    public function index(IndexUserRequest $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $users = $this->userAdminService->list($request->validated());

        return ApiResponse::success(
            'Users retrieved successfully.',
            data: [
                'items' => $users->getCollection()
                    ->map(fn (User $user) => $this->toArray($user, $request))
                    ->values()
                    ->all(),
            ],
            meta: [
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem(),
                ],
                'filters' => [
                    'global' => $request->validated('global'),
                    'local_status' => $request->validated('local_status'),
                    'has_sso_link' => $request->validated('has_sso_link'),
                    'sort_field' => $request->validated('sort_field', 'created_at'),
                    'sort_order' => $request->validated('sort_order', 'desc'),
                ],
            ],
        );
    }

    /**
     * Egy felhasználó részletes adatainak lekérése.
     */
    public function show(Request $request, User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return ApiResponse::success(
            'User retrieved successfully.',
            data: [
                'user' => $this->toArray($user->fresh(), $request),
            ],
        );
    }

    /**
     * Felhasználó helyi admin adatainak frissítése.
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $user = $this->userAdminService->update($user, $request->validated());

        return ApiResponse::success(
            'User updated successfully.',
            data: [
                'user' => $this->toArray($user, $request),
            ],
        );
    }

    /**
     * Az API válaszban használt felhasználói payload felépítése.
     *
     * @return array<string, mixed>
     */
    private function toArray(User $user, Request $request): array
    {
        return [
            'id' => $user->id,
            'sso_user_id' => $user->sso_user_id,
            'name' => $user->name,
            'email' => $user->email,
            'local_status' => $user->local_status,
            'notes' => $user->notes,
            'last_authenticated_at' => optional($user->last_authenticated_at)?->toDateTimeString(),
            'created_at' => optional($user->created_at)?->toDateTimeString(),
            'updated_at' => optional($user->updated_at)?->toDateTimeString(),
            'can' => [
                'view' => $request->user()?->can('view', $user) ?? false,
                'update' => $request->user()?->can('update', $user) ?? false,
            ],
        ];
    }
}
