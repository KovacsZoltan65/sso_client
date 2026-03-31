<?php

namespace App\Http\Middleware;

use App\Services\Auth\LocalFallbackAuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictFallbackSessionAccess
{
    public function __construct(
        private readonly LocalFallbackAuthService $localFallbackAuthService,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->localFallbackAuthService->isFallbackSession($request)) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        if ($routeName !== null && in_array($routeName, $this->localFallbackAuthService->allowedFallbackRouteNames(), true)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Forbidden.',
                'data' => (object) [],
                'meta' => [
                    'session_mode' => LocalFallbackAuthService::SESSION_MODE_LOCAL_FALLBACK,
                ],
                'errors' => (object) [],
            ], 403);
        }

        return redirect()
            ->route('dashboard')
            ->with('error', 'A local fallback session csak korlatozott, olvasasi szintu oldalakhoz ferhet hozza.');
    }
}
