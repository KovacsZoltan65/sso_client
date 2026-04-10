
# BaseDataTable használati útmutató

## Mi ez pontosan?

A `BaseDataTable` egy közös Vue komponens, ami a PrimeVue `DataTable` fölé épül.
A szerepe az, hogy az admin listaoldalakon ne kelljen mindig ugyanazt a táblázat-vázat újra megírni.

Magyarul:
ez a komponens ad egy kész, egységes alapot a listákhoz.

Amit ad neked:

- egységes wrapper
- egységes paginator alap
- egységes loading állapot
- egységes empty state
- egységes scroll viselkedés
- közös, projekt-szintű használati minta

Amit nem csinál meg helyetted:

- API hívás
- business logic
- modal kezelés
- űrlapkezelés
- bulk action logika
- jogosultsági döntések

---

## Hol találod?

A jelenlegi projektben jellemzően itt:

`resources/js/Components/Admin/BaseDataTable.vue`

---

## Mikor kell használni?

Admin listaoldalakon gyakorlatilag mindig.

Példák:

- Users
- Companies
- Employees
- Roles
- Permissions
- Scopes
- Clients
- Token Policies

Ha az oldal alapvetően egy táblázatos lista, akkor a kiindulópont a `BaseDataTable`.

---

## Alap példa

```vue
<script setup>
import { ref } from "vue";
import Column from "primevue/column";
import BaseDataTable from "@/Components/Admin/BaseDataTable.vue";

const rows = ref([
    { id: 1, name: "Acme Ltd." },
    { id: 2, name: "Example Corp." },
]);

const loading = ref(false);

function onPage(event) {
    console.log("Page event:", event);
}

function onSort(event) {
    console.log("Sort event:", event);
}
</script>

<template>
    <BaseDataTable
        :value="rows"
        :loading="loading"
        :rows="10"
        :total-records="2"
        :first="0"
        @page="onPage"
        @sort="onSort"
    >
        <Column field="name" header="Name" sortable />
    </BaseDataTable>
</template>
```

---

## Hogyan épül fel?

A komponens belül:

- egy külső wrappert ad
- opcionálisan kirak egy `filters` slotot a tábla fölé
- renderel egy PrimeVue `DataTable` komponenst
- ráteszi a közös alapértelmezéseket
- a fontos eseményeket továbbadja
- ad egy közös `loading` és `empty` megjelenítést

Fontos: a komponens `inheritAttrs: false` beállítással működik.
Ez azt jelenti, hogy a nem deklarált attribútumokat nem a wrapper `div` kapja meg, hanem a belső PrimeVue `DataTable`.

Ez nagyon hasznos, mert így a PrimeVue-specifikus opciókat továbbra is át tudod adni.

---

## A props-ok részletesen

Az alábbi lista a jelenlegi komponens tényleges propjaira épül.

### 1. `value`

**Típus:** `Array`  
**Alapértelmezés:** `[]`

Ez maga a táblázat sorainak tömbje.

Egyszerűen:
ezt jeleníti meg a tábla.

Példa:

```vue
<BaseDataTable :value="rows" />
```

Általában backendről töltöd fel:

```js
rows.value = response.data.items ?? [];
```

---

### 2. `filters`

**Típus:** `Object`  
**Alapértelmezés:** `{}`

A PrimeVue DataTable filter objektuma.

Ezt akkor használod, ha a táblához filter állapot is tartozik, például:

- globális keresés
- oszlop szintű szűrés
- menüs filterek

Példa:

```js
const filters = ref({
    global: { value: "", matchMode: "contains" },
});
```

```vue
<BaseDataTable v-model:filters="filters" :filters="filters" />
```

Megjegyzés:
a komponens támogatja az `update:filters` eseményt is, vagyis kétirányú mintában is jól használható.

---

### 3. `loading`

**Típus:** `Boolean`  
**Alapértelmezés:** `false`

Azt jelzi, hogy a tábla töltési állapotban van-e.

Ha `true`, akkor a PrimeVue loading állapot aktív lesz, és a komponens a `loading` slotot vagy az alap loading nézetet mutatja.

Példa:

```vue
<BaseDataTable :loading="loading" />
```

