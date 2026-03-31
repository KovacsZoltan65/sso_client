<?php

namespace App\Http\Controllers\Emergency;

use App\Http\Controllers\Controller;
use App\Http\Requests\Emergency\EmergencyDeactivateRequest;
use App\Models\EmergencyAccount;
use App\Services\Emergency\EmergencyModeService;
use Illuminate\Http\RedirectResponse;

class EmergencyActivationController extends Controller
{
    public function __construct(
        private readonly EmergencyModeService $emergencyModeService,
    ) {
    }

    public function destroy(EmergencyDeactivateRequest $request): RedirectResponse
    {
        /** @var EmergencyAccount $account */
        $account = auth('emergency')->user();

        $this->emergencyModeService->deactivate(
            reason: (string) $request->validated('reason'),
            operator: (string) ($request->validated('operator') ?: $account->username),
            request: $request,
        );

        auth('emergency')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('emergency.status')
            ->with('success', 'Emergency mode deactivated.');
    }
}
