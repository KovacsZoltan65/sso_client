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
- `nonce` véletlen, nagy entrópiájú string, ha a kért scope-ok között szerepel az `openid`
- `code_challenge`
- `code_challenge_method=S256`

`nonce` baseline szabály:

- ha az authorize request `openid` scope-ot kér, a `nonce` kötelező
- ha az authorize request nem kér `openid` scope-ot, az első iterációban a `nonce` elhagyható
- a `nonce`-ot mindig az `sso_client` generálja, nem user inputból származik
- a `state` és a `nonce` nem ugyanaz: a `state` request/callback korrelációra szolgál, a `nonce` a későbbi OIDC identity response / ID token validáció alapja

Kliens oldali session storage szerződés:

- az `sso_client` az authorize indításkor egy `sso.oauth.pending_authorizations` session mapbe ment
- a kulcs a generált `state`
- az érték tartalmazza legalább:
  - `state`
  - `code_verifier`
  - `nonce`
  - `issued_at`
  - `scope_contains_openid`
- ez a szerződés azért létezik, hogy a nonce ne UI state-ből vagy queryből legyen később visszafejtve

Szerver oldali viselkedés:

- érvényes kérés és hitelesített user esetén `302` redirect a `redirect_uri` címre `code` és visszaechozott `state` paraméterrel
- érvénytelen kliens / redirect / request szerkezet esetén a hiba a provider oldalon marad (`302` session validation hibákkal), nem callback redirect
- csak már validált kliens + validált redirect után keletkező authorize refusal mehet vissza callbacken OAuth-stílusú `error` paraméterekkel

## 2. Callback szerződés (`sso_client`)

A kliens callback végpontja:

- `GET /auth/sso/callback`

A kliens elvárása:

- sikeres ág: `code` és `state`
- hibaág: opcionális OAuth-stílusú `error`, `error_description`, `state`

A kliens oldali validáció szabályai:

- hiányzó `code` -> hiba
- hiányzó `state` -> hiba
- eltérő `state` vagy hiányzó várt session state -> hiba
- hiányzó PKCE verifier a sessionből -> hiba
- ha az eredeti flow `openid` scope-ot használt, de a sessionből hiányzik a nonce kontextus -> hiba
- callback queryben jelen lévő `error` -> authorize callback hiba, külön kezelve a generikus belső hibáktól

Fontos határ:

- ebben az iterációban még nincs teljes kliensoldali JWT verification platform
- a ticket célja most már a returned nonce fogadása es az expected vs returned nonce osszevetes foundationje
- a későbbi OIDC ticket erre a működő adatutra fog építeni

Downstream nonce validation foundation:

- a callback a token response `id_token` mezőjéből olvassa ki a returned nonce-ot
- a callback a session-bound `expected_nonce` értékkel ténylegesen összeveti a returned nonce-ot
- sikeres nonce validáció után a kliens a nonce contextet átteszi a `sso.oauth.identity_validation_contexts` session mapbe
- ebben a retained contextben a kliens `expected_nonce` néven őrzi meg a validált expected oldalt
- a pending context ugyanahhoz a `state`-hez csak ezután törölhető
- a kliens oldalon a foundation helper neve `validateExpectedNonce(...)`
- jelenleg ez a helper:
  - guardolja a hiányzó elvárt nonce-ot, ha az flow szerint kötelező
  - mismatch esetén hibát dob
  - hiányzó returned nonce esetén openid flow-ban hibát dob
  - csak akkor deferred, ha tényleges identity response még nincs
