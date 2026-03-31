<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpFoundation\Response;
use App\Support\ApiResponse;
use App\Services\Audit\AuditLogService;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $exception, Request $request): Response {
            if ($request->expectsJson()) {
                app(AuditLogService::class)->logFailure(
                    logName: AuditLogService::LOG_CLIENT_API,
                    event: 'client_api.request.unauthorized',
                    description: 'Client API request unauthorized.',
                    causer: $request->user(),
                    properties: [
                        'reauth_reason' => 'authentication_required',
                        'api_endpoint' => $request->path(),
                        'http_status' => 401,
                        ...app(AuditLogService::class)->requestContext($request),
                    ],
                );

                return ApiResponse::error(
                    'Authentication required.',
                    401,
                    meta: [
                        'redirect_to' => route('login'),
                        'reauth_to' => route('auth.sso.redirect'),
                    ],
                );
            }

            app(AuditLogService::class)->logFailure(
                logName: AuditLogService::LOG_CLIENT_AUTH,
                event: 'client_auth.reauth.redirected',
                description: 'Client reauthentication redirect triggered.',
                causer: $request->user(),
                properties: [
                    'reauth_reason' => 'missing_or_expired_session',
                    'redirect_target' => route('auth.sso.redirect'),
                    ...app(AuditLogService::class)->requestContext($request),
                ],
            );

            return redirect()
                ->guest(route('login'))
                ->with('error', 'A munkamenet hianyzik vagy lejart. Jelentkezz be ujra.');
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request): Response {
            if ($request->expectsJson()) {
                app(AuditLogService::class)->logFailure(
                    logName: AuditLogService::LOG_CLIENT_API,
                    event: 'client_api.request.forbidden',
                    description: 'Client API request forbidden.',
                    causer: $request->user(),
                    properties: [
                        'reason' => 'authorization_denied',
                        'api_endpoint' => $request->path(),
                        'http_status' => 403,
                        ...app(AuditLogService::class)->requestContext($request),
                    ],
                );

                return ApiResponse::error('Forbidden.', 403);
            }

            return redirect()
                ->back()
                ->with('error', 'Nincs jogosultsagod a kert oldal megtekintesehez.');
        });

        $exceptions->render(function (UnauthorizedException $exception, Request $request): Response {
            if ($request->expectsJson()) {
                app(AuditLogService::class)->logFailure(
                    logName: AuditLogService::LOG_CLIENT_API,
                    event: 'client_api.request.forbidden',
                    description: 'Client API request forbidden.',
                    causer: $request->user(),
                    properties: [
                        'reason' => 'permission_denied',
                        'api_endpoint' => $request->path(),
                        'http_status' => 403,
                        ...app(AuditLogService::class)->requestContext($request),
                    ],
                );

                return ApiResponse::error('Forbidden.', 403);
            }

            return redirect()
                ->back()
                ->with('error', 'Nincs jogosultsagod a kert oldal megtekintesehez.');
        });

        $exceptions->render(function (ValidationException $exception, Request $request): ?Response {
            if (! $request->expectsJson()) {
                return null;
            }

            return ApiResponse::error(
                'Validation failed.',
                422,
                errors: $exception->errors(),
            );
        });
    })->create();
