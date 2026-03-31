<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\SsoAuthenticationException;
use App\Http\Controllers\Controller;
use App\Services\Sso\SsoClientService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SsoAuthController extends Controller
{
    public function __construct(
        private readonly SsoClientService $ssoClientService,
    ) {
    }

    /**
     * Az SSO authorize flow indítása és átirányítás az SSO szerverre.
     */
    public function redirect(Request $request): RedirectResponse
    {
        try {
            return redirect()->away($this->ssoClientService->buildAuthorizationUrl($request));
        } catch (SsoAuthenticationException $exception) {
            return redirect()
                ->route('login')
                ->with('error', $exception->getMessage());
        }
    }

    /**
     * Az SSO callback feldolgozása és a helyi session létrehozása.
     */
    public function callback(Request $request): RedirectResponse
    {
        try {
            $this->ssoClientService->authenticateFromCallback($request);
        } catch (SsoAuthenticationException $exception) {
            Log::warning('SSO authentication callback failed.', [
                'status' => $exception->status(),
                'reason' => $exception->getMessage(),
                'has_code' => $request->filled('code'),
                'has_state' => $request->filled('state'),
                'provider_error' => $request->string('error')->toString() ?: null,
                ...$exception->context(),
            ]);

            return redirect()
                ->route('login')
                ->with('error', $exception->getMessage());
        }

        return redirect()->intended(route('dashboard', absolute: false))
            ->with('success', 'Sikeres SSO bejelentkezes.');
    }

    /**
     * A helyi session kijelentkeztetése az SSO kliensben.
     */
    public function logout(Request $request): RedirectResponse
    {
        $this->ssoClientService->logout($request);

        return redirect('/')
            ->with('success', 'Sikeres kijelentkezes.');
    }
}