- a jelenlegi contract:
  - `expected_nonce` a kliens retained identity validation contextből jön
  - `returned_nonce` a szerver által kibocsátott `id_token` nonce claimjéből jön
  - a kliens a payload parsinget jelenleg nonce-validációra használja, nem teljes JWT hitelesítésre

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
    "scope": "openid profile email",
    "id_token": "eyJ..."
  },
  "meta": {},
  "errors": {}
}
```

Szerződés szabály:

- a kliens kizárólag a `data` envelope-ból dolgozik
- `openid` scope esetén a kliens `data.id_token` mezőt is vár, és abból olvassa ki a returned nonce-ot
- a kliens discovery foundationkent tudja hasznalni a `GET /.well-known/openid-configuration` metadata dokumentumot is
- a discoverybol jelenleg ezeket a mezoket veszi at es validalja: `issuer`, `authorization_endpoint`, `token_endpoint`, `userinfo_endpoint`, `end_session_endpoint`, `jwks_uri`, `response_types_supported`, `subject_types_supported`, `id_token_signing_alg_values_supported`, `claims_supported`, `frontchannel_logout_session_supported`, `backchannel_logout_supported`, `backchannel_logout_session_supported`
- precedence szabaly:
  - 1. explicit kliens config
  - 2. ervenyes, validalt discovery metadata
  - 3. kontrollalt fallback a `SSO_SERVER_BASE_URL` alapjan, de csak akkor, ha nincs hibas discovery dokumentum, amelyet a kliens mar megprobalt felhasznalni
- a discovery validacio kotelezoen ellenorzi:
  - kotelezo mezok: `issuer`, `authorization_endpoint`, `token_endpoint`, `jwks_uri`, `response_types_supported`, `subject_types_supported`, `id_token_signing_alg_values_supported`, `claims_supported`
  - `issuer` egyezik az explicit `SSO_OIDC_EXPECTED_ISSUER` ertekkel, ha az be van allitva
  - endpoint mezok nem uresek es valid abszolut URL-ek
  - `id_token_signing_alg_values_supported` tartalmazza az `RS256` erteket
  - `claims_supported` tartalmazza a `sub` claimet
- hibas vagy hianyos discovery dokumentum eseten a kliens kontrollalt `client_auth.oidc.discovery_validation_failed` audit esemenyt ir, es nem lep tovabb vak fallbackkel
- a kliens tobbkulcsos JWKS valaszt tud kezelni; nincs `first key wins` logika
- a kliens `kid` alapján valasztja ki a megfelelo JWKS kulcsot
- ha a token `kid` erteke hianyzik, kontrollalt auth hiba tortenik
- ha a cache-elt JWKS-ben nincs matching publikus kulcs, a kliens egyszer force-refresh utvonalon ujra lekéri a JWKS-t, majd egyszer ujraprobalja a `kid` valasztast
- ha a refresh utan sincs matching `kid`, kontrollalt unknown-key auth hiba tortenik; nincs vegtelen retry vagy `first key wins` fallback
- a provider oldali `active`, `published` es `retiring` kulcsok kliens oldalon egyarant JWKS-ben publikalt verify kulcskent jelennek meg
- `retiring`, de meg JWKS-ben publikalt verify kulccsal alairt regi token tovabbra is verifikalhato
- `disabled` kulcsot a provider nem publikál JWKS-ben; ilyen `kid` erteku tokenre a kliens refresh utan is kontrollalt unknown-key hibaval reagal
- az unknown `kid` refresh audit esemenyei: `client_auth.id_token.unknown_kid_refresh_triggered`, majd hiba eseten `client_auth.id_token.unknown_kid_still_missing`
- a kliens RS256 alairast ellenoriz az ID tokenen
- a kliens minimalisan ellenorzi az `iss`, `aud`, `exp`, `iat` claim-eket is
- az `id_token`-tol jelenleg csak a minimalis claim contractot varja: `iss`, `sub`, `aud`, `iat`, `exp`, valamint `nonce` openid flow-ban es `sid`, ha a provider session-correlated logout foundationt ad
- a `sub` identity subject, a `sid` session-korrelációs azonosító; a kliens nem mossa ossze oket
- a nonce check csak sikeres signature es claim verify utan futhat le
- non-openid flow-ban a kliens nem vár `id_token` mezőt
- a discovery cache nem orok; TTL configbol jon, es a service explicit force-refresh utvonalat is tamogat
- hianyos vagy ervenytelen discovery dokumentumra a kliens nem epit vakon; kontrollalt hibakezelest alkalmaz
- ez meg mindig foundation ticket: nincs teljes discovery ecosystem vagy dynamic registration tamogatas

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

- `openid` scope eseten garantalt: `data.sub`
- `profile` scope eseten opcionális: `data.name`
- `email` scope eseten opcionális: `data.email`, `data.email_verified`

Kliens oldali szerződés:

- a userinfo választ csak a `data` mezőből olvassa
- a userinfo endpointot explicit configbol, discoverybol vagy kontrollalt fallbackbol oldja fel
- a userinfo hivas bearer access tokennel történik
- a kliens a `userinfo.sub` claimet az `id_token.sub` claimmel is osszeveti, mielott sikeres auth state epulne fel
- a lokális user session felépítéséhez jelenleg szükséges a `data.email`
- a reszletesebb identity claim-eket a kliens a `userinfo` felől várja, nem az `id_token`-bol
- a userinfo endpoint nem teljes profile API; csak a minimalis, scope-vezerelt identity payloadra epulunk
- sikeres openid callback utan a kliens az `id_token.sid` erteket a helyi OIDC session contextben tarolja, es hash-alapu `oidc_session_mappings` bejegyzessel koti a Laravel session ID-hoz
- raw `sid` nem kerul audit logba; perzisztens lookuphoz csak `sid_hash` tarolodik

## 5. Logout szerződés

Logout indítás:

- a user logout akcioja a kliensben `POST /auth/logout`
- a kliens provider logout URL-t epit
- a kliens lokalis sessiont mar az inditas fazisaban tisztan lezarja

Kuldo paraméterek a provider fele:

- `id_token_hint`, ha az elozo openid loginbol mar rendelkezésre áll
- `post_logout_redirect_uri`
- `state`

Logout return:

- a provider a klienst `GET /auth/logout/return` pontra iranyithatja vissza
- a kliens a visszateres `state` erteket a sessionben tarolt logout contexthez meri
- ervenytelen return allapot eseten a kliens kontrollalt hibauzenettel megszakitja a flow-t

Front-channel logout:

- a kliens kulon fogado vegpontot hasznal: `GET /auth/frontchannel-logout`
- ez nem azonos a normal logout return route-tal
- a kliens minimalis guardot alkalmaz:
  - `iss` egyezik a vart provider issuerrel
  - `client_id` egyezik a helyi kliensazonositoval
  - `sid`, ha jelen van, egyeznie kell a helyi OIDC session contexttel
- ervenyes provider-kezdeményezett logout eseten a kliens:
  - lokalis auth state-et lezarja
  - torli az atmeneti OIDC session contextet
  - sessiont invalidalja
- ervenytelen front-channel kereses eseten a kliens kontrollalt hibaval all meg, es nem lep ki vakon
- front-channel `sid` mismatch eseten kontrollalt no-op tortenik: a lokalis session megmarad, es audit esemeny jelzi a mismatch-et

Session boundary:

- a provider logout kulon fogalom a local logouttol
- a kliens a local logoutot akkor is vegrehajtja, ha a provider return meg csak ezutan jon
- a `userinfo`, `id_token` es egyeb atmeneti OIDC session adatok logoutkor torlodnek

Tudatosan nincs benne meg:

- teljes single logout tobb kliens kozott
- teljes garantalt multi-client logout minden bongeszo edge case-re

Back-channel logout:

- a kliens kulon backend vegpontot hasznal: `POST /auth/backchannel-logout`
- ez nem UI route, nem callback route, es nem a front-channel logout ujrahasznositasa
- a provider `logout_token` mezoben signed logout JWT-t kuld
- a kliens a meglévo OIDC verify foundationre epítve ellenorzi:
  - signature
  - `iss`
  - `aud`
  - `iat`
  - kotelezo `exp`
  - `jti`
  - `sub`
  - `sid`, ha a provider session-correlated logout tokent kuld
  - `events`
- a vart logout event claim: `http://schemas.openid.net/event/backchannel-logout`
- a replay ellenorzes csak sikeres token verify utan fut; ismeretlen vagy hamis alairasu token nem kerul receipt store-ba
- a `jti` feldolgozottsagat a kliens tartos `oidc_logout_receipts` store-ban koveti `jti_hash` alapon, raw logout token es raw `jti` tarolasa nelkul
- a receipt minimum metaadata: issuer, audience, opcionális `sid_hash`, outcome, processed_at es expires_at
- ugyanazon valid `jti` masodik beérkezese kontrollalt `already_processed` no-op valaszt ad, es nem indit uj lokalis cleanup hullamot
- valid token es `sid` claim eseten a kliens elsodlegesen a `sid_hash` alapjan feloldott session mappingeket torli
- ha a tokenben nincs `sid`, a kliens a meglévo legacy `sub`-alapu fallbacket hasznalja, hogy a korabbi foundation ne regresszaljon
- back-channel `sid` mismatch eseten kontrollalt no-op tortenik, nincs vak user-szintu session torles
- ha az aktualis request ugyanahhoz a felhasznalohoz tartozik, a kliens a jelenlegi web sessiont is lezarja
- lejart logout token verify hibat ad, nem hoz letre receiptet, es nem indit lokalis session cleanupot
- a receipt store-hoz minimalis lejart-receipt cleanup helper tartozik; ez foundation, nem globalis deduplikacios infrastruktura
- invalid signature, issuer mismatch, audience mismatch vagy hibas event claim eseten kontrollalt hiba tortenik, es nincs vak logout

