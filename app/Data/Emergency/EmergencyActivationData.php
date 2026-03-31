<?php

namespace App\Data\Emergency;

use Spatie\LaravelData\Data;

final class EmergencyActivationData extends Data
{
    public function __construct(
        public string $reason,
        public string $operator,
        public ?int $ttlMinutes = null,
        public ?string $referenceId = null,
    ) {
    }
}
