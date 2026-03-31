<?php

namespace App\Data\Emergency;

use Spatie\LaravelData\Data;

final class EmergencyLoginData extends Data
{
    public function __construct(
        public string $username,
        public string $password,
    ) {
    }
}