Tudatosan nincs benne meg:

- guaranteed back-channel delivery
- teljes multi-device/session graph vagy admin session dashboard
- full OIDC session management iframe spec
- distributed replay-store koordinacio vagy eros, hosszu TTL-s replay platform

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

## 8. Authorize error contract

Provider oldalon marad:

- invalid client
- invalid redirect URI
- strukturálisan hibás authorize request, ha a callback biztonsága nem garantált
- nem hitelesített user esetén a normál provider login flow folytatódik, ez nem callback hiba

Callbacken térhet vissza:

- validált kliens + validált redirect után keletkező authorize refusal
- jelenleg: `access_denied`
- a callback payload OAuth-stílusú query paramétereket használ:

```text
error=access_denied
error_description=Access to this client was denied.
state=<eredeti state>
```

A kliens oldali elvárt reakció:

- `access_denied` -> rövid, nem technikai refusal üzenet
- `invalid_request` -> rövid, újraindítást kérő authorize hibaüzenet
- ismeretlen provider callback hiba -> általános, de még mindig provider-authorize hibaként kezelt üzenet

## 9. Hiba szerződés mátrix

| Eset | Szerver státusz/body | Transport | Kliens viselkedés |
|---|---|---|---|
| invalid client (authorize) | 302 + validation session hibák (`client_id`) | szerver oldali redirect | nem callback-alapú, a user a szerver flow-ban marad |
| inactive client (authorize/token) | 302 validation (authorize) / 422 JSON (token) | redirect vagy JSON | a token fázis elbukik, a kliens login hibát ad |
| redirect mismatch (authorize/token) | 302 validation (authorize) / 422 JSON (token) | redirect vagy JSON | a token fázis elbukik |
| disallowed scope (authorize) | 302 + validation session hibák (`scope`) | szerver oldali redirect | nem callback-alapú |
| invalid authorize request structure | 302 + validation session hibák | szerver oldali redirect | nem callback-alapú |
| client access denied after valid authorize validation | 302 redirect `error=access_denied` paraméterekkel | kliens callback | refusal üzenet, nincs lokális login |
| missing state (callback) | n/a, kliens oldali callback validáció | query a kliens callbackre | a kliens elutasítja |
| invalid state (callback) | n/a, kliens oldali callback validáció | query a kliens callbackre | a kliens elutasítja |
| missing code (callback) | n/a, kliens oldali callback validáció | query a kliens callbackre | a kliens elutasítja |
| provider authorize callback error (`invalid_request`) | provider által küldött callback hiba | kliens callback | külön authorize hibaüzenet, nem generikus belső hiba |
| invalid/expired/reused code (token) | 422 JSON envelope `errors.code` mezővel | JSON | a kliens elutasítja a token exchange-et |
| token endpoint failure/network | n/a | transport hiba | a kliens elutasítja a token exchange-et |
| userinfo unauthorized | 401 JSON envelope | JSON | a kliens elutasítja a userinfo fázist |
| userinfo forbidden | 403 JSON envelope | JSON | a kliens elutasítja a userinfo fázist |
| forbidden self-service profile field | 422 JSON envelope mezőszintű hibákkal | JSON | a kliens section-level vagy field-level hibákat renderel |
| unauthorized protected route (client app) | 302 login redirect (HTML) / 401 JSON (`reauth_to`) | redirect vagy JSON | explicit re-auth viselkedés |

