<?php

namespace App\Console\Commands;

use App\Services\Auth\LocalFallbackAuthService;
use App\Services\Sso\SsoReachabilityService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class SsoFallbackStatusCommand extends Command
{
    protected $signature = 'sso:fallback:status';

    protected $description = 'Display the current SSO fallback status and operational warnings.';

    public function __construct(
        private readonly SsoReachabilityService $ssoReachabilityService,
        private readonly LocalFallbackAuthService $localFallbackAuthService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $request = Request::create('/');
        $reachability = $this->ssoReachabilityService->current();
        $decision = $this->localFallbackAuthService->buildLoginDecisionData($request);

        $this->line('SSO Fallback Status');
        $this->line('-------------------');
        $this->newLine();

        $this->info('SSO Reachability:');
        $this->line(sprintf('  status: %s', $reachability->status));
        $this->line(sprintf('  failure_count: %d', $reachability->failureCount));
        $this->line(sprintf('  reason: %s', $reachability->reason));
        $this->line(sprintf('  source: %s', $reachability->source));
        $this->line(sprintf('  checked_at: %s', $reachability->checkedAt ?? 'n/a'));

        if ($reachability->retryAfter !== null) {
            $this->line(sprintf('  retry_after: %s', $reachability->retryAfter));
        }

        $this->newLine();
        $this->info('Config:');
        $this->line(sprintf('  fallback_enabled: %s', $this->boolString($this->localFallbackAuthService->isFeatureEnabled())));
        $this->line(sprintf('  allow_degraded: %s', $this->boolString($this->localFallbackAuthService->allowsDegraded())));
        $this->line(sprintf('  require_sso_unreachable: %s', $this->boolString($this->localFallbackAuthService->requiresSsoUnreachable())));
        $this->line(sprintf('  incident_id_required: %s', $this->boolString($this->localFallbackAuthService->incidentIdRequired())));
        $this->line(sprintf('  incident_id: %s', $this->localFallbackAuthService->incidentId() ?? 'none'));

        $this->newLine();
        $this->info('Decision:');
        $this->line(sprintf('  fallback_allowed: %s', $decision['currentlyAllowed'] ? 'YES' : 'NO'));
        $this->line(sprintf('  reason: %s', $this->decisionReason($decision, $reachability->status)));

        $warnings = $this->warningsFor($decision, $reachability->status);

        $this->newLine();
        $this->info('Warnings:');

        if ($warnings === []) {
            $this->line('  none');
        } else {
            foreach ($warnings as $warning) {
                $this->warn(sprintf('  ! %s', $warning));
            }
        }

        return self::SUCCESS;
    }

    private function boolString(bool $value): string
    {
        return $value ? 'true' : 'false';
    }

    private function decisionReason(array $decision, string $status): string
    {
        if (! $decision['featureEnabled']) {
            return 'blocked (feature disabled)';
        }

        if ($decision['blockedBecauseIncidentMissing']) {
            return 'blocked (no incident)';
        }

        if ($decision['currentlyAllowed']) {
            if ($status === SsoReachabilityService::STATUS_DEGRADED) {
                return 'degraded (flag enabled)';
            }

            if ($status === SsoReachabilityService::STATUS_UNREACHABLE) {
                return 'unreachable';
            }
        }

        return match ($status) {
            SsoReachabilityService::STATUS_HEALTHY => 'blocked (healthy)',
            SsoReachabilityService::STATUS_MAINTENANCE => 'blocked (maintenance)',
            SsoReachabilityService::STATUS_DEGRADED => 'blocked (degraded flag disabled)',
            SsoReachabilityService::STATUS_UNREACHABLE => 'blocked (policy)',
            default => 'blocked (unknown)',
        };
    }

    /**
     * @return array<int, string>
     */
    private function warningsFor(array $decision, string $status): array
    {
        $warnings = [];

        if ($decision['featureEnabled'] && $status === SsoReachabilityService::STATUS_HEALTHY) {
            $warnings[] = 'Fallback enabled while SSO is healthy';
        }

        if ($status === SsoReachabilityService::STATUS_DEGRADED && $decision['allowDegraded']) {
            $warnings[] = 'Degraded fallback active (risky mode)';
        }

        if ($decision['incidentIdRequired'] && $decision['incidentId'] === null) {
            $warnings[] = 'Missing incident ID';
        }

        if ($status === SsoReachabilityService::STATUS_DEGRADED) {
            $warnings[] = 'SSO partially available (degraded)';
        }

        if ($decision['currentlyAllowed']) {
            $warnings[] = 'Fallback mode should be temporary';
        }

        return $warnings;
    }
}
