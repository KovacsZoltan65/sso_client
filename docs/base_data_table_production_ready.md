# BaseDataTable – Production Ready útmutató

## Cél

Ez az útmutató arra való, hogy egy új vagy kevésbé rutinos fejlesztő valódi, éles használatra alkalmas admin listaoldalt tudjon készíteni a projekt szabványai szerint.

A dokumentum végére lesz egy olyan listaoldalad, amely:

- backendből tölti az adatokat
- támogatja a keresést
- támogatja a szűrést
- támogatja a lapozást
- támogatja a rendezést
- kezeli a bulk kijelölést
- kezeli a hibákat
- egységes toolbar és summary mintát használ

Ez nem csak demó.
Ez egy olyan minta, amit valódi Users, Companies, Employees, Roles, Permissions jellegű oldalakhoz is lehet alapnak használni.

---

## 1. Mikor ezt a mintát használd?

Akkor használd ezt a teljes mintát, ha az oldal:

- táblázatos admin listaoldal
- backendből tölt adatot
- szerveroldali lapozást használ
- kereshető vagy szűrhető
- soronként műveleteket tartalmaz
- van bulk művelet vagy várhatóan később lesz

Példák:

- Users
- Companies
- Employees
- Roles
- Permissions
- Clients
- Scopes
- Token Policies

---

## 2. Miből áll össze a teljes megoldás?

Egy production ready admin listaoldal jellemzően ezekből áll:

- `BaseDataTable`
- `useAdminTableState()`
- `AdminTableToolbar`
- `AdminTableSummary`
- `useAdminTableSelection()` – ha van bulk
- opcionálisan `RowActionMenu`
- saját `loadRows()` függvény
- saját API service vagy fetch hívás
- saját oldal-specifikus filter state
- saját hiba- és toast kezelés

Röviden:

- `BaseDataTable` = közös UI shell
- `useAdminTableState()` = lapozás, rendezés, filter state
- `AdminTableToolbar` = kereső, filter, akciók
- `AdminTableSummary` = footer összegzés
- `useAdminTableSelection()` = bulk kijelölés
- `loadRows()` = adatbetöltés

---

## 3. A teljes minta röviden

### Mentális modell

Egy admin listaoldalban ez történik:

1. létrehozod a state-eket
2. létrehozod a table state composable-t
3. létrehozod a selection composable-t, ha kell
4. megírod a `loadRows()` függvényt
5. a toolbar módosítja a filtereket
6. a DataTable eseményei módosítják a page és sort állapotot
7. minden állapotváltozás után újratöltöd az adatokat
8. a backend meta alapján frissíted a táblázat state-jét

---

## 4. Teljes production ready példa