## 10. Konfigurációs szerződés

A kliens konfigurációjának tartalmaznia kell:

- `SSO_SERVER_BASE_URL`
- `SSO_AUTHORIZE_ENDPOINT`
- `SSO_TOKEN_ENDPOINT`
- `SSO_USERINFO_ENDPOINT`
- `SSO_CLIENT_ID`
- `SSO_CLIENT_SECRET` ha confidential client auth szükséges
- `SSO_REDIRECT_URI`
- `SSO_SCOPES`, és kötelezően tartalmaznia kell az `email` scope-ot, mert a kliens session mappinghez szükséges a `userinfo.email`
- az `openid` scope jelen iterációban már nem általános konfigurációs kötelezettség, de ha szerepel a kért scope-ok között, a kliens nonce-ot generál és a szerver nonce-ot vár el

Nonce szerződés a konfigurációhoz:

- ha az `SSO_SCOPES` tartalmazza az `openid` scope-ot, az `sso_client` minden authorize redirecthez nonce-ot generál és elküld
- a szervernek az `openid` scope-ot kérő authorize requestet csak nonce-szal szabad elfogadnia
- sikeres callback után a kliens retained identity-validation contextben tovább őrzi az expected nonce-ot a későbbi összevetéshez

A szerver konfigurációjának / adatainak ehhez igazodnia kell:

