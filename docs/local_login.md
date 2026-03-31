# SSO_CLIENT – Local Fallback Auth használati dokumentáció és operátori runbook

Ez a dokumentáció az `sso_client` alkalmazás korlátozott local fallback auth módjának használatát írja le.

Fontos:

- ez nem normál auth mód
- ez nem általános local login
- ez nem második teljes értékű auth rendszer
- ez csak SSO outage esetére szolgáló, korlátozott, auditált fallback mód

---

# 1. Cél

A local fallback auth célja, hogy az `sso_server` tényleges elérhetetlensége esetén az `sso_client` korlátozottan tovább használható maradjon.

A fallback mód:

- explicit feature flag mögött van
- csak SSO outage esetén enged local logint
- healthy SSO mellett blokkolt
- csak allowlistelt local-only felhasználókkal működik
- csak korlátozott hozzáférést ad
- minden fontos eseményt auditál

---

# 2. Mikor használható

## Használható

- ha az SSO ténylegesen nem elérhető
- ha a normál SSO login nem működik
- ha az üzemi minimum fenntartása indokolja
- ha az incidens dokumentálva van

## Nem használható

- normál napi működésre
- kényelmi local loginra
- healthy SSO mellett
- teljes admin fallbackként
- user / role / permission admin helyettesítésére
- password reset / register / teljes auth lifecycle pótlására

---

# 3. Fő működési elv

A fallback mód három fő tényezőt vizsgál:

1. feature flag
2. SSO reachability
3. fallback allowlist

A local login csak akkor használható, ha egyszerre igaz, hogy:

- a feature engedélyezett
- az SSO nem elérhető
- a fallback policy ezt megengedi

Ha az SSO elérhető:

- a local login oldal blokkolt
- a local login submit blokkolt
- warning jelenik meg
- audit event keletkezik

---

# 4. Konfiguráció

## Fő env változók

```env
SSO_LOCAL_AUTH_ENABLED=false
SSO_LOCAL_AUTH_REQUIRE_SSO_UNREACHABLE=true
SSO_LOCAL_AUTH_CHECK_TIMEOUT_MS=1500
SSO_LOCAL_AUTH_HEALTHY_CACHE_SECONDS=20
SSO_LOCAL_AUTH_UNREACHABLE_CACHE_SECONDS=5
SSO_LOCAL_AUTH_FAILURE_THRESHOLD=2
SSO_LOCAL_AUTH_INCIDENT_ID_REQUIRED=true
SSO_LOCAL_AUTH_INCIDENT_ID=
SSO_READINESS_ENDPOINT=
```

## Jelentésük

`SSO_LOCAL_AUTH_ENABLED`

- engedélyezi vagy tiltja a fallback képességet
- önmagában nem nyitja meg a local login oldalt
  `SSO_LOCAL_AUTH_REQUIRE_SSO_UNREACHABLE`
- ha `true`, akkor csak tényleges outage esetén használható a fallback
  `SSO_LOCAL_AUTH_CHECK_TIMEOUT_MS`
- az SSO ellenőrzés timeoutja
  `SSO_LOCAL_AUTH_HEALTHY_CACHE_SECONDS`
- healthy állapot cache ideje
  `SSO_LOCAL_AUTH_UNREACHABLE_CACHE_SECONDS`
- unreachable állapot cache ideje
  `SSO_LOCAL_AUTH_FAILURE_THRESHOLD`
- hány egymást követő hard failure után tekintjük az SSO-t ténylegesen elérhetetlennek
  `SSO_LOCAL_AUTH_INCIDENT_ID_REQUIRED`
- ha `true`, a fallback használat incident-driven működésre van tervezve
  `SSO_LOCAL_AUTH_INCIDENT_ID`
- az aktuális incidens vagy ticket azonosítója
- ajánlott minden fallback használathoz kitölteni
  `SSO_READINESS_ENDPOINT`
- opcionális dedikált readiness endpoint
- ha nincs megadva, a kliens authorize probe-ra esik vissza

---

# 5. Runtime viselkedés

## A) `SSO_LOCAL_AUTH_ENABLED=false`

- nincs fallback auth
- `/login` normál SSO belépési oldal
- `/local-login` tiltott
- `POST /local-login` tiltott

## B) `SSO_LOCAL_AUTH_ENABLED=true` + SSO reachable

- `/login` normál SSO oldal
- admin warning látható lehet
- `/local-login` blokkolt
- `POST /local-login` blokkolt
- warning audit keletkezik

## C) `SSO_LOCAL_AUTH_ENABLED=true` + SSO unreachable

- `/login` decision page-ként működik
- a user láthatja, hogy fallback mód engedélyezett
- `/local-login` elérhető
- `POST /local-login` csak allowlistelt fallback userrel működik
- fallback session banner látható
- a session csak korlátozott route-okat érhet el

---

# 6. Milyen felhasználó léphet be fallback módban

Csak olyan lokális felhasználó, aki megfelel az allowlist szabályoknak.

A fallback loginhoz a usernek legalább az alábbi feltételeknek kell megfelelnie:

- `fallback_auth_enabled = true`
- `sso_user_id = null`
- `local_status = active`

Fontos:

- a fallback user nem normál SSO-projected user
- a fallback user nem a teljes normál userbázis helyettesítője
- a fallback userkészlet dedikált és korlátozott

---

# 7. Jogosultságok fallback módban

A fallback mód nem nyitja ki automatikusan a teljes normál RBAC világot.

## Engedett területek

