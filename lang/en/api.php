<?php

return [
    'companies' => [
        'retrieved' => 'Companies retrieved successfully.',
        'created' => 'Company created successfully.',
        'updated' => 'Company updated successfully.',
        'deleted' => 'Company deleted successfully.',
    ],
    'employees' => [
        'fetched' => 'Employees fetched successfully.',
        'created' => 'Employee created successfully.',
        'updated' => 'Employee updated successfully.',
        'deleted' => 'Employee deleted successfully.',
    ],
    'roles' => [
        'retrieved' => 'Roles retrieved successfully.',
        'created' => 'Role created successfully.',
        'updated' => 'Role updated successfully.',
        'deleted' => 'Role deleted successfully.',
    ],
    'permissions' => [
        'retrieved' => 'Permissions retrieved successfully.',
        'created' => 'Permission created successfully.',
        'updated' => 'Permission updated successfully.',
        'deleted' => 'Permission deleted successfully.',
    ],
    'users' => [
        'retrieved' => 'Users retrieved successfully.',
        'retrieved_single' => 'User retrieved successfully.',
        'updated' => 'User updated successfully.',
    ],
    'audit_logs' => [
        'retrieved' => 'Audit logs retrieved successfully.',
        'retrieved_single' => 'Audit log retrieved successfully.',
    ],
    'sso' => [
        'callback_missing_code' => 'Missing authorization code in callback.',
        'callback_missing_state' => 'Missing state value in callback.',
        'invalid_or_expired_state' => 'Invalid or expired SSO state. Please try signing in again.',
        'missing_pkce_verifier' => 'Missing PKCE verifier. Restart the login flow.',
        'missing_nonce_state' => 'Missing OIDC nonce state. Restart the login flow.',
        'missing_valid_id_token' => 'The SSO token response does not include a valid ID token for openid flow.',
        'token_endpoint_unreachable' => 'The SSO token endpoint is unreachable.',
        'token_endpoint_invalid_json' => 'The SSO token endpoint returned an invalid non-JSON response.',
        'token_endpoint_error' => 'The SSO token endpoint returned an error.',
        'missing_valid_access_token' => 'The SSO token response does not include a valid access token.',
        'userinfo_missing_identifier' => 'The SSO userinfo response does not include a usable user identifier.',
        'missing_expected_nonce' => 'Missing expected OIDC nonce. Restart the login flow.',
        'invalid_nonce_claim' => 'The SSO ID token does not contain a valid nonce claim. Restart sign in.',
        'nonce_verification_failed' => 'OIDC nonce verification failed. Restart sign in.',
    ],
];
