# SSO_CLIENT – Local Fallback Auth tesztelési folyamat lépésről lépésre

Ez a dokumentáció az `sso_client` alkalmazás **korlátozott local fallback auth** működésének kézi és részben automatizált tesztelését írja le.

Cél:

- ellenőrizni, hogy a fallback auth csak a tervezett feltételek mellett működik
- kizárni, hogy a fallback út egészséges SSO mellett használható legyen
- ellenőrizni a route-, session-, audit- és UI-korlátokat
- megbizonyosodni arról, hogy a fallback nem nyit újra teljes második auth rendszert

---

# 1. Előfeltételek

A tesztelés megkezdése előtt ellenőrizd:

- az `sso_client` alkalmazás fut
- az adatbázis migrációk lefutottak
- a fallback implementációhoz tartozó új mezők és route-ok bent vannak
- van legalább egy fallback allowlistelt lokális felhasználó
- az SSO normál esetben elérhető
- a cache/config frissíthető

Ajánlott környezet:

- lokális dev környezet
- külön teszt adatbázis vagy biztonságos dev adatbázis
- ne production legyen az első kipróbálás

---

# 2. Előkészítő ellenőrzések

## 2.1. Fallback user meglétének ellenőrzése

Ellenőrizd, hogy van olyan user, aki megfelel a fallback feltételeknek:

- `fallback_auth_enabled = true`
- `sso_user_id = null`
- `local_status = active`

Ha nincs, hozz létre egy teszt fallback usert.

## 2.2. Legacy auth route-ok ne legyenek aktívak

Ellenőrizd route listával, hogy:

- nincs véletlenül aktív register
- nincs véletlenül aktív password reset
- nincs régi local login route, ami a fallback logikát megkerüli

Parancs:

```bash
php artisan route:list
```

Külön figyeld:

- GET /login
- GET /local-login
- POST /local-login
- POST /logout

3. Konfigurációs alaphelyzet teszt
   3.1. Fallback feature kikapcsolt állapot
   Állítsd be:
   SSO_LOCAL_AUTH_ENABLED=false

Utána:
php artisan config:clear
php artisan cache:clear

3.2. Elvárt viselkedés
Teszteld:

- GET /login
- GET /local-login
- POST /local-login
  Elvárt:
- /login normál SSO döntési oldal
- /local-login tiltott
- POST /local-login tiltott

    3.3. Ellenőrizendő pontok

- nem jelenik meg fallback link
- nem jelenik meg fallback banner
- healthy SSO mellett nincs fallback UI
- nincs fallback login lehetőség

4. Healthy SSO melletti fallback blokk teszt
   4.1. Feature engedélyezése

Állítsd be:
SSO_LOCAL_AUTH_ENABLED=true
SSO_LOCAL_AUTH_REQUIRE_SSO_UNREACHABLE=true
SSO_LOCAL_AUTH_CHECK_TIMEOUT_MS=1500
SSO_LOCAL_AUTH_HEALTHY_CACHE_SECONDS=20
SSO_LOCAL_AUTH_UNREACHABLE_CACHE_SECONDS=5
SSO_LOCAL_AUTH_FAILURE_THRESHOLD=2
SSO_LOCAL_AUTH_INCIDENT_ID_REQUIRED=true

Utána:

```bash
php artisan config:clear
php artisan cache:clear
```

4.2. Győződj meg róla, hogy az SSO elérhető
Normál működő SSO mellett nyisd meg:

- /login

    4.3. Elvárt viselkedés
    Elvárt:

- a normál SSO belépési út látható
- megjelenhet figyelmeztetés, hogy a fallback feature aktív
- a local fallback login nem használható

Ellenőrizd:

- GET /local-login → blokkolva
- POST /local-login → blokkolva

    4.4. Audit ellenőrzés

Nézd meg, hogy létrejött-e megfelelő warning / blocked audit esemény, például:

- client_security.local_fallback.sso_reachable_warning
- client_auth.local_fallback.page_blocked

5. SSO outage szimuláció teszt

Ez a legfontosabb teszt.

5.1. Outage szimuláció előkészítése
Valamilyen kontrollált módon érd el, hogy az sso_client ne tudja elérni az SSO-t.