Tipikus használat:

```js
loading.value = true;

try {
    await loadRows();
} finally {
    loading.value = false;
}
```

---

### 4. `loadingMessage`

**Típus:** `String`  
**Alapértelmezés:** `"Betoltes folyamatban..."`

Ez az alapértelmezett szöveg jelenik meg a loading nézetben.

Ha nem akarsz külön `#loading` slotot írni, de a szöveget testre szabnád, ez a legegyszerűbb mód.

Példa:

```vue
<BaseDataTable
    :loading="loading"
    loading-message="Felhasználók betöltése..."
/>
```

---

### 5. `rows`

**Típus:** `Number`  
**Alapértelmezés:** `10`

Oldalanként hány sort mutasson a táblázat.

Ez a paginator egyik alapértéke.

Példa:

```vue
<BaseDataTable :rows="25" />
```

A gyakorlatban ezt gyakran a `useAdminTableState()` adja:

```vue
<BaseDataTable :rows="tableState.perPage" />
```

---

### 6. `totalRecords`

**Típus:** `Number`  
**Alapértelmezés:** `0`

Az összes rekord száma, nem csak az aktuális oldalon látható elemek száma.

Ez főleg szerver oldali lapozásnál fontos.
A paginator ebből tudja, hogy összesen hány oldal van.

Példa:

```vue
<BaseDataTable :total-records="tableState.totalRecords" />
```

A komponens ezt belül normalizálja is:
ha hibás vagy negatív szám érkezik, biztonságosan `0`-ra esik vissza.

---

### 7. `first`

**Típus:** `Number`  
**Alapértelmezés:** `0`

Az aktuális első rekord indexe a paginator logikában.

Például:

- első oldal → `0`
- második oldal, 10-es lapméretnél → `10`
- harmadik oldal, 10-es lapméretnél → `20`

Példa:

```vue
<BaseDataTable :first="first" />
```

Ez tipikusan a `useAdminTableState()` egyik derived értéke.

A komponens ezt is biztonságosan normalizálja:
ha rossz szám jön, akkor `0` lesz.

---

### 8. `lazy`

**Típus:** `Boolean`  
**Alapértelmezés:** `true`

Azt jelzi, hogy a lapozás, rendezés és szűrés szerver oldalon történik-e.

Admin oldalaknál ez általában `true`, mert a backendből kérjük le az adatokat.

Példa:

```vue
<BaseDataTable lazy />
```

Ha valamiért teljesen kliens oldali listád van, ezt akár `false`-ra is teheted, de az admin list standard jellemzően lazy működéssel számol.

---

### 9. `paginator`

**Típus:** `Boolean`  
**Alapértelmezés:** `true`

Megmondja, hogy egyáltalán jelenjen-e meg lapozó.

Példa:

```vue
<BaseDataTable :paginator="false" />
```

Ez ritkább, de hasznos lehet rövid, fix listáknál.

---

### 10. `alwaysShowPaginator`

**Típus:** `Boolean`  
**Alapértelmezés:** `true`

A paginator akkor is látszódjon-e, ha kevés rekord van.

Ha `true`, a lapozó nem tűnik el attól, hogy most éppen csak kevés sor van.
Ez vizuálisan stabilabb admin UI-t ad.

Példa:

```vue
<BaseDataTable :always-show-paginator="true" />
```

---

### 11. `rowsPerPageOptions`

**Típus:** `Array`  
**Alapértelmezés:** `[10, 25, 50]`

Az oldalanként választható elemszámok.

Példa:

```vue
<BaseDataTable :rows-per-page-options="[10, 20, 50, 100]" />
```

A komponens ezt is védi:
ha üres tömböt kap, akkor visszaesik az aktuális `rows` értékre.

---

### 12. `paginatorTemplate`

**Típus:** `String`  
**Alapértelmezés:**  
`"RowsPerPageDropdown FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink"`

A PrimeVue paginator felépítését szabályozza.

Ez megmondja, hogy milyen elemek jelenjenek meg a lapozóban és milyen sorrendben.

Példa:

```vue
<BaseDataTable
    paginator-template="PrevPageLink CurrentPageReport NextPageLink"
/>
```

