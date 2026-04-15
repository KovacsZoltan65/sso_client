<?php

namespace App\Http\Middleware;

use App\Data\UserSummaryData;
use App\Services\Sso\SsoClientService;
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

        return [
            ...parent::share($request),
            'auth' => [
                'isAuthenticated' => $user !== null,
                'isGuest' => $user === null,
                'user' => $user ? UserSummaryData::fromModel($user)->toArray() : null,
                'loginUrl' => route('login'),
                'reauthUrl' => route('auth.sso.redirect'),
                'logoutUrl' => route('logout'),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'sso' => [
                'status' => fn () => app(SsoClientService::class)->status()->toArray(),
            ],
            'locale' => [
                'current' => app()->getLocale(),
                'fallback' => config('app.fallback_locale'),
                'available' => config('app.available_locales', ['hu', 'en']),
            ],
        ];
    }
}
