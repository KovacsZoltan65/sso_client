<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Sso\SsoReachabilityService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LocalFallbackAuthService
{
    public const SESSION_MODE_SSO = 'sso';
    public const SESSION_MODE_LOCAL_FALLBACK = 'local_fallback';
    public const SESSION_MODE_GUEST = 'guest';

    /**
     * @var array<int, string>
     */
    private const ALLOWED_FALLBACK_ROUTE_NAMES = [
        'dashboard',
        'account.show',
        'logout',
    ];

    /**
     * @var array<int, string>
     */
    private const FALLBACK_CAPABILITIES = [
        'dashboard.view',
        'account.view',
    ];

    public function __construct(
        private readonly SsoReachabilityService $reachabilityService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function isFeatureEnabled(): bool
    {
        return (bool) config('sso.local_auth_enabled', false);
    }

    public function requiresSsoUnreachable(): bool
    {
        return (bool) config('sso.local_auth_require_sso_unreachable', true);
    }

    public function allowsDegraded(): bool
    {
        return (bool) config('sso.local_auth_allow_degraded', false);
    }

    public function currentSessionMode(Request $request): string
    {
        if (! $request->user()) {
            return self::SESSION_MODE_GUEST;
        }

        return (string) $request->session()->get(config('sso.session_mode_session_key'), self::SESSION_MODE_SSO);
    }

    public function isFallbackSession(Request $request): bool
    {
        return $this->currentSessionMode($request) === self::SESSION_MODE_LOCAL_FALLBACK;
    }

    public function buildLoginDecisionData(Request $request): array
    {
        $reachability = $this->reachabilityService->current();
        $featureEnabled = $this->isFeatureEnabled();
        $incidentId = $this->incidentId();
        $incidentReady = ! $this->incidentIdRequired() || $incidentId !== null;
        $fallbackReason = $this->fallbackReasonFromReachability($reachability->status);
        $blockedBecauseHealthy = $featureEnabled && $reachability->status === SsoReachabilityService::STATUS_HEALTHY;
        $blockedBecauseMaintenance = $featureEnabled && $reachability->status === SsoReachabilityService::STATUS_MAINTENANCE;
        $blockedBecauseDegraded = $featureEnabled
            && $reachability->status === SsoReachabilityService::STATUS_DEGRADED
            && ! $this->allowsDegraded();
        $allowed = $featureEnabled
            && $incidentReady
            && (! $this->requiresSsoUnreachable() || $this->isAllowedReachabilityStatus($reachability->status));

        return [
            'featureEnabled' => $featureEnabled,
            'allowDegraded' => $this->allowsDegraded(),
            'requireSsoUnreachable' => $this->requiresSsoUnreachable(),
            'incidentIdRequired' => $this->incidentIdRequired(),
            'incidentId' => $incidentId,
            'currentlyAllowed' => $allowed,
            'fallbackReason' => $allowed ? $fallbackReason : null,
            'blockedBecauseSsoHealthy' => $blockedBecauseHealthy,
            'blockedBecauseMaintenance' => $blockedBecauseMaintenance,
            'blockedBecauseDegraded' => $blockedBecauseDegraded,
            'blockedBecauseIncidentMissing' => $featureEnabled && ! $incidentReady,
            'currentSessionMode' => $this->currentSessionMode($request),
            'reachability' => $reachability->toArray(),
            'warning' => $this->decisionWarning($featureEnabled, $allowed, $reachability->status, $incidentReady),
            'banner' => $this->fallbackBannerData($request),
        ];
    }

    public function isFallbackAllowed(Request $request, bool $forceRefresh = false): bool
    {
        if (! $this->isFeatureEnabled()) {
            return false;
        }

        if ($this->incidentIdRequired() && $this->incidentId() === null) {
            return false;
        }

        if (! $this->requiresSsoUnreachable()) {
            return true;
        }

        $reachability = $forceRefresh ? $this->reachabilityService->refresh() : $this->reachabilityService->current();

        return $this->isAllowedReachabilityStatus($reachability->status);
    }

    public function isFallbackBlockedBecauseSsoHealthy(Request $request, bool $forceRefresh = false): bool
    {
        if (! $this->isFeatureEnabled()) {
            return false;
        }

        $reachability = $forceRefresh ? $this->reachabilityService->refresh() : $this->reachabilityService->current();

        return $reachability->status === SsoReachabilityService::STATUS_HEALTHY;
    }

    /**
     * @return array{user: User, sessionMode: string}
     */
    public function attemptLogin(Request $request, array $credentials): array
    {
        $email = Str::lower((string) ($credentials['email'] ?? ''));
        $this->ensureRateLimit($request, $email);

        if (! $this->isFallbackAllowed($request, true)) {
            $reachability = $this->reachabilityService->current();
            $reason = $this->blockedAttemptReason($reachability->status);

            $this->auditLogService->logFailure(
                logName: AuditLogService::LOG_CLIENT_AUTH,
                event: 'client_auth.local_fallback.login_failed',
                description: 'Local fallback login failed because the SSO server is reachable.',
                properties: [
                    'reason' => $reason,
                    'fallback_mode' => 'blocked',
                    'reachability_state' => $reachability->status,
                    'fallback_reason' => $this->fallbackReasonFromReachability($reachability->status),
                    'allow_degraded_enabled' => $this->allowsDegraded(),
                    'session_mode' => self::SESSION_MODE_GUEST,
                    'incident_id' => $this->incidentId(),
                    'failure_count' => $reachability->failureCount,
                    ...$this->auditLogService->requestContext($request),
                ],
            );

            throw ValidationException::withMessages([
                'email' => $this->blockedAttemptMessage($reachability->status),
            ]);
        }

        $user = User::query()
            ->where('email', $email)
            ->whereNull('sso_user_id')
            ->where('local_status', 'active')
            ->where('fallback_auth_enabled', true)
            ->first();

        if (! $user instanceof User || ! Hash::check((string) ($credentials['password'] ?? ''), (string) $user->password)) {
            RateLimiter::hit($this->throttleKey($request, $email));
            $reachability = $this->reachabilityService->current();

            $this->auditLogService->logFailure(
                logName: AuditLogService::LOG_CLIENT_AUTH,
                event: 'client_auth.local_fallback.login_failed',
                description: 'Local fallback login failed.',
                properties: [
                    'reason' => 'invalid_credentials_or_not_allowlisted',
                    'fallback_mode' => 'active',
                    'reachability_state' => $reachability->status,
                    'fallback_reason' => $this->fallbackReasonFromReachability($reachability->status),
                    'allow_degraded_enabled' => $this->allowsDegraded(),
                    'session_mode' => self::SESSION_MODE_GUEST,
                    'incident_id' => $this->incidentId(),
                    'failure_count' => $reachability->failureCount,
                    ...$this->auditLogService->requestContext($request),
                ],
            );

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey($request, $email));

        Auth::login($user, remember: false);
        $request->session()->regenerate();
        $request->session()->put(config('sso.session_mode_session_key'), self::SESSION_MODE_LOCAL_FALLBACK);
        $reachability = $this->reachabilityService->current();

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_CLIENT_AUTH,
            event: 'client_auth.local_fallback.login_succeeded',
            description: 'Local fallback login succeeded.',
            subject: $user,
            causer: $user,
            properties: [
                'fallback_mode' => 'active',
                'reachability_state' => $reachability->status,
                'fallback_reason' => $this->fallbackReasonFromReachability($reachability->status),
                'allow_degraded_enabled' => $this->allowsDegraded(),
                'session_mode' => self::SESSION_MODE_LOCAL_FALLBACK,
                'incident_id' => $this->incidentId(),
                'failure_count' => $reachability->failureCount,
                ...$this->auditLogService->requestContext($request),
            ],
        );

        return [
            'user' => $user,
            'sessionMode' => self::SESSION_MODE_LOCAL_FALLBACK,
        ];
    }

    public function logout(Request $request, ?User $user = null): void
    {
        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_CLIENT_AUTH,
            event: 'client_auth.local_fallback.logout',
            description: 'Local fallback logout completed.',
            subject: $user,
            causer: $user,
            properties: [
                'fallback_mode' => 'inactive',
                'session_mode' => self::SESSION_MODE_LOCAL_FALLBACK,
                'incident_id' => $this->incidentId(),
                ...$this->auditLogService->requestContext($request),
            ],
        );
    }

    /**
     * @return array<int, string>
     */
    public function fallbackCapabilities(): array
    {
        return self::FALLBACK_CAPABILITIES;
    }

    /**
     * @return array<int, string>
     */
    public function allowedFallbackRouteNames(): array
    {
        return self::ALLOWED_FALLBACK_ROUTE_NAMES;
    }

    public function fallbackBannerData(Request $request): ?array
    {
        if (! $this->isFallbackSession($request)) {
            return null;
        }

        $reachability = $this->reachabilityService->current();

        return [
            'visible' => true,
            'severity' => 'warning',
            'title' => 'Helyi hitelesites aktiv',
            'message' => $reachability->status === SsoReachabilityService::STATUS_DEGRADED
                ? 'Fallback mod aktiv (SSO allapot: degraded). Korlatozott local hitelesites fut.'
                : 'SSO szerver nem erheto el. Korlatozott fallback mod fut.',
            'fallbackReason' => $this->fallbackReasonFromReachability($reachability->status),
            'sessionMode' => self::SESSION_MODE_LOCAL_FALLBACK,
            'incidentId' => $this->incidentId(),
        ];
    }

    public function incidentId(): ?string
    {
        $incidentId = trim((string) config('sso.local_auth_incident_id'));

        return $incidentId === '' ? null : $incidentId;
    }

    public function incidentIdRequired(): bool
    {
        return (bool) config('sso.local_auth_incident_id_required', true);
    }

    private function decisionWarning(bool $featureEnabled, bool $allowed, string $status, bool $incidentReady): ?string
    {
        if (! $featureEnabled) {
            return null;
        }

        if (! $incidentReady) {
            return 'A local fallback engedelyezett, de nincs rogzitett incident azonosito, ezert jelenleg tiltott.';
        }

        if ($status === SsoReachabilityService::STATUS_MAINTENANCE) {
            return 'Az SSO szerver jelenleg karbantartas alatt van. A normal belepes ideiglenesen nem erheto el.';
        }

        if ($status === SsoReachabilityService::STATUS_HEALTHY) {
            return 'A local fallback engedelyezve van, de jelenleg tiltott, mert az SSO szerver elerheto. Javasolt a konfiguracio visszaallitasa.';
        }

        if ($allowed) {
            if ($status === SsoReachabilityService::STATUS_DEGRADED) {
                return 'Az SSO szerver jelenleg reszben hibas allapotban van, ezert a korlatozott local fallback mod ideiglenesen engedelyezett.';
            }

            return 'Az SSO szerver jelenleg nem erheto el, ezert a korlatozott local fallback mod hasznalhato.';
        }

        if ($status === SsoReachabilityService::STATUS_DEGRADED) {
            return 'Az SSO szerver reszben hibas allapotban van, de a degraded fallback nincs engedelyezve ebben a kornyezetben.';
        }

        return null;
    }

    private function isAllowedReachabilityStatus(string $status): bool
    {
        if ($status === SsoReachabilityService::STATUS_UNREACHABLE) {
            return true;
        }

        if ($status === SsoReachabilityService::STATUS_DEGRADED && $this->allowsDegraded()) {
            return true;
        }

        return false;
    }

    private function fallbackReasonFromReachability(string $status): ?string
    {
        return match ($status) {
            SsoReachabilityService::STATUS_UNREACHABLE => 'unreachable_allowed',
            SsoReachabilityService::STATUS_DEGRADED => $this->allowsDegraded() ? 'degraded_allowed' : 'degraded_blocked',
            SsoReachabilityService::STATUS_MAINTENANCE => 'maintenance_blocked',
            SsoReachabilityService::STATUS_HEALTHY => 'healthy_blocked',
            default => null,
        };
    }

    private function blockedAttemptReason(string $status): string
    {
        return match ($status) {
            SsoReachabilityService::STATUS_MAINTENANCE => 'sso_maintenance',
            SsoReachabilityService::STATUS_DEGRADED => 'sso_degraded_blocked',
            SsoReachabilityService::STATUS_HEALTHY => 'sso_reachable',
            default => 'fallback_not_allowed',
        };
    }

    private function blockedAttemptMessage(string $status): string
    {
        return match ($status) {
            SsoReachabilityService::STATUS_MAINTENANCE => 'A local fallback login jelenleg tiltott, mert az SSO szerver karbantartas alatt van.',
            SsoReachabilityService::STATUS_DEGRADED => 'A local fallback login jelenleg tiltott, mert a degraded fallback nincs engedelyezve.',
            SsoReachabilityService::STATUS_HEALTHY => 'A local fallback login mar nem erheto el, mert az SSO szerver kozben ujra elerhetove valt.',
            default => 'A local fallback login jelenleg nem engedelyezett.',
        };
    }

    private function ensureRateLimit(Request $request, string $email): void
    {
        $key = $this->throttleKey($request, $email);

        if (! RateLimiter::tooManyAttempts($key, 5)) {
            return;
        }

        event(new Lockout($request));

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    private function throttleKey(Request $request, string $email): string
    {
        return Str::transliterate(Str::lower($email).'|'.$request->ip().'|local-fallback');
    }
}
