<?php

return [
    'server_base_url' => env('SSO_SERVER_BASE_URL'),
    'client_id' => env('SSO_CLIENT_ID'),
    'client_secret' => env('SSO_CLIENT_SECRET'),
    'redirect_uri' => env('SSO_REDIRECT_URI'),
    'local_auth_enabled' => env('SSO_LOCAL_AUTH_ENABLED', true),
];