Lehetséges módszerek:

- ideiglenesen rossz SSO host/config
- leállított sso_server
- hibás readiness endpoint
- lokális hosts/network szintű blokkolás

FONTOS:

- csak tesztkörnyezetben csináld
- dokumentáld, pontosan mit állítottál át

    5.2. Cache ürítése

```bash
php artisan cache:clear
php artisan config:clear
```

5.3. Elvárt viselkedés
Nyisd meg:

- /login
  Elvárt:
- a login decision page jelzi, hogy fallback mód használható
- világos warning jelenik meg:
    - SSO nem elérhető
    - helyi hitelesítés használható
    - korlátozott mód

        5.4. Local login oldal teszt
        Nyisd meg:

- GET /local-login
  Elvárt:
- az oldal elérhető
- a warning/banner látszik
- egyértelmű, hogy ez nem normál auth mód

    5.5. Audit ellenőrzés
    Létrejöhet például:

- client_auth.local_fallback.page_allowed

6. Sikeres fallback login teszt
   6.1. Jelentkezz be fallback userrel
   Használj olyan usert, aki:

- allowlistelt
- local-only
- aktív

    6.2. Elvárt viselkedés
    Elvárt:

- login sikeres
- session mode local_fallback
- globális fallback banner látható
- a felhasználó csak a megengedett route-okat éri el

    6.3. Ellenőrizd a banner állapotot

- Authenticated layoutban jelenjen meg valami ehhez hasonló:
- Helyi hitelesítés aktív
- SSO szerver nem elérhető
- Korlátozott fallback mód

    6.4. Audit ellenőrzés
    Létrejön:

- client_auth.local_fallback.login_succeeded

7. Sikertelen fallback login teszt
   7.1. Rossz jelszó

Próbálj rossz jelszóval belépni.
Elvárt:

- login sikertelen
- session nem jön létre
- hibaüzenet megjelenik
- audit log létrejön

    7.2. Nem allowlistelt user
    Próbálj olyan userrel belépni, aki:

- nem fallback_auth_enabled
- vagy van sso_user_id
- vagy nem aktív
  Elvárt:
- login sikertelen
- audit log létrejön
- nem jön létre fallback session

    7.3. Audit esemény
    Ellenőrizd:

- client_auth.local_fallback.login_failed

8. TOCTOU védelem teszt

Ez kritikus.

8.1. Teszt menete

1. Érd el, hogy az SSO ne legyen elérhető
2. Nyisd meg a GET /local-login oldalt
3. Mielőtt submitolsz, állítsd vissza az SSO elérhetőséget
4. Küldd el a POST /local-login kérést

8.2. Elvárt eredmény
Elvárt:

- a login submit blokkolódik
- nem jön létre session
- a rendszer érzékeli, hogy közben helyreállt az SSO
- audit log keletkezik

Ez igazolja, hogy nem csak GET oldali, hanem POST oldali ellenőrzés is van.

9. Jogosultsági korlátok tesztje
   9.1. Engedett oldalak

Fallback sessionnel próbáld meg elérni:

- dashboard
- account view
- minden olyan oldal, amit az implementáció enged
  Elvárt:
- csak a minimális allowlist működik

    9.2. Tiltott oldalak
    Próbáld elérni:

- users
- companies
- roles
- permissions
- profile edit
- admin CRUD oldalak
- audit log admin nézetek, ha nincsenek fallbackhez megnyitva
  Elvárt:
- tiltva
- redirect vagy 403 vagy explicit block, a megvalósítástól függően
- audit esemény opcionálisan keletkezhet

    9.3. API végpontok tesztje
    Ha vannak admin API route-ok:

- próbáld elérni őket fallback sessionnel
  Elvárt:
- tiltva

10. Logout teszt
    10.1. Fallback session logout
    Jelentkezz ki fallback sessionből.
    Elvárt:

- session megszűnik
- a banner eltűnik
- visszakerülsz megfelelő oldalra
- audit log létrejön
  10.2. Audit esemény
  Ellenőrizd:
- client_auth.local_fallback.logout

