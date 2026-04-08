<?php

namespace App\Services\Sso;

use App\Models\OidcLogoutReceipt;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class OidcBackChannelLogoutReceiptService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * @param array<string, mixed> $claims
     */
    public function hasProcessedJti(array $claims): bool
    {
        $jtiHash = $this->jtiHash((string) ($claims['jti'] ?? ''));

        if ($jtiHash === null) {
            return false;
        }

        return OidcLogoutReceipt::query()
            ->where('jti_hash', $jtiHash)
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * @param array<string, mixed> $claims
     */
    public function markProcessed(array $claims, string $outcome): void
    {
        $jtiHash = $this->jtiHash((string) ($claims['jti'] ?? ''));

        if ($jtiHash === null) {
            return;
        }

        $expiresAt = $this->expiresAt($claims);

        OidcLogoutReceipt::query()->updateOrCreate(
            ['jti_hash' => $jtiHash],
            [
                'issuer' => trim((string) ($claims['iss'] ?? '')) ?: null,
                'audience' => trim((string) ($claims['aud'] ?? '')) ?: null,
                'sid_hash' => $this->sidHash($claims['sid'] ?? null),
                'outcome' => trim($outcome) !== '' ? trim($outcome) : 'processed',
                'processed_at' => now(),
                'expires_at' => $expiresAt,
            ],
        );
    }

    public function purgeExpiredReceipts(): int
    {
        return OidcLogoutReceipt::query()
            ->where('expires_at', '<=', now())
            ->delete();
    }

    /**
     * @param array<string, mixed> $claims
     */
    public function logReplayDetected(Request $request, array $claims): void
    {
        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_CLIENT_AUTH,
            event: 'client_auth.logout.backchannel_replay_detected',
            description: 'Client back-channel logout replay detected.',
            causer: $request->user(),
            properties: [
                'has_jti' => trim((string) ($claims['jti'] ?? '')) !== '',
                'has_exp' => (int) ($claims['exp'] ?? 0) > 0,
                'has_sid' => trim((string) ($claims['sid'] ?? '')) !== '',
                'status' => 'already_processed',
                ...$this->auditLogService->requestContext($request),
            ],
        );
    }

    public function jtiHash(string $jti): ?string
    {
        $jti = trim($jti);

        return $jti !== '' ? hash('sha256', $jti) : null;
    }

    private function sidHash(mixed $sid): ?string
    {
        $sid = trim((string) ($sid ?? ''));

        return $sid !== '' ? hash('sha256', $sid) : null;
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function expiresAt(array $claims): Carbon
    {
        $exp = (int) ($claims['exp'] ?? 0);

        if ($exp <= 0) {
            return now()->addSeconds(max(60, (int) config('sso.backchannel_logout_replay_cache_seconds', 300)));
        }

        $clockSkew = max(0, (int) config('sso.oidc_clock_skew_seconds', 60));

        return Carbon::createFromTimestamp($exp)->addSeconds($clockSkew);
    }
}
