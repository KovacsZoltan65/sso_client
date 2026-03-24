<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class SsoStatusData extends Data
{
    public function __construct(
        public bool $configured,
        public bool $localAuthEnabled,
        public ?string $serverBaseUrl,
        public string $mode,
        public string $message,
    ) {
    }
}
