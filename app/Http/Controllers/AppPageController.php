<?php

namespace App\Http\Controllers;

use App\Services\Sso\SsoClientService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

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

    public function users(): Response
    {
        return $this->placeholder(
            title: 'Users',
            description: 'This module will later use SSO-backed user synchronization and local role-aware administration.',
            tags: ['Directory', 'Repository ready', 'Permission gated'],
        );
    }

    public function roles(): Response
    {
        return $this->placeholder(
            title: 'Roles',
            description: 'Role management will sit on top of spatie/laravel-permission and stay independent from the upstream SSO source of truth.',
            tags: ['RBAC', 'Seeded base roles', 'Admin only'],
        );
    }

    public function permissions(): Response
    {
        return $this->placeholder(
            title: 'Permissions',
            description: 'Permission visibility is already wired in the UI; this page is the placeholder for future granular administration.',
            tags: ['Spatie', 'Convention based', 'UI aware'],
        );
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

    /**
     * @param  array<int, string>  $tags
     */
    private function placeholder(string $title, string $description, array $tags): Response
    {
        return Inertia::render('App/PlaceholderPage', [
            'title' => $title,
            'description' => $description,
            'tags' => $tags,
        ]);
    }
}
