<?php

namespace App\Http\Middleware;

use App\Data\UserSummaryData;
use App\Models\User;
use App\Services\Emergency\EmergencyStatusService;
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
        $authUser = $user instanceof User ? $user : null;

        return [
            ...parent::share($request),
            'auth' => [
                'isAuthenticated' => $authUser !== null,
                'isGuest' => $authUser === null,
                'user' => $authUser ? UserSummaryData::fromModel($authUser)->toArray() : null,
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
            'emergency' => [
                'status' => fn () => app(EmergencyStatusService::class)->status()->toArray(),
            ],
        ];
    }
}