```vue
<script setup>
import { computed, onMounted, ref } from "vue";
import Column from "primevue/column";
import Select from "primevue/select";
import Button from "primevue/button";
import BaseDataTable from "@/Components/Admin/BaseDataTable.vue";
import AdminTableToolbar from "@/Components/Admin/AdminTableToolbar.vue";
import AdminTableSummary from "@/Components/Admin/AdminTableSummary.vue";
import RowActionMenu from "@/Components/Admin/RowActionMenu.vue";
import { useAdminTableState } from "@/Composables/useAdminTableState";
import { useAdminTableSelection } from "@/Composables/useAdminTableSelection";

const rows = ref([]);
const loading = ref(false);
const loadError = ref("");
const busyBulkAction = ref(false);

const statusOptions = [
    { label: "All", value: "" },
    { label: "Active", value: "active" },
    { label: "Inactive", value: "inactive" },
];

const {
    state: tableState,
    filters,
    first,
    lastPage,
    resetPagination,
    setPageFromEvent,
    setSortFromEvent,
    applyMeta,
    buildFetchParams,
} = useAdminTableState({
    initialSortField: "name",
    initialSortOrder: 1,
    initialFilters: {
        search: "",
        status: "",
    },
});

const {
    selectedRows,
    selectedIds,
    selectedCount,
    selectableCount,
    clearSelection,
    setSelectedRows,
} = useAdminTableSelection(computed(() => rows.value), {
    isRowSelectable: (row) => row?.canDelete !== false,
});

async function fetchUsers(params) {
    const query = new URLSearchParams(params).toString();
    const response = await fetch(`/api/users?${query}`, {
        method: "GET",
        headers: {
            Accept: "application/json",
        },
    });

    if (!response.ok) {
        throw new Error("Failed to load users.");
    }

    return response.json();
}

async function deleteUsersBulk(ids) {
    const response = await fetch("/api/users/bulk-delete", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
        },
        body: JSON.stringify({ ids }),
    });

    if (!response.ok) {
        throw new Error("Bulk delete failed.");
    }

    return response.json();
}

function getRequestParams() {
    return buildFetchParams({
        filters: {
            search: filters.search || undefined,
            status: filters.status || undefined,
        },
    });
}

async function loadRows() {
    loading.value = true;
    loadError.value = "";

    try {
        const response = await fetchUsers(getRequestParams());

        rows.value = response?.data?.items ?? [];
        applyMeta(response?.meta?.pagination ?? {});
        clearSelection();
    } catch (error) {
        rows.value = [];
        applyMeta({});
        loadError.value = error instanceof Error
            ? error.message
            : "Unknown loading error.";
    } finally {
        loading.value = false;
    }
}

function onPage(event) {
    setPageFromEvent(event);
    clearSelection();
    loadRows();
}

function onSort(event) {
    setSortFromEvent(event, "name");
    clearSelection();
    loadRows();
}

function onSearchUpdate(value) {
    filters.search = value;
    resetPagination();
    clearSelection();
    loadRows();
}

function onStatusChange(value) {
    filters.status = value;
    resetPagination();
    clearSelection();
    loadRows();
}

async function refresh() {
    clearSelection();
    await loadRows();
}

async function handleBulkDelete() {
    if (selectedIds.value.length === 0) {
        return;
    }

    const confirmed = window.confirm(
        `Are you sure you want to delete ${selectedIds.value.length} item(s)?`
    );

    if (!confirmed) {
        return;
    }

    busyBulkAction.value = true;

    try {
        await deleteUsersBulk(selectedIds.value);
        clearSelection();
        await loadRows();
        window.alert("Bulk delete finished successfully.");
    } catch (error) {
        window.alert(
            error instanceof Error
                ? error.message
                : "Bulk delete failed."
        );
    } finally {
        busyBulkAction.value = false;
    }
}

function getRowActions(row) {
    return [
        {
            label: "Edit",
            icon: "pi pi-pencil",
            command: () => {
                console.log("Edit", row.id);
            },
        },
        {
            label: "Delete",
            icon: "pi pi-trash",
            disabled: row.canDelete === false,
            command: () => {
                console.log("Delete", row.id);
            },
        },
    ];
}

onMounted(() => {
    loadRows();
});
</script>

<template>
    <BaseDataTable
        v-model:filters="filters"
        :value="rows"
        :loading="loading"
        :rows="tableState.perPage"
        :total-records="tableState.totalRecords"
        :first="first"
        data-key="id"
        lazy
        @page="onPage"
        @sort="onSort"
    >
        <template #header>
            <AdminTableToolbar
                title="Users"
                description="Manage users from a centralized admin table."
                searchable
                :search-value="filters.search"
                search-placeholder="Search users"
                :canBulkDelete="true"
                :selectedCount="selectedCount"
                :selectableCount="selectableCount"
                :busy="loading || busyBulkAction"
                @update:searchValue="onSearchUpdate"
                @bulk-delete="handleBulkDelete"
                @refresh="refresh"
            >
                <template #filters>
                    <Select
                        :model-value="filters.status"
                        :options="statusOptions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Status"
                        class="w-56"
                        @update:modelValue="onStatusChange"
                    />
                </template>

                <template #primary>
                    <Button
                        label="Create User"
                        icon="pi pi-plus"
                    />
                </template>
            </AdminTableToolbar>
        </template>

        <template #loading>
            <div class="p-6 text-center text-slate-500">
                Loading users...
            </div>
        </template>

        <template #empty>
            <div class="p-8 text-center text-slate-500">
                <div class="mb-2 text-lg font-medium">No users found.</div>
                <div>Try changing the search term or filters.</div>
            </div>
        </template>

        <Column selectionMode="multiple" headerStyle="width: 3rem" />

        <Column field="name" header="Name" sortable />
        <Column field="email" header="Email" sortable />
        <Column field="status" header="Status" sortable />

        <Column header="Actions" :exportable="false">
            <template #body="{ data }">
                <RowActionMenu :items="getRowActions(data)" />
            </template>
        </Column>
    </BaseDataTable>

    <div v-if="loadError" class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-red-700">
        {{ loadError }}
    </div>

    <AdminTableSummary
        :page="tableState.page"
        :per-page="tableState.perPage"
        :total="tableState.totalRecords"
        :last-page="lastPage"
        item-label="users"
    />
</template>
```