Ez advanced beállítás, a legtöbb oldalon az alapértelmezett jó.

---

### 13. `currentPageReportTemplate`

**Típus:** `String`  
**Alapértelmezés:** `"{first} - {last} / {totalRecords}"`

A paginator középső szöveges része.

Példa:

```vue
<BaseDataTable
    current-page-report-template="{first}–{last} / {totalRecords}"
/>
```

Ha egységesebb magyar szöveget akarsz, átírhatod, de általában ez marad.

---

### 14. `dataKey`

**Típus:** `String`  
**Alapértelmezés:** `"id"`

Megmondja, hogy melyik mező azonosítja egyértelműen a sorokat.

Ez nagyon fontos:

- selectionnél
- sorfrissítésnél
- stabil renderelésnél

Példa:

```vue
<BaseDataTable data-key="uuid" />
```

Ha a rekordjaid nem `id` mezőt használnak, ezt át kell állítani.

---

### 15. `scrollable`

**Típus:** `Boolean`  
**Alapértelmezés:** `true`

Azt mondja meg, hogy a tábla scrollolható legyen-e.

Admin listáknál ez általában jó alapbeállítás, főleg sok oszlopnál.

Példa:

```vue
<BaseDataTable :scrollable="true" />
```

---

### 16. `scrollHeight`

**Típus:** `String`  
**Alapértelmezés:** `"flex"`

A scrollolható tábla magassági viselkedését szabályozza.

A `"flex"` érték azt jelenti, hogy a tábla a rendelkezésre álló helyhez próbál igazodni.

Példa:

```vue
<BaseDataTable scroll-height="400px" />
```

Ez akkor hasznos, ha fix magasságú táblát akarsz.

---

### 17. `stripedRows`

**Típus:** `Boolean`  
**Alapértelmezés:** `true`

Csíkozott sorháttér legyen-e.
Ez olvashatóbbá teszi a hosszabb listákat.

Példa:

```vue
<BaseDataTable :striped-rows="false" />
```

---

### 18. `size`

**Típus:** `String`  
**Alapértelmezés:** `null`

A PrimeVue táblaméretet szabályozza.

Tipikus értékek lehetnek például:

- `"small"`
- `"large"`

Példa:

```vue
<BaseDataTable size="small" />
```

Ha nincs megadva, az alap méret marad.

---

### 19. `emptyMessage`

**Típus:** `String`  
**Alapértelmezés:** `"Nincs megjelenitheto adat."`

Ez a szöveg jelenik meg, ha nincs találat, és nem adsz külön `#empty` slotot.

Példa:

```vue
<BaseDataTable empty-message="Nincs felhasználó." />
```

Ez egyszerűbb, mint külön empty slotot írni, ha csak a szöveget akarod cserélni.

---

### 20. `rowClass`

**Típus:** `Function | String | Array | Object`  
**Alapértelmezés:** `null`

Ezzel tudsz egyedi CSS osztályt adni a soroknak.

Hasznos például akkor, ha:

- tiltott sorokat máshogy színeznél
- inaktív elemeket halványítanál
- státusz alapján emelnél ki sorokat

Példa függvénnyel:

```vue
<BaseDataTable :row-class="getRowClass" />
```

```js
function getRowClass(row) {
    return row.is_active ? "" : "opacity-60";
}
```

---

### 21. `tableClass`

**Típus:** `String | Array | Object`  
**Alapértelmezés:** `""`

Egyedi CSS osztályt adhatsz a belső táblához.

A komponens ehhez mindig hozzáadja az alap:
`admin-datatable h-full`

Példa:

```vue
<BaseDataTable table-class="border-round-xl" />
```

Fontos:
ez nem lecseréli az alap osztályokat, hanem hozzáadódik hozzájuk.

---

### 22. `wrapperClass`

**Típus:** `String | Array | Object`  
**Alapértelmezés:** `""`

Egyedi osztályt adhatsz a külső wrapper elemhez.

A komponens ehhez mindig hozzáadja az alap wrappert:
`base-data-table flex min-h-0 flex-1 flex-col overflow-hidden`

Példa:

```vue
<BaseDataTable wrapper-class="rounded-2xl bg-white" />
```

Ez akkor jó, ha az adott oldalon a külső keretet finoman módosítanád.

---

## Események

A komponens a fontos PrimeVue DataTable eseményeket továbbadja.

### 1. `page`

Lapozáskor fut le.

Példa:

```vue
<BaseDataTable @page="onPage" />
```

```js
function onPage(event) {
    setPageFromEvent(event);
    loadRows();
}
```

---

### 2. `sort`

Rendezéskor fut le.

Példa:

```vue
<BaseDataTable @sort="onSort" />
```

```js
function onSort(event) {
    setSortFromEvent(event, "created_at");
    loadRows();
}
```

---

### 3. `filter`

Szűréskor fut le.

Példa:

```vue
<BaseDataTable @filter="onFilter" />
```

```js
function onFilter() {
    resetPagination();
    loadRows();
}
```

---

### 4. `row-click`

Sorra kattintáskor fut le.

Példa:

```vue
<BaseDataTable @row-click="onRowClick" />
```

```js
function onRowClick(event) {
    console.log(event.data);
}
```

---

### 5. `update:filters`

A filter objektum frissítésekor fut le.

Ez különösen akkor hasznos, ha `v-model:filters` mintával akarod használni.

Példa:

```vue
<BaseDataTable
    v-model:filters="filters"
    :filters="filters"
/>
```

---

## Slotok részletesen

A komponens több slotot is ad.

### 1. Alap slot

Ide kerülnek az oszlopok.

Példa:

```vue
<BaseDataTable ...>
    <Column field="name" header="Name" />
    <Column field="email" header="Email" />
</BaseDataTable>
```

---

### 2. `header`

A DataTable header részébe kerül.

Ezt leggyakrabban `AdminTableToolbar` számára használjuk.

Példa:

```vue
<template #header>
    <AdminTableToolbar />
</template>
```

---

### 3. `actions`

Ez a header alatt egy külön blokkban jelenik meg, de még mindig a táblázat fejléc-részében.

A komponens úgy működik, hogy ha van `header` vagy `actions` slot, akkor egy közös header blokkot rajzol ki.

Példa:

```vue
<template #actions>
    <div class="flex justify-end">
        <Button label="Export" />
    </div>
</template>
```

---

### 4. `filters`

Ez a táblázat külső wrapperében, a DataTable fölött jelenik meg.

Ez akkor hasznos, ha a filter UI-nak nem a DataTable headerben kell lennie, hanem kívül.

Példa:

```vue
<template #filters>
    <div class="mb-4">
        <Select v-model="status" :options="statusOptions" />
    </div>
</template>
```

---

### 5. `loading`

Ezzel teljesen felülírhatod az alap loading nézetet.

Alapból a komponens ezt adja:

- spinner ikon
- `loadingMessage` szöveg
- egységes padding és szövegstílus

Ha egyedi loading UI kell, használj slotot.

Példa:

```vue
<template #loading>
    <div class="p-8 text-center">
        Egyedi betöltés...
    </div>
</template>
```

---

### 6. `empty`

Ezzel teljesen felülírhatod az alap empty nézetet.

Alapból a komponens csak kiírja az `emptyMessage` szöveget.

Ha gazdagabb üres állapot kell, itt tudod megadni.

Példa:

```vue
<template #empty>
    <div class="py-10 text-center">
        <i class="pi pi-inbox mb-3 text-2xl" />
        <div>Nincs találat.</div>
    </div>
</template>
```

---

## Milyen attribútumokat adhatsz még át?

Mivel a komponens `v-bind="attrs"` használattal továbbadja a nem deklarált attribútumokat a belső PrimeVue `DataTable`-nek, ezért sok plusz opciót közvetlenül átadhatsz.

Példák:

```vue
<BaseDataTable
    removableSort
    filterDisplay="menu"
    selectionMode="multiple"
/>
```

Ez nagyon kényelmes, mert a `BaseDataTable` nem akarja teljesen lecserélni a PrimeVue API-ját.

---

## Tipikus együttműködés más komponensekkel

A `BaseDataTable` általában nem önmagában él, hanem együtt használjuk ezekkel:

- `useAdminTableState()`
- `AdminTableToolbar`
- `AdminTableSummary`
- `useAdminTableSelection()` ha van bulk
- `RowActionMenu` ha soronként műveleti menü van

Röviden:

- `BaseDataTable` = táblázat UI shell
- `useAdminTableState()` = állapotkezelés
- `AdminTableToolbar` = kereső / akciók / bulk zóna
- `AdminTableSummary` = footer összegzés

---

## Teljesebb, valósághű példa

```vue
<script setup>
import { ref } from "vue";
import Column from "primevue/column";
import BaseDataTable from "@/Components/Admin/BaseDataTable.vue";
import AdminTableToolbar from "@/Components/Admin/AdminTableToolbar.vue";
import AdminTableSummary from "@/Components/Admin/AdminTableSummary.vue";
import { useAdminTableState } from "@/Composables/useAdminTableState";

const rows = ref([]);
const loading = ref(false);

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
    },
});

async function loadRows() {
    loading.value = true;

    try {
        const response = await fetchCompanies(
            buildFetchParams({
                filters: {
                    search: filters.search || undefined,
                },
            }),
        );

        rows.value = response.data.items ?? [];
        applyMeta(response.meta.pagination ?? {});
    } finally {
        loading.value = false;
    }
}

function onPage(event) {
    setPageFromEvent(event);
    loadRows();
}

function onSort(event) {
    setSortFromEvent(event, "name");
    loadRows();
}
</script>

<template>
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
        <template #header>
            <AdminTableToolbar
                searchable
                :search-value="filters.search"
                @update:searchValue="filters.search = $event"
            />
        </template>

        <Column field="name" header="Name" sortable />
        <Column field="email" header="Email" sortable />

        <template #empty>
            <div class="py-10 text-center text-slate-500">
                Nincs megjeleníthető rekord.
            </div>
        </template>
    </BaseDataTable>

    <AdminTableSummary
        :page="tableState.page"
        :per-page="tableState.perPage"
        :total="tableState.totalRecords"
        :last-page="lastPage"
        item-label="records"
    />
</template>
```

---

## Tipikus hibák

### 1. Közvetlenül PrimeVue `DataTable` használata `BaseDataTable` helyett

Ez könnyen visszahozza a régi káoszt:

- eltérő paginator
- eltérő loading
- eltérő empty state
- eltérő wrapper viselkedés

Ha admin listáról van szó, ne kerüld meg a `BaseDataTable`-t.

---

### 2. Saját paginator logika

A paginatorhoz kapcsolódó state-et ne oldalanként találd ki.
Arra ott a `useAdminTableState()`.

---

### 3. Saját loading blokk minden oldalon

Ha csak a szöveget akarod módosítani, elég a `loadingMessage`.
Csak akkor írj `#loading` slotot, ha tényleg teljesen más nézet kell.

---

### 4. Saját empty állapot számolás

Ha csak egy egyszerű szöveg kell, használd az `emptyMessage` propot.
Nem kell minden oldalon külön `if` logikát írni.

---

### 5. Rossz `dataKey`

Ha a rekordok azonosítója nem `id`, de nem állítod át a `dataKey`-t, abból később selection és frissítési problémák lehetnek.

---

## Best practice röviden

A `BaseDataTable`-t így érdemes elképzelni:

- ne akarj benne business logicot tartani
- ne akarj benne API hívást tartani
- ne próbáld meg okos admin frameworkké növeszteni
- használd közös UI shellként
- add át neki a sorokat és a state-et
- az oszlopokat és az oldalspecifikus működést az oldalon tartsd

---

## Rövid összefoglalás

A `BaseDataTable` azért fontos, mert egységessé teszi az admin listaoldalakat.

Nem az a dolga, hogy mindent megoldjon.
Az a dolga, hogy ugyanazt az alapot adja minden listának.

Ha jól használod, akkor:

- kevesebb lesz a boilerplate
- kevesebb lesz a UI eltérés
- kevesebb lesz a paginator/loading/empty bug
- könnyebb lesz új listaoldalt építeni
- könnyebb lesz a régi oldalak refaktorálása
