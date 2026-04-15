<?php

return [
    'companies' => [
        'retrieved' => 'A cégek lekérése sikeres.',
        'created' => 'A cég létrehozása sikeres.',
        'updated' => 'A cég frissítése sikeres.',
        'deleted' => 'A cég törlése sikeres.',
    ],
    'employees' => [
        'fetched' => 'A munkatársak lekérése sikeres.',
        'created' => 'A munkatárs létrehozása sikeres.',
        'updated' => 'A munkatárs frissítése sikeres.',
        'deleted' => 'A munkatárs törlése sikeres.',
    ],
    'roles' => [
        'retrieved' => 'A szerepkörök lekérése sikeres.',
        'created' => 'A szerepkör létrehozása sikeres.',
        'updated' => 'A szerepkör frissítése sikeres.',
        'deleted' => 'A szerepkör törlése sikeres.',
    ],
    'permissions' => [
        'retrieved' => 'A jogosultságok lekérése sikeres.',
        'created' => 'A jogosultság létrehozása sikeres.',
        'updated' => 'A jogosultság frissítése sikeres.',
        'deleted' => 'A jogosultság törlése sikeres.',
    ],
    'users' => [
        'retrieved' => 'A felhasználók lekérése sikeres.',
        'retrieved_single' => 'A felhasználó lekérése sikeres.',
        'updated' => 'A felhasználó frissítése sikeres.',
    ],
    'audit_logs' => [
        'retrieved' => 'Az audit naplók lekérése sikeres.',
        'retrieved_single' => 'Az audit napló bejegyzés lekérése sikeres.',
    ],
    'sso' => [
        'callback_missing_code' => 'Hiányzik az authorization code a callbackből.',
        'callback_missing_state' => 'Hiányzik a state érték a callbackből.',
        'invalid_or_expired_state' => 'Érvénytelen vagy lejárt SSO állapot. Próbáld újra a bejelentkezést.',
        'missing_pkce_verifier' => 'Hiányzó PKCE verifier miatt nem folytatható a bejelentkezés. Indítsd újra a login folyamatot.',
        'missing_nonce_state' => 'Hiányzó OIDC nonce állapot miatt nem folytatható a bejelentkezés. Indítsd újra a login folyamatot.',
        'missing_valid_id_token' => 'Az SSO token válasz nem tartalmaz érvényes ID tokent az openid flow-hoz.',
        'token_endpoint_unreachable' => 'Az SSO token végpont nem érhető el.',
        'token_endpoint_invalid_json' => 'Az SSO token végpont érvénytelen, nem JSON választ adott.',
        'token_endpoint_error' => 'Az SSO token végpont hibával válaszolt.',
        'missing_valid_access_token' => 'Az SSO token válasz nem tartalmaz érvényes access tokent.',
        'userinfo_missing_identifier' => 'Az SSO userinfo válasz nem tartalmaz használható user azonosítót.',
        'missing_expected_nonce' => 'Hiányzó várt OIDC nonce miatt nem folytatható a bejelentkezés. Indítsd újra a login folyamatot.',
        'invalid_nonce_claim' => 'Az SSO ID token nem tartalmaz érvényes nonce claimet. Indítsd újra a bejelentkezést.',
        'nonce_verification_failed' => 'Az OIDC nonce ellenőrzés sikertelen. Indítsd újra a bejelentkezést.',
    ],
];
