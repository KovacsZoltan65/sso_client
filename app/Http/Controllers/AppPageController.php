<?php

namespace App\Http\Controllers;

use App\Services\RolePageDataService;
use App\Services\Sso\SsoClientService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AppPageController extends Controller
{
    public function __construct(
        private readonly SsoClientService $ssoClientService,
        private readonly RolePageDataService $rolePageDataService,
    ) {
    }

    /**
     * @param Request $request
     * @return \Inertia\Response
     */
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

    /**
     * @param Request $request
     * @return \Inertia\Response
     */
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

    /**
     * @param Request $request
     * @return \Inertia\Response
     */
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

    /**
     * @param Request $request
     * @return \Inertia\Response
     */
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
            'permissionOptions' => $this->rolePageDataService->permissionOptions(),
        ]);
    }

    /**
     * @param Request $request
     * @return \Inertia\Response
     */
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

    /**
     * @return \Inertia\Response
     */
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

    /**
     * @param Request $request
     * @return \Inertia\Response
     */
    public function auditLogs(Request $request): Response
    {
        return Inertia::render('AuditLogs/Index', [
            'auditLogsApi' => [
                'endpoints' => [
                    'index' => route('api.audit-logs.index'),
                ],
            ],
            'permissions' => [
                'view' => $request->user()?->can('audit-logs.view') ?? false,
            ],
        ]);
    }
}
