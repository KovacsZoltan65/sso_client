# SSO_CLIENT – Emergency Access (Break-Glass Mode) használati dokumentáció

Ez a dokumentáció az `sso_client` alkalmazás **Emergency Access / Break-Glass Mode** funkciójának használatát írja le.

Cél:

- SSO kiesés esetén **korlátozott, biztonságos hozzáférés biztosítása**
- Nem cél: teljes értékű fallback auth rendszer

---

# 🧭 Áttekintés

## Mi ez?

Az Emergency Mode egy **izolált, auditált, read-only-first hozzáférési mód**, amely:

- külön auth guardot használ (`emergency`)
- külön sessionben fut
- külön route namespace-en érhető el (`/emergency/*`)
- nem használja a normál `users` rendszert
- nem használja az SSO authot
- nem biztosít teljes funkcionalitást

---

## Mikor használjuk?

✅ Használható:

- SSO szerver kiesése esetén
- amikor a normál login nem működik
- operátori diagnosztikára
- audit és státusz ellenőrzésre

❌ Nem használható:

- normál napi működésre
- admin feladatok elvégzésére
- adatmódosításra
- SSO megkerülésére

---

# ⚙️ Állapotok

A rendszer három állapotot ismer:

| Állapot              | Jelentés                                    |
| -------------------- | ------------------------------------------- |
| `normal`             | SSO működik, normál rendszer                |
| `degraded_available` | SSO problémás, de nincs emergency aktiválva |
| `emergency_active`   | Emergency mód aktív                         |

---

# 🚨 Emergency mód aktiválása

## Előfeltételek

- SSO ténylegesen nem elérhető
- incidens/ticket létrehozva (pl. `INC-1234`)
- operátor azonosított

---

## Aktiválás parancs

```bash
php artisan emergency:activate --reason="INC-1234 SSO outage" --operator="email@company.com"
```
