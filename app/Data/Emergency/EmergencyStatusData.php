<?php

namespace App\Data\Emergency;

use Spatie\LaravelData\Data;

final class EmergencyStatusData extends Data
{
    public function __construct(
        public string $state,
        public bool $featureEnabled,
        public bool $manualActivationRequired,
        public bool $healthcheckEnabled,
        public bool $ssoReachable,
        public bool $emergencyLoginAvailable,
        public ?string $bannerMessage,
        public ?string $activationReference,
        public ?string $reason,
        public ?string $activatedBy,
        public ?string $activatedAt,
        public ?string $expiresAt,
        public array $capabilities,
    ) {
    }
}