- létezik OAuth kliens ugyanazzal a `client_id` értékkel
- az engedélyezett `redirect_uri` pontosan tartalmazza a `SSO_REDIRECT_URI` értéket
- az engedélyezett scope-ok tartalmazzák a kliens által kért scope-okat
- a token policy PKCE beállításai kompatibilisek a kliens kérésével
- a szerver CORS pontosan engedi az `sso_client` browser origint a közvetlen profile API hívásokhoz

## 11. Szerződést védő tesztlefedettség

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

## 12. Planned consent flow contract (specification only)

Ez a szakasz nem jelenlegi implementációt ír le, hanem a következő consent-flow kör célállapotát rögzíti.

### 12.1. Current gap

Jelenleg a kliens erre van felkészítve:

- success callback `code` és `state` paraméterrel
- authorize refusal vagy provider authorize hiba callback `error` alapon

Jelenleg nincs külön consent képernyő, mert a szerver valid authorize request után automatikusan approve-ol vagy refusal redirectet épít.

### 12.2. Future success callback

Approve után a kliens szerződése változatlan marad:

- `code` kötelező
- `state` kötelező, ha eredetileg szerepelt a requestben

Kliens reakció:

1. callback validáció
2. state ellenőrzés
3. token exchange
4. userinfo
5. lokális session létrehozás

### 12.3. Future refusal callback

Consent deny esetén a szerver ezt küldi vissza:

- `error=access_denied`
- `error_description` opcionális
- `state` visszaadva, ha az eredeti request tartalmazta

