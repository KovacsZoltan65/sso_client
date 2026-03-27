<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Sso\SsoClientService;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(SsoClientService $ssoClientService): Response
    {
        return Inertia::render('Auth/Login', [
            'loginUrl' => route('auth.sso.redirect'),
            'status' => session('status'),
            'ssoStatus' => $ssoClientService->status()->toArray(),
        ]);
    }
}
