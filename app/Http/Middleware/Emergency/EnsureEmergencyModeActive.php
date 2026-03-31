<?php

namespace App\Http\Middleware\Emergency;

use App\Services\Emergency\EmergencyModeService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmergencyModeActive
{
    public function __construct(
        private readonly EmergencyModeService $emergencyModeService,
    ) {
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->emergencyModeService->isEmergencyActive()) {
            return redirect()
                ->route('emergency.status')
                ->with('error', 'Emergency mode is not active.');
        }

        return $next($request);
    }
}
