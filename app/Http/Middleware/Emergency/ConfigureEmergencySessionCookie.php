<?php

namespace App\Http\Middleware\Emergency;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConfigureEmergencySessionCookie
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('emergency') || $request->is('emergency/*')) {
            config([
                'session.cookie' => (string) config('emergency.session_cookie', 'sso_client_emergency_session'),
            ]);
        }

        return $next($request);
    }
}