Kliens reakció:

- refusalként kezeli
- rövid, nem technikai üzenetet mutat
- ne általános belső hibát jelenítsen meg
- a user értse, hogy az alkalmazás hozzáférését utasította el

### 12.4. Future authorize callback error separation

A kliensnek továbbra is külön kell kezelnie:

- user-originated refusal callback (`access_denied`)
- provider-originated authorize callback hibák
- belső klienshibák a callback, token vagy userinfo fázisban

Ez a szétválasztás a STORY-07 szerződés folytatása, és a consent flow bevezetése után sem keverhető össze.

### 12.5. Client UX baseline

Approve után:

- nincs extra UX ág, a normál login folytatódik

Deny után:

- egyértelmű refusal üzenet
- újrapróbálás lehetősége a login entrypointon

Provider authorize error után:

- maradjon külön authorize hibaüzenet
- ne essen vissza az általános “valami elromlott” ágra

### 12.6. Client-side audit expectation

A kliens oldalon legalább ezek az audit események szükségesek majd:

- `client_auth.authorize_refusal.received`
- `client_auth.authorize_error.received`

Minimum payload:

- `provider_error`
- `provider_error_description`
- `callback_result`
- request context mezők

Nem logolható:

- access token
- refresh token
- authorization code
- PKCE verifier
- client secret

## 13. Planned trust tier and consent bypass contract (specification only)

Ez a szakasz a jövőbeli trust-tier alapú consent döntést rögzíti, implementáció nélkül.

### 13.1. Current state

Jelenleg a kliens felől nincs explicit trust-tier szerződés:

- a kliens nem kap trust policy adatot
- a szerver ma nem különböztet meg dokumentált first-party / third-party kategóriákat authorize döntéskor
- a kliens csak azt látja, hogy success callback vagy authorize hiba érkezik

### 13.2. Future expected behavior

A jövőbeli szerveroldali trust policy három döntési eredményt adhat:

- `show_consent`
- `skip_consent`
- `deny_authorization`

A kliens számára ennek látható következménye:

- `skip_consent` -> normál success callback, nincs köztes consent képernyő
- `show_consent` -> a user provider consent képernyőt lát, majd approve vagy deny történik
- `deny_authorization` -> authorize refusal vagy provider-side hiba a STORY-07 szerződés szerint

### 13.3. UX expectation

Consent skip esetén:

- a kliens oldalán nincs külön UX ág
- a mostani success callback flow marad

Consent required esetén:

- a user átmenetileg a provider consent képernyőjét látja
- approve után normál success callback jön
- deny után refusal callback jön

### 13.4. Client responsibility boundary

A kliensnek nem kell nyers trust-tier mezőt ismernie.

A kliens szerződéses felelőssége:

- success callback helyes kezelése
- refusal callback helyes kezelése
- provider authorize error helyes kezelése

Vagyis a trust modell szerveroldali policy, a kliens számára pedig csak UX-következmény.

## 14. Planned remembered consent contract (specification only)

Ez a szakasz a jövőbeli remembered consent policy kliensre látható hatását rögzíti, implementáció nélkül.

### 14.1. Current state

Jelenleg nincs remembered consent.

A kliens ma nem tud különbséget tenni:

- friss user approve
- vagy háttérben újrahasznált korábbi consent

és erre nincs is szüksége.

### 14.2. Future visible behavior

Ha a szerver remembered consent alapján skipeli a consent képernyőt:

- a kliens normál success callbacket kap
- nincs külön callback paraméter
- nincs külön remembered-consent protokoll

Ha a remembered consent nem használható:

- a normál consent flow jelenik meg
- approve után success callback
- deny után refusal callback

### 14.3. Client UX expectation

A kliens UX szempontjából:

- nem kell külön üzenetet mutatni arról, hogy a consent remembered consent miatt lett skipelve
- a kliensnek csak a success / refusal / provider error hármasra kell stabilan reagálnia

