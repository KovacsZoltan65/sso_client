<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class SsoStatusData extends Data
{
    /**
     * Az SSO kliens állapotát leíró DTO.
     *
     * @param array<int, string> $scopes
     */
    public function __construct(
        public bool $configured,
        public bool $localAuthEnabled,
        public ?string $serverBaseUrl,
        public ?string $authorizeEndpoint,
        public ?string $tokenEndpoint,
        public ?string $userinfoEndpoint,
        public ?string $redirectUri,
        public array $scopes,
        public string $mode,
        public string $message,
    ) {
    }
}
