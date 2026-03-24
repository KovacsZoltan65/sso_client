<?php

namespace App\Services\Sso;

use App\Data\SsoStatusData;

class SsoClientService
{
    public function status(): SsoStatusData
    {
        $serverBaseUrl = config('sso.server_base_url');
        $localAuthEnabled = (bool) config('sso.local_auth_enabled');
        $configured = filled($serverBaseUrl);

        return new SsoStatusData(
            configured: $configured,
            localAuthEnabled: $localAuthEnabled,
            serverBaseUrl: $serverBaseUrl,
            mode: $configured ? 'placeholder-ready' : 'missing-configuration',
            message: $configured
                ? 'The client is ready for a future redirect and callback implementation.'
                : 'Set SSO_SERVER_BASE_URL to activate the future SSO integration contract.',
        );
    }

    public function authorizationRedirectUrl(): ?string
    {
        return blank(config('sso.server_base_url'))
            ? null
            : rtrim((string) config('sso.server_base_url'), '/').'/oauth/authorize';
    }
}
