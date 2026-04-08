<?php

namespace App\Services\Sso;

use App\Models\OidcSessionMapping;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OidcSessionContextService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly OidcSessionMappingCleanupService $mappingCleanupService,
    ) {
    }

    public function bind(Request $request, User $user, string $sid, ?string $issuer): void
    {
        $sid = trim($sid);

        if ($sid === '') {
            return;
        }

        OidcSessionMapping::query()->updateOrCreate(
            [
                'sid_hash' => $this->sidHash($sid),
                'session_id' => $request->session()->getId(),
            ],
            [
                'user_id' => $user->getKey(),
                'issuer' => $issuer,
                'client_id' => trim((string) config('sso.client_id')),
                'bound_at' => now(),
                'last_seen_at' => now(),
                'invalidated_at' => null,
            ],
        );

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_CLIENT_AUTH,
            event: 'client_auth.sid.bound',
            description: 'Client OIDC sid bound to the local auth session.',
            subject: $user,
            causer: $user,
            properties: [
                'target_local_user_id' => $user->getKey(),
                'has_sid' => true,
                'status' => 'bound',
                ...$this->auditLogService->requestContext($request),
            ],
        );
    }

    public function currentSessionSidMatches(Request $request, string $sid): bool
    {
        $sid = trim($sid);

        if ($sid === '') {
            return false;
        }

        $context = $request->session()->get(config('sso.oidc_session_context_key'));

        if (! is_array($context)) {
            return false;
        }

        $currentSid = trim((string) ($context['sid'] ?? ''));

        return $currentSid !== '' && hash_equals($currentSid, $sid);
    }

    public function clearSessionsForSid(Request $request, string $sid): int
    {
        $sid = trim($sid);

        if ($sid === '') {
            return 0;
        }

        $mappings = OidcSessionMapping::query()
            ->where('sid_hash', $this->sidHash($sid))
            ->whereNull('invalidated_at')
            ->get();

        if ($mappings->isEmpty()) {
            return 0;
        }

        $sessionIds = $mappings
            ->pluck('session_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $deletedSessions = 0;

        $this->mappingCleanupService->invalidateBySid($sid, $request);

        if ($sessionIds !== []) {
            $deletedSessions = DB::table((string) config('session.table', 'sessions'))
                ->whereIn('id', $sessionIds)
                ->delete();
        }

        if (in_array($request->session()->getId(), $sessionIds, true)) {
            Auth::guard('web')->logout();
            $this->forgetTransientOidcSessionState($request);
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return $deletedSessions;
    }

    public function forgetCurrentSession(Request $request): void
    {
        $this->mappingCleanupService->invalidateBySessionId($request->session()->getId(), $request);
    }

    public function sidHash(string $sid): string
    {
        return hash('sha256', trim($sid));
    }

    public function forgetTransientOidcSessionState(Request $request): void
    {
        $request->session()->forget(config('sso.pending_auth_session_key'));
        $request->session()->forget(config('sso.identity_validation_session_key'));
        $request->session()->forget(config('sso.oidc_session_context_key'));
        $request->session()->forget(config('sso.logout_state_session_key'));
    }
}
