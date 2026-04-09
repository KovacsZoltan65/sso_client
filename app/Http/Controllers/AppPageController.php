<?php

namespace App\Http\Controllers;

use App\Services\Sso\SsoClientService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

class AppPageController extends Controller
{
    public function __construct(
        private readonly SsoClientService $ssoClientService,
    ) {
    }

    public function myAccount(Request $request): Response
    {
        return Inertia::render('Account/Show', [
            'account' => [
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'roles' => $request->user()->getRoleNames()->values()->all(),
                'permissions' => $request->user()->getAllPermissions()->pluck('name')->values()->all(),
            ],
        ]);
    }

    public function users(Request $request): Response
    {
        return Inertia::render('Users/Index', [
            'usersApi' => [
                'endpoints' => [
                    'index' => route('api.users.index'),
                ],
            ],
            'permissions' => [
                'view' => $request->user()?->can('users.view') ?? false,
                'manage' => $request->user()?->can('users.manage') ?? false,
            ],
        ]);
    }

    public function companies(Request $request): Response
    {
        return Inertia::render('Companies/Index', [
            'companiesApi' => [
                'endpoints' => [
                    'index' => route('api.companies.index'),
                    'store' => route('api.companies.store'),
                ],
            ],
            'permissions' => [
                'view' => $request->user()?->can('companies.view') ?? false,
                'create' => $request->user()?->can('companies.create') ?? false,
                'update' => $request->user()?->can('companies.update') ?? false,
                'delete' => $request->user()?->can('companies.delete') ?? false,
            ],
        ]);
    }

    public function roles(Request $request): Response
    {
        return Inertia::render('Roles/Index', [
            'rolesApi' => [
                'endpoints' => [
                    'index' => route('api.roles.index'),
                    'store' => route('api.roles.store'),
                ],
            ],
            'permissions' => [
                'view' => $request->user()?->can('roles.view') ?? false,
                'create' => $request->user()?->can('roles.create') ?? false,
                'update' => $request->user()?->can('roles.update') ?? false,
                'delete' => $request->user()?->can('roles.delete') ?? false,
            ],
            'permissionOptions' => Permission::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Permission $permission) => [
                    'value' => (int) $permission->id,
                    'label' => Str::headline((string) Str::of($permission->name)->afterLast('.')),
                    'helper' => $permission->name,
                    'groupKey' => (string) Str::of($permission->name)->before('.'),
                    'groupLabel' => Str::headline((string) Str::of($permission->name)->before('.')->replace(['-', '_'], ' ')),
                    'action' => (string) Str::of($permission->name)->afterLast('.'),
                    'itemLabel' => Str::headline((string) Str::of($permission->name)->afterLast('.')),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function permissions(Request $request): Response
    {
        return Inertia::render('Permissions/Index', [
            'permissionsApi' => [
                'endpoints' => [
                    'index' => route('api.permissions.index'),
                    'store' => route('api.permissions.store'),
                ],
            ],
            'permissions' => [
                'view' => $request->user()?->can('permissions.view') ?? false,
                'create' => $request->user()?->can('permissions.create') ?? false,
                'update' => $request->user()?->can('permissions.update') ?? false,
                'delete' => $request->user()?->can('permissions.delete') ?? false,
            ],
        ]);
    }

    public function ssoStatus(): Response
    {
        return Inertia::render('Sso/Status', [
            'status' => $this->ssoClientService->status()->toArray(),
            'capabilities' => [
                'Redirect users to the SSO server',
                'Handle signed callback state',
                'Resolve authenticated user context',
                'Persist local authorization assignments',
            ],
        ]);
    }

    public function auditLogs(): Response
    {
        return Inertia::render('AuditLogs/Index', [
            'entries' => Activity::query()
                ->latest()
                ->limit(12)
                ->get()
                ->map(fn (Activity $activity) => [
                    'id' => $activity->id,
                    'log_name' => $activity->log_name,
                    'description' => $activity->description,
                    'event' => $activity->event,
                    'subject_type' => class_basename((string) $activity->subject_type),
                    'causer' => $activity->causer?->only(['id', 'name', 'email']),
                    'created_at' => optional($activity->created_at)?->toDateTimeString(),
                ]),
        ]);
    }
}
