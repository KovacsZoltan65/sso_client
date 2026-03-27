<?php

return [
    'server_base_url' => env('SSO_SERVER_BASE_URL'),
    'authorize_endpoint' => env('SSO_AUTHORIZE_ENDPOINT', '/oauth/authorize'),
    'token_endpoint' => env('SSO_TOKEN_ENDPOINT', '/api/oauth/token'),
    'userinfo_endpoint' => env('SSO_USERINFO_ENDPOINT', '/api/oauth/userinfo'),
    'logout_endpoint' => env('SSO_LOGOUT_ENDPOINT'),
    'client_id' => env('SSO_CLIENT_ID'),
    'client_secret' => env('SSO_CLIENT_SECRET'),
    'redirect_uri' => env('SSO_REDIRECT_URI'),
    'scopes' => array_values(array_filter(preg_split('/[\s,]+/', (string) env('SSO_SCOPES', 'openid profile email')) ?: [])),
    'timeout' => (int) env('SSO_TIMEOUT', 10),
    'state_session_key' => 'sso.oauth.state',
    'pkce_verifier_session_key' => 'sso.oauth.pkce_verifier',
    'local_auth_enabled' => env('SSO_LOCAL_AUTH_ENABLED', false),
];
