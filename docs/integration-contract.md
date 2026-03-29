# SSO Server <-> SSO Client integrációs szerződés

## Hatókör

Ez a dokumentum az explicit, tesztekkel védett integrációs szerződést írja le az alábbiak között:

- `sso_server`
- `sso_client`

Csak a jelenlegi, ténylegesen implementált működést írja le.

## 1. Authorize request szerződés

A kliens a bejelentkezést ide irányítással indítja:

- `GET {SSO_SERVER_BASE_URL}{SSO_AUTHORIZE_ENDPOINT}`
- alapértelmezés szerint: `GET /oauth/authorize`

Az `sso_client` által küldött kötelező query paraméterek:

- `response_type=code`
- `client_id`
- `redirect_uri`
- `scope` szóközzel elválasztva, `SSO_SCOPES` alapján
- `state` véletlen 64 karakter, sessionben tárolva
- `code_challenge`
- `code_challenge_method=S256`

Szerver oldali viselkedés:

- érvényes kérés és hitelesített user esetén `302` redirect a `redirect_uri` címre `code` és visszaechozott `state` paraméterrel
- érvénytelen kliens / redirect / scope esetén a jelenlegi szerver oldali működés validation hiba az authorize route-on (`302` session validation hibákkal), nem callback redirect

## 2. Callback szerződés (`sso_client`)

A kliens callback végpontja:

- `GET /auth/sso/callback`

A kliens elvárása:

- sikeres ág: `code` és `state`
- hibaág: opcionális OAuth-stílusú `error`

A kliens oldali validáció szabályai:

- hiányzó `code` -> hiba
- hiányzó `state` -> hiba
- eltérő `state` vagy hiányzó várt session state -> hiba
- hiányzó PKCE verifier a sessionből -> hiba
- callback queryben jelen lévő `error` -> hiba

## 3. Token response szerződés

Token végpont:

- `POST {SSO_SERVER_BASE_URL}{SSO_TOKEN_ENDPOINT}`
- alapértelmezés szerint: `POST /api/oauth/token`

A kliens által használt grant:

- `grant_type=authorization_code`
- `client_id`
- `client_secret` ha konfigurálva van
- `redirect_uri`
- `code`
- `code_verifier`

Sikeres válasz hiteles formátuma:

```json
{
  "message": "OAuth token issued successfully.",
  "data": {
    "token_type": "Bearer",
    "access_token": "...",
    "refresh_token": "...",
    "expires_in": 3600,
    "refresh_token_expires_in": 86400,
    "scope": "openid profile email"
  },
  "meta": {},
  "errors": {}
}
```

Szerződés szabály:

- az `sso_client` kizárólag a `data.access_token` mezőt olvassa, nincs top-level fallback

Hibás válasz formátuma:

```json
{
  "message": "OAuth token request failed.",
  "data": {},
  "meta": {},
  "errors": {
    "field": ["reason"]
  }
}
```

## 4. UserInfo response szerződés

Userinfo végpont:

- `GET {SSO_SERVER_BASE_URL}{SSO_USERINFO_ENDPOINT}`
- alapértelmezés szerint: `GET /api/oauth/userinfo`
- authorizáció: `Bearer {access_token}`

Sikeres válasz:

```json
{
  "message": "User info retrieved successfully.",
  "data": {
    "sub": "123",
    "name": "Example User",
    "email": "user@example.test",
    "email_verified": true
  },
  "meta": {},
  "errors": {}
}
```

Claim szerződés:

- garantált: `data.sub`
- scope-függően opcionális: `data.name`, `data.email`, `data.email_verified`

Kliens oldali szerződés:

- a userinfo választ csak a `data` mezőből olvassa
- a lokális user session felépítéséhez szükséges a `data.email`

## 5. Logout szerződés

Jelenlegi explicit szerződés:

- az `sso_client` csak lokális logoutot hajt végre (`POST /auth/logout`)
- a session teljesen törlődik lokálisan
- jelenleg nincs aktív single logout handshake a szerverrel

## 6. Self-service profile szerződés

A kliens profile oldala:

- `GET /profile`

A böngésző által közvetlenül használt upstream API:

- `GET {SSO_SERVER_BASE_URL}/api/profile`
- `PATCH {SSO_SERVER_BASE_URL}/api/profile`
- `PATCH {SSO_SERVER_BASE_URL}/api/profile/password`

Boundary:

- az `sso_client` csak UI és orchestration réteg
- az `sso_server` marad az identity módosítások forrása
- nincs lokális profile persistence route az `sso_client` oldalon self-service identity módosításokra

A jelenlegi szerződés szerinti szerkeszthető mezők:

