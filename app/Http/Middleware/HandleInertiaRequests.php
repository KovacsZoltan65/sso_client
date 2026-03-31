<?php

namespace App\Http\Middleware;

use App\Data\UserSummaryData;
use App\Services\Auth\LocalFallbackAuthService;
use App\Services\Sso\SsoClientService;
use App\Services\Sso\SsoReachabilityService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $localFallbackAuthService = app(LocalFallbackAuthService::class);
        $sessionMode = $localFallbackAuthService->currentSessionMode($request);
        $decision = $localFallbackAuthService->buildLoginDecisionData($request);
        $reachability = app(SsoReachabilityService::class)->current()->toArray();

        return [
            ...parent::share($request),
            'auth' => [
                'isAuthenticated' => $user !== null,
                'isGuest' => $user === null,
                'sessionMode' => $sessionMode,
                'user' => $user ? UserSummaryData::fromModel(
                    $user,
                    $sessionMode,
                    $sessionMode === LocalFallbackAuthService::SESSION_MODE_LOCAL_FALLBACK
                        ? $localFallbackAuthService->fallbackCapabilities()
                        : [],
                )->toArray() : null,
                'loginUrl' => route('login'),
                'reauthUrl' => route('auth.sso.redirect'),
                'logoutUrl' => route('logout'),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'sso' => [
                'status' => $reachability['status'] ?? 'healthy',
                'reason' => $reachability['reason'] ?? null,
                'retryAfter' => $reachability['retryAfter'] ?? null,
                'isMaintenance' => $reachability['isMaintenance'] ?? false,
                'isReachable' => $reachability['isReachable'] ?? true,
                'details' => fn () => app(SsoClientService::class)->status()->toArray(),
            ],
            'fallback' => [
                'featureEnabled' => $decision['featureEnabled'],
                'currentlyAllowed' => $decision['currentlyAllowed'],
                'blockedBecauseSsoHealthy' => $decision['blockedBecauseSsoHealthy'],
                'blockedBecauseIncidentMissing' => $decision['blockedBecauseIncidentMissing'],
                'warning' => $decision['warning'],
                'banner' => $decision['banner'],
                'incidentId' => $decision['incidentId'],
                'reachability' => $reachability,
            ],
        ];
    }
}
