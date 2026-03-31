<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LocalFallbackLoginRequest;
use App\Services\Auth\LocalFallbackAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LocalFallbackSessionController extends Controller
{
    public function __construct(
        private readonly LocalFallbackAuthService $localFallbackAuthService,
    ) {
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Auth/LocalFallbackLogin', [
            'decision' => $this->localFallbackAuthService->buildLoginDecisionData($request),
            'status' => session('status'),
        ]);
    }

    public function store(LocalFallbackLoginRequest $request): RedirectResponse
    {
        $this->localFallbackAuthService->attemptLogin($request, $request->validated());

        return redirect()->intended(route('dashboard', absolute: false))
            ->with('success', 'Sikeres local fallback bejelentkezes.');
    }
}
