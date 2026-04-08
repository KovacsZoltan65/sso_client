<?php

namespace App\Services\Sso;

use App\Models\OidcSessionMapping;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\Request;

class OidcSessionMappingCleanupService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function invalidateBySid(string $sid, ?Request $request = null): int
    {
        $sidHash = $this->sidHash($sid);

        if ($sidHash === null) {
            return 0;
        }

        $count = OidcSessionMapping::query()
            ->where('sid_hash', $sidHash)
            ->whereNull('invalidated_at')
            ->update([
                'invalidated_at' => now(),
                'updated_at' => now(),
            ]);

        $this->logInvalidated($request, $count, 'sid');

        return $count;
    }

    public function invalidateBySessionId(string $sessionId, ?Request $request = null): int
    {
        $sessionId = trim($sessionId);

        if ($sessionId === '') {
            return 0;
        }

        $count = OidcSessionMapping::query()
            ->where('session_id', $sessionId)
            ->whereNull('invalidated_at')
            ->update([
                'invalidated_at' => now(),
                'updated_at' => now(),
            ]);

        $this->logInvalidated($request, $count, 'session');

        return $count;
    }

    /**
     * @param array<int, string> $sessionIds
     */
    public function invalidateBySessionIds(array $sessionIds, ?Request $request = null): int
    {
        $sessionIds = collect($sessionIds)
            ->map(fn (mixed $sessionId): string => trim((string) $sessionId))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($sessionIds === []) {
            return 0;
        }

        $count = OidcSessionMapping::query()
            ->whereIn('session_id', $sessionIds)
            ->whereNull('invalidated_at')
            ->update([
                'invalidated_at' => now(),
                'updated_at' => now(),
            ]);

        $this->logInvalidated($request, $count, 'sessions');

        return $count;
    }

    public function purgeStaleMappings(?int $retentionSeconds = null, ?Request $request = null): int
    {
        $retentionSeconds ??= max(3600, (int) config('sso.oidc_session_mapping_retention_seconds', 604800));
        $deleteBefore = now()->subSeconds($retentionSeconds);

        $deleted = OidcSessionMapping::query()
            ->whereNotNull('invalidated_at')
            ->where('invalidated_at', '<=', $deleteBefore)
            ->delete();

        $this->logPurged($request, $deleted, 'stale');

        return $deleted;
    }

    public function purgeOrphanMappings(?Request $request = null): int
    {
        $sessionTable = (string) config('session.table', 'sessions');

        $deleted = OidcSessionMapping::query()
            ->whereNull('invalidated_at')
            ->whereNotExists(function ($subQuery) use ($sessionTable): void {
                $subQuery->selectRaw('1')
                    ->from($sessionTable)
                    ->whereColumn($sessionTable.'.id', 'oidc_session_mappings.session_id');
            })
            ->delete();

        $this->logPurged($request, $deleted, 'orphan');

        return $deleted;
    }

    public function sidHash(string $sid): ?string
    {
        $sid = trim($sid);

        return $sid !== '' ? hash('sha256', $sid) : null;
    }

    private function logInvalidated(?Request $request, int $count, string $trigger): void
    {
        if ($count <= 0) {
            return;
        }

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_CLIENT_AUTH,
            event: 'client_auth.sid.mapping_invalidated',
            description: 'Client OIDC sid mapping invalidated.',
            causer: $request?->user(),
            properties: [
                'affected_count' => $count,
                'trigger' => $trigger,
                'status' => 'invalidated',
                ...($request instanceof Request ? $this->auditLogService->requestContext($request) : []),
            ],
        );
    }

    private function logPurged(?Request $request, int $count, string $trigger): void
    {
        if ($count <= 0) {
            return;
        }

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_CLIENT_AUTH,
            event: 'client_auth.sid.mapping_purged',
            description: 'Client OIDC sid mapping purged.',
            causer: $request?->user(),
            properties: [
                'affected_count' => $count,
                'trigger' => $trigger,
                'status' => 'purged',
                ...($request instanceof Request ? $this->auditLogService->requestContext($request) : []),
            ],
        );
    }
}
