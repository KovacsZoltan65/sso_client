<?php

return [
    'server_base_url' => env('SSO_SERVER_BASE_URL'),
    'authorize_endpoint' => env('SSO_AUTHORIZE_ENDPOINT', '/oauth/authorize'),
    'token_endpoint' => env('SSO_TOKEN_ENDPOINT', '/api/oauth/token'),
    'userinfo_endpoint' => env('SSO_USERINFO_ENDPOINT', '/api/oauth/userinfo'),
    'oidc_jwks_endpoint' => env('SSO_OIDC_JWKS_ENDPOINT', '/.well-known/jwks.json'),
    'oidc_expected_issuer' => env('SSO_OIDC_EXPECTED_ISSUER', env('SSO_SERVER_BASE_URL')),
    'oidc_clock_skew_seconds' => (int) env('SSO_OIDC_CLOCK_SKEW_SECONDS', 60),
    'oidc_jwks_cache_seconds' => (int) env('SSO_OIDC_JWKS_CACHE_SECONDS', 300),
    'logout_endpoint' => env('SSO_LOGOUT_ENDPOINT'),
    'client_id' => env('SSO_CLIENT_ID'),
    // Confidential clients should provide a secret; public PKCE-only clients may leave it empty.
    'client_secret' => env('SSO_CLIENT_SECRET'),
    'redirect_uri' => env('SSO_REDIRECT_URI'),
    'scopes' => array_values(array_filter(preg_split('/[\s,]+/', (string) env('SSO_SCOPES', 'openid profile email')) ?: [])),
    'timeout' => (int) env('SSO_TIMEOUT', 10),
    'pending_auth_session_key' => 'sso.oauth.pending_authorizations',
    'identity_validation_session_key' => 'sso.oauth.identity_validation_contexts',
    'local_auth_enabled' => env('SSO_LOCAL_AUTH_ENABLED', false),
];