### 14.4. Contract boundary

A kliensnek nem kell tudnia:

- létezett-e consent rekord
- mikor járt le
- mi volt az invalidation oka
- milyen trust-tier vagy policy döntés miatt történt skip

Ez teljesen szerveroldali policy maradjon.

## 15. OIDC-grade nonce, logout, and session-boundary roadmap

Ez a szakasz az implementalt foundationre epitő tovabbi iranyt rogziti.

### 15.1. Current state

`nonce`

- jelenleg nincs explicit `nonce` a kliens authorize requestben
- a kliens ma `state`-et és `pkce_verifier`-t kezel

Logout

- a kliens mar tud provider logoutot kezdemenyezni
- a provider return kulon route-on kezelodik
- tovabbra sincs cross-app logout propagation

Session boundary

- a kliens lokális sessionje külön él a provider sessiontől
- ez implicit módon már igaz, de most válik explicit szerződéssé

### 15.2. Future nonce expectation

A jövőbeli OIDC-grade irányban:

- a kliens generál majd `nonce`-ot
- azt authorize requestben továbbítja
- sessionben tárolja
- a későbbi OIDC response / ID token validáció ehhez fog kapcsolódni

Az első iterációban a kliens callback branch-je emiatt még nem bővül külön protokollággal.

### 15.3. Logout maturity expectation

A kliens szempontjából három fogalom különül el:

- `local logout`
- `provider logout`
- később `cross-app logout`

Jelenlegi érettségi szint:

- a local logout tamogatott es explicit
- a provider logout kulon szerzodest kapott
- a cross-app logout kesobbi roadmap fazis

### 15.4. Session boundary expectation

A kliensnek a jövőben is úgy kell működnie, hogy:

- a provider session és a client session nem ugyanaz
- local logout nem feltétlenül jelenti a provider logoutot
- provider logout nem feltétlenül propagálódik azonnal minden kliensre
- reauth requirement külön kezelhető állapot marad

### 15.5. Client UX expectation

A user jövőben ezt tapasztalhatja:

- kijelentkezett ebből az appból, de a központi szolgáltatásban még lehet sessionje
- a központi szolgáltatásból kijelentkezett, de más kliensek lokális sessionje még rövid ideig élhet, amíg nincs teljes propagation

Ezt a boundary-t a kliens UX-nek később világosan kell kommunikálnia, nem elrejtenie.

## 16. OIDC session cleanup lifecycle

Az OIDC `sid` mapping most explicit lifecycle adatként kezeli a kliensoldali session-korrelációt:

- `bound_at`: mikor kötöttük a provider `sid` értéket a lokális Laravel sessionhöz
- `last_seen_at`: mikor frissült utoljára ez a kapcsolat
- `invalidated_at`: mikor zárta le logout vagy cleanup a mappinget

Logout után a kliens nem hagy aktívnak látszó `sid` mappinget:

- local/provider logout esetén a jelenlegi session mapping invalidálódik
- front-channel logoutnál csak valid `iss` + `client_id` és `sid` match után történik cleanup
- front-channel `sid` mismatch kontrollált no-op, nem sérti a lokális mappinget
- back-channel logoutnál a signed token verify és replay guard után a `sid`-hez vagy fallbackként session/user ághoz tartozó mapping invalidálódik
- replayelt back-channel token nem indít második cleanup hullámot

Retention policy:

- `SSO_OIDC_SESSION_MAPPING_RETENTION_SECONDS`: invalidált mappingek törlési ablaka, alapértelmezés szerint 604800 másodperc
- `SSO_OIDC_LOGOUT_RECEIPT_RETENTION_SECONDS`: receipt retention szerződés későbbi ütemezett cleanuphoz, az aktuális replay ablakot továbbra is a token `exp` + clock skew védi

Foundation határ:

- nincs multi-device session dashboard
- nincs distributed session inventory
- nincs advanced session analytics
- a cleanup service később Artisan commandhoz vagy schedulerhez köthető
