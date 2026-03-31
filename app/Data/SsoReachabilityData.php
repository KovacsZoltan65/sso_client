<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class SsoReachabilityData extends Data
{
    public function __construct(
        public string $status,
        public bool $reachable,
        public bool $isReachable,
        public bool $isMaintenance,
        public string $reason,
        public ?int $httpStatus,
        public int $failureCount,
        public ?string $checkedAt,
        public ?string $retryAfter,
        public string $source,
    ) {
    }
}
