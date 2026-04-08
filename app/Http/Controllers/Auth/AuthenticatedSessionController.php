<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Sso\SsoClientService;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function __construct(
            private readonly SsoClientService $ssoClientService
    ) {}
    
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'loginUrl' => route('auth.sso.redirect'),
            'status' => session('status'),
            'ssoStatus' => $this->ssoClientService->status()->toArray(),
        ]);
    }
}