---

## 5. Lépésről lépésre magyarázat

### 5.1. `rows` és `loading`

```js
const rows = ref([]);
const loading = ref(false);
```

Ez a két legalapvetőbb state.

- `rows` = a táblázat sorai
- `loading` = éppen töltjük-e az adatot

Ha ezek nincsenek, nincs működő lista.

---

### 5.2. `loadError`

```js
const loadError = ref("");
```

Ez a betöltési hibákhoz kell.

Ez fontos production környezetben, mert a backend kérés elromolhat:

- hálózati hiba
- 500-as hiba
- hibás válasz
- auth probléma

Ha nincs hibaállapotod, a felhasználó csak egy üres táblát lát, és nem fogja érteni, mi történt.

---

### 5.3. `useAdminTableState()`

```js
const {
    state: tableState,
    filters,
    first,
    lastPage,
    resetPagination,
    setPageFromEvent,
    setSortFromEvent,
    applyMeta,
    buildFetchParams,
} = useAdminTableState(...)
```

Ez kezeli a táblázat közös state-jét:

- page
- perPage
- sortField
- sortOrder
- filters
- first
- totalRecords

Fontos:

- `buildFetchParams()` készíti elő a backend paramétereket
- `applyMeta()` tölti vissza a backend pagination meta adatokat
- `setPageFromEvent()` és `setSortFromEvent()` a DataTable eseményekből frissíti a state-et

---

### 5.4. `filters`

A példában ezt használjuk:

```js
initialFilters: {
    search: "",
    status: "",
}
```

Ez azt jelenti, hogy az oldalnak két saját szűrője van:

- `search`
- `status`

Fontos:
nem kell mindig ugyanilyen.
Annyi filter mezőt teszel bele, amennyire annak az oldalnak szüksége van.

Példák:

- `search`
- `status`
- `role`
- `company_id`
- `is_active`

---

### 5.5. `useAdminTableSelection()`

```js
const {
    selectedRows,
    selectedIds,
    selectedCount,
    selectableCount,
    clearSelection,
    setSelectedRows,
} = useAdminTableSelection(...)
```

Ez akkor kell, ha van bulk művelet.

Mit ad?

- `selectedRows` = a kijelölt teljes rekordok
- `selectedIds` = a kijelölt rekordok ID-i
- `selectedCount` = hány kijelölt elem van
- `selectableCount` = az aktuális oldalon mennyi választható
- `clearSelection()` = kijelölés törlése

---

### 5.6. `isRowSelectable`

```js
isRowSelectable: (row) => row?.canDelete !== false
```

Ez UX szintű védelem.

Példa:
ha bizonyos sorokat nem lehet törölni, akkor azokat már a felületen is nem kijelölhetővé teheted.

Ez nem helyettesíti a backend validációt.
A backendnek továbbra is ellenőriznie kell a jogosultságot.

---

### 5.7. `fetchUsers()` és `deleteUsersBulk()`

A példában külön függvények vannak az API hívásokra.

Ez jó minta, mert:

- a `loadRows()` olvashatóbb marad
- az API hívás külön tesztelhető
- később service-be is könnyű áttenni

A valós projektben sokszor nem `fetch`, hanem saját API helper vagy service réteg lesz.
A lényeg ugyanaz: a listaoldalnak kell tudnia adatot kérni.

---

### 5.8. `getRequestParams()`

```js
function getRequestParams() {
    return buildFetchParams({
        filters: {
            search: filters.search || undefined,
            status: filters.status || undefined,
        },
    });
}
```

Ez jó minta, mert a backend kéréshez szükséges paraméterek képzése egy helyen van.

Haszna:

- könnyebb olvasni
- könnyebb bővíteni
- kevesebb a hiba

---

### 5.9. `loadRows()`

Ez a listaoldal szíve.

Feladata:

1. loading bekapcsolása
2. korábbi hiba törlése
3. backend hívás
4. `rows` frissítése
5. `applyMeta()` meghívása
6. kijelölés törlése, ha kell
7. hiba kezelése
8. loading kikapcsolása

Minimum elvárás:

- legyen `try/finally`
- legyen hibaállapot
- legyen meta frissítés
- ne maradjon bent beragadt loading

---

### 5.10. Miért hívjuk az `applyMeta()`-t?

```js
applyMeta(response?.meta?.pagination ?? {});
```

Ez kötelező, ha a backend pagination meta adatot küld.