- `name`

A jelenlegi szerződés szerinti csak olvasható mezők:

- `email`

Kliens oldali state viselkedés:

- az oldal a kanonikus profile adatot az `sso_server` felől tölti be
- sikeres profile frissítés után a kliens helyben szinkronizálja a shared auth user `name` és `email` mezőit
- jelszóváltoztatás után nincs szükség ki- és bejelentkezésre ahhoz, hogy a látható profile állapot konzisztens maradjon

## 7. Session/Auth state szerződés (kliens)

Lokális hitelesített állapot csak az alábbiak után jön létre:

1. érvényes callback validáció
2. sikeres token exchange
3. sikeres userinfo lekérés használható `email` mezővel
4. lokális user feloldás email alapján
5. Laravel web login + session regenerate

A kliens guest állapotba kerül, ha:

- lokális logout történik, vagy
- a user védett route-ot ér el érvényes session nélkül (`401` JSON API jellegű kérésnél, login redirect böngészőnél)

## 8. Hiba szerződés mátrix

| Eset | Szerver státusz/body | Transport | Kliens viselkedés |
|---|---|---|---|
| invalid client (authorize) | 302 + validation session hibák (`client_id`) | szerver oldali redirect | nem callback-alapú, a user a szerver flow-ban marad |
| inactive client (authorize/token) | 302 validation (authorize) / 422 JSON (token) | redirect vagy JSON | a token fázis elbukik, a kliens login hibát ad |
| redirect mismatch (authorize/token) | 302 validation (authorize) / 422 JSON (token) | redirect vagy JSON | a token fázis elbukik |
| disallowed scope (authorize) | 302 + validation session hibák (`scope`) | szerver oldali redirect | nem callback-alapú |
| missing state (callback) | n/a, kliens oldali callback validáció | query a kliens callbackre | a kliens elutasítja |
| invalid state (callback) | n/a, kliens oldali callback validáció | query a kliens callbackre | a kliens elutasítja |
| missing code (callback) | n/a, kliens oldali callback validáció | query a kliens callbackre | a kliens elutasítja |
| invalid/expired/reused code (token) | 422 JSON envelope `errors.code` mezővel | JSON | a kliens elutasítja a token exchange-et |
| token endpoint failure/network | n/a | transport hiba | a kliens elutasítja a token exchange-et |
| userinfo unauthorized | 401 JSON envelope | JSON | a kliens elutasítja a userinfo fázist |
| userinfo forbidden | 403 JSON envelope | JSON | a kliens elutasítja a userinfo fázist |
| forbidden self-service profile field | 422 JSON envelope mezőszintű hibákkal | JSON | a kliens section-level vagy field-level hibákat renderel |
| unauthorized protected route (client app) | 302 login redirect (HTML) / 401 JSON (`reauth_to`) | redirect vagy JSON | explicit re-auth viselkedés |

## 9. Konfigurációs szerződés

A kliens konfigurációjának tartalmaznia kell:

- `SSO_SERVER_BASE_URL`
- `SSO_AUTHORIZE_ENDPOINT`
- `SSO_TOKEN_ENDPOINT`
- `SSO_USERINFO_ENDPOINT`
- `SSO_CLIENT_ID`
- `SSO_CLIENT_SECRET` ha confidential client auth szükséges
- `SSO_REDIRECT_URI`
- `SSO_SCOPES`, és kötelezően tartalmaznia kell az `email` scope-ot, mert a kliens session mappinghez szükséges a `userinfo.email`

A szerver konfigurációjának / adatainak ehhez igazodnia kell:

- létezik OAuth kliens ugyanazzal a `client_id` értékkel
- az engedélyezett `redirect_uri` pontosan tartalmazza a `SSO_REDIRECT_URI` értéket
- az engedélyezett scope-ok tartalmazzák a kliens által kért scope-okat
- a token policy PKCE beállításai kompatibilisek a kliens kérésével
- a szerver CORS pontosan engedi az `sso_client` browser origint a közvetlen profile API hívásokhoz

## 10. Szerződést védő tesztlefedettség

Szerver:

- `tests/Feature/OAuth/OAuthAuthorizationCodeFlowTest.php`
- `tests/Feature/OAuth/OAuthUserInfoTest.php`
- `tests/Feature/Api/SelfServiceProfileApiTest.php`

Kliens:

- `tests/Feature/Auth/SsoAuthenticationTest.php`
- `tests/Feature/ProfileTest.php`
- `resources/js/tests/services/profileApi.test.js`
- `resources/js/tests/pages/ProfileEdit.test.js`

Ezek a tesztek adják ennek a szerződésnek a regressziós védelmét.

