<?php

use App\Data\Emergency\EmergencyActivationData;
use App\Services\Emergency\EmergencyModeService;
use App\Services\Emergency\EmergencyStatusService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('emergency:status', function (EmergencyStatusService $emergencyStatusService) {
    $status = $emergencyStatusService->status();

    $this->info(sprintf('State: %s', $status->state));
    $this->line(sprintf('Feature enabled: %s', $status->featureEnabled ? 'yes' : 'no'));
    $this->line(sprintf('SSO reachable: %s', $status->ssoReachable ? 'yes' : 'no'));
    $this->line(sprintf('Emergency login available: %s', $status->emergencyLoginAvailable ? 'yes' : 'no'));

    if ($status->activationReference) {
        $this->line(sprintf('Reference: %s', $status->activationReference));
        $this->line(sprintf('Activated by: %s', $status->activatedBy));
        $this->line(sprintf('Activated at: %s', $status->activatedAt));
        $this->line(sprintf('Expires at: %s', $status->expiresAt));
    }
})->purpose('Show the current emergency mode state');

Artisan::command('emergency:activate {--reason=} {--operator=} {--ttl=}', function (EmergencyModeService $emergencyModeService) {
    $reason = trim((string) $this->option('reason'));
    $operator = trim((string) $this->option('operator'));
    $ttl = $this->option('ttl');

    if ($reason === '' || $operator === '') {
        $this->error('Both --reason and --operator are required.');

        return 1;
    }

    $activation = $emergencyModeService->activate(new EmergencyActivationData(
        reason: $reason,
        operator: $operator,
        ttlMinutes: is_numeric($ttl) ? (int) $ttl : null,
    ));

    $this->info('Emergency mode activated.');
    $this->line(sprintf('Reference: %s', $activation['reference_id']));
    $this->line(sprintf('Expires at: %s', $activation['expires_at']));

    return 0;
})->purpose('Activate emergency mode');

Artisan::command('emergency:deactivate {--reason=} {--operator=}', function (EmergencyModeService $emergencyModeService) {
    $reason = trim((string) $this->option('reason'));
    $operator = trim((string) $this->option('operator'));

    if ($reason === '' || $operator === '') {
        $this->error('Both --reason and --operator are required.');

        return 1;
    }

    $emergencyModeService->deactivate($reason, $operator);

    $this->info('Emergency mode deactivated.');

    return 0;
})->purpose('Deactivate emergency mode');

Schedule::command('activitylog:clean')->daily();