Enélkül a táblázat nem fogja tudni:

- hány oldal van
- melyik oldalon járunk
- mennyi az összes rekord
- mi legyen a `first`

---

### 5.11. `onPage()`

```js
function onPage(event) {
    setPageFromEvent(event);
    clearSelection();
    loadRows();
}
```

Lapozáskor ez történik:

1. a composable frissíti az oldalszámot
2. a kijelölés törlődik
3. újratöltjük az adatot

---

### 5.12. `onSort()`

```js
function onSort(event) {
    setSortFromEvent(event, "name");
    clearSelection();
    loadRows();
}
```

Ugyanaz a logika, mint lapozásnál:

- új sort state
- selection reset
- újratöltés

---

### 5.13. `onSearchUpdate()` és `onStatusChange()`

Ezek az oldal-specifikus filter kezelők.

A minta:

1. módosítod a filter state-et
2. `resetPagination()`
3. `clearSelection()`
4. újratöltés

---

### 5.14. `refresh()`

Ez egy kényelmi művelet.

A toolbar gyakran tartalmaz egy refresh gombot.

```js
async function refresh() {
    clearSelection();
    await loadRows();
}
```

---

### 5.15. `handleBulkDelete()`

Ez egy valós bulk flow.

Mit csinál?

1. ellenőrzi, van-e kijelölés
2. confirmot kér
3. elindítja a bulk műveletet
4. siker esetén:
   - selection reset
   - reload
   - success visszajelzés
5. hiba esetén:
   - hibaüzenet

A példában `window.confirm` és `window.alert` van, mert így könnyen érthető.
A valódi projektben ez gyakran:

- `ConfirmDialog`
- `Toast`
- shared action helper

lesz.

---

### 5.16. `AdminTableToolbar`

A toolbar kezeli:

- címet
- leírást
- keresőt
- filter zónát
- primary akciót
- bulk zónát
- refresh műveletet

Production oldalon szinte mindig lesz benne legalább:

- kereső
- refresh
- create gomb
- esetleg filterek

---

### 5.17. A DataTable kötelező részei

A production mintában ez a minimum:

```vue
<BaseDataTable
    :value="rows"
    :loading="loading"
    :rows="tableState.perPage"
    :total-records="tableState.totalRecords"
    :first="first"
    data-key="id"
    lazy
    @page="onPage"
    @sort="onSort"
>
```

Mit csinál itt minden?

- `value` = a sorok
- `loading` = töltési állapot
- `rows` = lapméret
- `total-records` = összes backend rekord
- `first` = paginator offset
- `data-key` = stabil sorazonosító
- `lazy` = szerveroldali működés
- `@page` = lapozás kezelése
- `@sort` = rendezés kezelése

---

### 5.18. A selection oszlop

```vue
<Column selectionMode="multiple" headerStyle="width: 3rem" />
```

Ez jeleníti meg a checkbox oszlopot.

Fontos:
önmagában ez még nem elég.
A selection működéséhez kell a `useAdminTableSelection()` is.

---

### 5.19. `RowActionMenu`

```vue
<RowActionMenu :items="getRowActions(data)" />
```

Ez a soronkénti műveleti menü.

Jellemzően ilyen műveletek vannak benne:

- Edit
- Delete
- Show
- Revoke
- Reset password

A fontos szabály:

- shared `RowActionMenu`
- ne saját overlayt írj
- a pozicionálást bízd a szabványos komponensre

---

### 5.20. `loadError` megjelenítése

```vue
<div v-if="loadError">...</div>
```

Ha a lista nem tölt be, a felhasználónak látnia kell, hogy hiba történt.

Ne maradjon néma a felület.

---

### 5.21. `AdminTableSummary`

Ez a footer összegzés.

Például:

- melyik oldalon vagy
- hány elem van
- hány oldal van

Ez azért jó, mert nem kell minden oldalon kézzel újraszámolni ugyanazt a summary logikát.

---

## 6. Minimum kötelező elemek

Ha csak a minimum production-ready verzió kell, akkor legalább ezek legyenek meg:

- `rows`
- `loading`
- `loadError`
- `useAdminTableState()`
- `loadRows()`
- `applyMeta()`
- `BaseDataTable`
- `AdminTableToolbar`
- `AdminTableSummary`

Ha van bulk:

- `useAdminTableSelection()`
- checkbox oszlop
- bulk handler
- selection reset
- success és error visszajelzés

---

## 7. Search minta részletesen