11. SSO helyreállás utáni viselkedés teszt
    11.1. SSO visszaállítása
    Állítsd vissza az SSO normál elérhetőségét.

11.2. Ellenőrzés
Nyisd meg újra:

- /login
- /local-login
  Elvárt:
- /login normál SSO mód
- /local-login újra blokkolt
- local fallback submit blokkolt
- warning megjelenhet, ha a flag még aktív

    11.3. Operátori ellenőrzés
    Ha a flag aktív maradt healthy SSO mellett:

- ezt tekintsd operációs figyelmeztetésnek
- állítsd vissza a flaget

12. Fallback flag visszaállítás teszt
    Állítsd vissza:

```bash
SSO_LOCAL_AUTH_ENABLED=false
```

Majd:

- php artisan config:clear
- php artisan cache:clear
  Ellenőrizd:
- /login normál mód
- /local-login tiltott
- nincs fallback banner
- nincs fallback döntési ág

13. Frontend vizuális ellenőrzések

Kézzel ellenőrizd:

Login decision page

- healthy SSO állapot
- fallback flag active + healthy warning
- outage + fallback link
  Local fallback login page
- erős warning
- nem tűnik normál login oldalnak
- fallback-only szövegek helyesek
  Authenticated fallback layout
- banner látszik
- navigáció szűkített
- tiltott menük nem látszanak vagy nem használhatók

14. Audit log ellenőrzési lista

Teszteld, hogy az alábbi események tényleg létrejönnek:

- client_auth.local_fallback.page_blocked
- client_auth.local_fallback.page_allowed
- client_auth.local_fallback.login_succeeded
- client_auth.local_fallback.login_failed
- client_auth.local_fallback.logout
- client_security.local_fallback.sso_reachable_warning

Ellenőrizd azt is, hogy a property mezők megfelelően kerülnek mentésre:

- fallback_mode
- reachability_state
- incident_id
- failure_count
- session_mode

És hogy érzékeny adat nem kerül a logba.

15. Automatizált tesztek futtatása

Futtasd a backend teszteket:

```bash
php artisan test tests/Feature/Auth/LocalFallbackAuthenticationTest.php tests/Feature/Auth/SsoAuthenticationTest.php tests/Feature/AppAccessTest.php
```

Futtasd a frontend teszteket:

```bash
npm test -- AuthLogin
```

Elvárt:

- minden releváns teszt pass
- regresszió nincs normál SSO flow-ban

16. Teljes tesztzáró checklist
    Alap működés

- Flag kikapcsolva → fallback tiltott
- Healthy SSO + flag aktív → fallback blokkolt
- SSO outage + flag aktív → fallback engedélyezett
  Login működés
- Allowlistelt fallback user be tud lépni
- Rossz jelszó blokkolt
- Nem allowlistelt user blokkolt
- POST oldali újraellenőrzés működik
  Jogosultság
- Fallback session csak minimális oldalakat lát
- Tiltott admin route-ok nem érhetők el
- Nincs teljes RBAC újranyitás
  UI
- Warningok helyesek
- Banner látszik fallback session alatt
- Healthy SSO mellett a local login nem tűnik használhatónak
  Audit
- Minden fő fallback event logolódik
- Nincs érzékeny adat a logban
  Recovery
- SSO helyreállás után a fallback újra blokkolódik
- A flag visszaállítható és a rendszer normál módba kerül

17. Ajánlott hibajegyzék teszt közben

Ha hibát találsz, rögzítsd legalább ezeket:

- lépés sorszáma
- környezet
- aktuális env/config
- SSO reachable vagy unreachable állapot
- használt user típusa
- elvárt eredmény
- tényleges eredmény
- audit log létrejött-e
- screenshot / request / response részletek

18. Rövid összefoglalás
    A fallback auth teszt akkor tekinthető sikeresnek, ha:

- healthy SSO mellett soha nem használható
- outage alatt kontrolláltan használható
- csak dedikált fallback userrel működik
- nem nyitja meg a teljes normál admin világot
- a session jól láthatóan fallback módú
- minden fontos esemény auditált
- az SSO helyreállása után visszaáll a normál működés
