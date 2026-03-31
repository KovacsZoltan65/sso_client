<?php

return [
    'enabled' => env('SSO_EMERGENCY_MODE_ENABLED', false),
    'require_manual_activation' => env('SSO_EMERGENCY_MODE_REQUIRE_MANUAL_ACTIVATION', true),
    'healthcheck_enabled' => env('SSO_HEALTHCHECK_ENABLED', true),
    'session_cookie' => env('SSO_EMERGENCY_SESSION_COOKIE', 'sso_client_emergency_session'),
    'activation_ttl_minutes' => (int) env('SSO_EMERGENCY_ACTIVATION_TTL_MINUTES', 60),
    'banner_message' => env('SSO_EMERGENCY_BANNER_MESSAGE', 'SSO unavailable - limited emergency mode is active.'),
    'allow_view_users' => env('SSO_EMERGENCY_ALLOW_VIEW_USERS', true),
    'allow_view_companies' => env('SSO_EMERGENCY_ALLOW_VIEW_COMPANIES', true),
    'allow_view_audit_logs' => env('SSO_EMERGENCY_ALLOW_VIEW_AUDIT_LOGS', true),
    'state_cache_key' => env('SSO_EMERGENCY_STATE_CACHE_KEY', 'sso_client.emergency.state'),
];