- `dashboard.view`
- `account.view`
- szűkített, read-only jellegű üzemi nézetek, ha külön engedélyezve vannak

## Tiltott területek

- profile edit
- users
- companies
- roles
- permissions
- SSO status admin
- audit logs admin, ha nincs külön engedélyezett fallback nézet
- bármilyen magas kockázatú admin CRUD
- register
- password reset
- password change fallback célból

Fontos:

- a fallback session route allowlist + session mode alapján korlátozott
- nem a teljes normál admin menüt használja

---

# 8. UI viselkedés

## `/login`

A normál login oldal decision page-ként viselkedik.

Lehetséges állapotok:

- normál SSO login
- healthy SSO melletti fallback warning
- outage melletti fallback lehetőség

## `/local-login`

Csak akkor érhető el, ha a fallback policy ezt megengedi.

A fallback login oldalon világosan jelenjen meg:

- hogy helyi hitelesítés történik
- hogy az SSO nem elérhető
- hogy korlátozott fallback mód fut

## Layout banner

Fallback session alatt globális banner jelenik meg, például:

- Helyi hitelesítés aktív
- SSO szerver nem elérhető
- Korlátozott fallback mód

A felhasználó soha ne higgye, hogy normál üzemben dolgozik.

---

# 9. Audit logging

A fallback mód minden fontos állapotot auditál.

## Fő eventek

- `client_auth.local_fallback.page_blocked`
- `client_auth.local_fallback.page_allowed`
- `client_auth.local_fallback.login_succeeded`
- `client_auth.local_fallback.login_failed`
- `client_auth.local_fallback.logout`
- `client_security.local_fallback.sso_reachable_warning`

## Logolt metaadatok

- `fallback_mode`
- `reachability_state`
- `incident_id`
- `failure_count`
- `session_mode`

A rendszer nem logolhat érzékeny adatokat.

---

# 10. Operátori használati folyamat

## 1. SSO hiba észlelése

- ellenőrizd, hogy az SSO valóban nem elérhető
- zárd ki a kliensoldali konfigurációs vagy hálózati hibát

## 2. Incidens rögzítése

- hozz létre incident / ticket azonosítót
- dokumentáld az okot

## 3. Feature flag ellenőrzése

- ellenőrizd, hogy a fallback feature engedélyezve van-e
- ha nincs, mérlegeld a kontrollált aktiválást

## 4. Login decision page ellenőrzése

- nyisd meg a `/login` oldalt
- ellenőrizd, hogy a fallback valóban csak outage esetén jelenik meg

## 5. Local fallback login

- csak dedikált fallback userrel jelentkezz be
- ne használj normál SSO-projected usereket fallback célra

## 6. Korlátozott használat

- csak a minimálisan szükséges műveleteket végezd
- ne próbálj tiltott területeket kerülőúton elérni

## 7. Helyreállás után

- ellenőrizd, hogy az SSO újra elérhető
- állítsd vissza a feature flaget, ha szükséges
- lépj ki fallback sessionből
- zárd le az incidenst
- review-ozd az audit eseményeket

---

# 11. Mit kell ellenőrizni fallback használat előtt

Kötelező ellenőrzések:

- az SSO valóban nem elérhető
- a fallback route tényleg csak outage esetén nyílik meg
- a login submit healthy SSO mellett tényleg blokkol
- a fallback banner látszik
- a fallback session nem jut be tiltott admin területekre
- audit események létrejönnek

---

# 12. Mit kell ellenőrizni fallback használat után

- visszaállt-e az SSO
- a fallback session megszűnt-e
- keletkeztek-e tiltott hozzáférési próbák
- minden fallback login/logout auditálódott-e
- nem maradt-e feleslegesen aktív a feature flag
- szükséges-e fallback user review vagy jelszócsere

---

# 13. Tiltott gyakorlatok

Tilos:

- healthy SSO mellett fallback logint használni
- a fallback route-okat direkt URL-lel erőltetni healthy SSO alatt
- a fallback userkészletet normál admin userkészletté bővíteni
- a teljes normál users / roles / permissions világot fallbackhez visszakötni
- register / password reset / teljes auth lifecycle fallbackhez hozzáépíteni
- a fallback sessionnel admin CRUD felületeket megnyitni
- a fallback módot tartós második auth útként használni
- az audit figyelmen kívül hagyni
- az incident review-t kihagyni

---

# 14. Gyors operátori checklist

## Aktiválás / használat előtt

- Az SSO tényleg nem elérhető
- Incident / ticket létrehozva
- A fallback feature kontrolláltan engedélyezett
- `/login` decision page helyesen működik
- `/local-login` csak tényleges outage esetén érhető el

## Használat közben

- Csak fallback allowlistelt usert használok
- A banner látszik
- Nem nyitok meg tiltott admin területeket
- Az audit logok képződnek

## Használat után

- Az SSO helyreállását ellenőriztem
- Kiléptem a fallback sessionből
- A fallback flag visszaállítása megtörtént, ha szükséges
- Az audit review megtörtént
- Az incident lezárása megtörtént

---

# 15. Rövid fejlesztői megjegyzés

A local fallback auth célja:

- üzemi minimum fenntartása SSO outage esetén

Nem célja:

- a kliens identity source-szá alakítása
- teljes local auth rendszer fenntartása
- normál admin funkcionalitás kiváltása

Ezért minden további bővítést ezen a szűk elven belül kell tartani:

- explicit
- outage-függő
- auditált
- korlátozott
- könnyen visszakapcsolható normál módra
