<?php

namespace App\Http\Controllers\Emergency;

use App\Data\Emergency\EmergencyLoginData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Emergency\EmergencyLoginRequest;
use App\Services\Emergency\EmergencyAuthService;
use App\Services\Emergency\EmergencyStatusService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EmergencySessionController extends Controller
{
    public function __construct(
        private readonly EmergencyAuthService $emergencyAuthService,
        private readonly EmergencyStatusService $emergencyStatusService,
    ) {
    }

    public function create(): Response
    {
        return Inertia::render('Emergency/Login', [
            'status' => $this->emergencyStatusService->status()->toArray(),
        ]);
    }

    public function store(EmergencyLoginRequest $request): RedirectResponse
    {
        $this->emergencyAuthService->login(
            EmergencyLoginData::from($request->validated()),
            $request,
        );

        return redirect()
            ->route('emergency.dashboard')
            ->with('success', 'Emergency access granted.');
    }

    public function destroy(): RedirectResponse
    {
        $this->emergencyAuthService->logout(request());

        return redirect()
            ->route('emergency.status')
            ->with('success', 'Emergency session closed.');
    }
}