A kereső jellemzően így működik:

```js
function onSearchUpdate(value) {
    filters.search = value;
    resetPagination();
    clearSelection();
    loadRows();
}
```

Ha a keresést debounce-olni akarod, ezt a függvényt debounced változatban hívd.

De a logika ugyanaz marad.

---

## 8. Filter minta részletesen

A filter működése hasonló a kereséshez.

```js
function onStatusChange(value) {
    filters.status = value;
    resetPagination();
    clearSelection();
    loadRows();
}
```

A szabály ugyanaz:

- filter változik
- pagination reset
- selection reset
- új backend kérés

---

## 9. Bulk minta részletesen

Egy jó bulk flow sorrendje:

1. user kijelöl sorokat
2. toolbar mutatja a selected countot
3. bulk action csak akkor engedélyezett, ha van kijelölés
4. confirm dialog
5. API hívás
6. success toast
7. reload
8. selection reset

---

## 10. Hiba- és állapotkezelés

Production listaoldalnál legalább három állapotot kezelned kell:

### 10.1. Betöltés

- `loading = true`
- a tábla loading állapota látszik

### 10.2. Üres lista

- nincs adat
- de nem hiba történt
- `empty` state jelenik meg

### 10.3. Hiba

- backend kérés hibás
- külön hibablokk vagy toast jelenik meg

Ez a három nem ugyanaz.

---

## 11. Mi honnan jön?

- `rows` → backend válasz `data.items`
- `totalRecords` → backend `meta.pagination.total`
- `first` → `useAdminTableState()`
- `rows per page` → `useAdminTableState().state.perPage`
- `search` → `filters.search`
- `status` → `filters.status`
- `selectedIds` → `useAdminTableSelection()`

Ha ezt átlátod, már össze tudsz rakni egy valódi listaoldalt.

---

## 12. Leggyakoribb hibák

### 12.1. Kimarad az `applyMeta()`

Eredmény:

- paginator rossz
- oldalváltás furcsa
- total rossz

### 12.2. Nincs `lazy`

Eredmény:

- a DataTable saját logikája összekeveredik a szerveroldali működéssel

### 12.3. Nincs `data-key`

Eredmény:

- selection instabil lehet
- sorfrissítés problémás lehet

### 12.4. Search után nincs `resetPagination()`

Eredmény:

- felhasználó bent marad egy későbbi oldalon
- azt hiszi, nincs találat

### 12.5. Bulk után nincs `clearSelection()`

Eredmény:

- szellemkijelölés marad
- UX zavaró lesz

### 12.6. Hiba esetén nincs `loadError`

Eredmény:

- a felhasználó csak egy üres vagy furcsa állapotot lát

---

## 13. Egyszerű ellenőrzőlista fejlesztés közben

- [ ] van `rows`
- [ ] van `loading`
- [ ] van `loadError`
- [ ] van `useAdminTableState()`
- [ ] van `loadRows()`
- [ ] van `applyMeta()`
- [ ] van `BaseDataTable`
- [ ] van `AdminTableToolbar`
- [ ] van `AdminTableSummary`
- [ ] page esemény kezelt
- [ ] sort esemény kezelt
- [ ] search reseteli a paginationt
- [ ] filter reseteli a paginationt
- [ ] bulk után reset van
- [ ] hiba esetén látszik valami a felhasználónak

---

## 14. Mit cserélj le valódi projektben?

A példában a legegyszerűbb megoldások szerepelnek, hogy könnyen érthető legyen.

Valódi projektben ezeket általában lecseréled:

- `fetch()` → saját API service
- `window.alert()` → Toast
- `window.confirm()` → ConfirmDialog
- egyszerű hibablokk → egységes error UX
- inline action függvények → service vagy composable hívások

De a szerkezet ettől még ugyanaz marad.

---

## 15. Rövid végső szabály

Ha csak egyetlen dolgot jegyzel meg, ez legyen az:

A `BaseDataTable` önmagában nem listaoldal.

Egy működő, production ready admin listaoldalhoz mindig együtt kell:

- state
- fetch
- events
- toolbar
- summary
- hibakezelés
- és ha kell, selection

---

## 16. Rövid zárás

Ha ezt a mintát követed, akkor a listaoldalad:

- egységes lesz
- kevésbé lesz hibás
- könnyebben karbantartható lesz
- könnyebben refaktorálható lesz
- jobban illeszkedik a projekt admin list standardjához

Röviden:
nem csak működni fog, hanem normálisan fog működni.

