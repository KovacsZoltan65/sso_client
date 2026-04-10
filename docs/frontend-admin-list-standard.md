# Frontend Admin List Standard

## 1. Bevezető

Az admin listaoldal design system célja, hogy minden admin táblázatos oldal egységes, PrimeVue-alapú szerződésre épüljön.

A standard az alábbi problémákat oldja meg:

- paginator boilerplate duplikáció
- loading és empty state inkonzisztencia
- toolbar újraépítése oldalanként
- summary logika ismétlése
- selection és bulk UX szétcsúszása

Ez a standard:

- NEM framework
- NEM univerzális grid motor
- NEM config-driven generátor

A cél: egyszerű, stabil, PrimeVue-kompatibilis működési szerződés

---

## 2. Kötelező szabályok (nem megszeghető)

- Minden új admin listaoldal KÖTELEZŐEN ezt a standardot követi
- Legacy oldalak refaktorakor ezt a standardot kell alkalmazni
- Eltérés csak dokumentált, indokolt esetben engedélyezett
- Code review során a checklist alapján történik az ellenőrzés

---

## 3. Kötelező építőelemek

Minden admin listaoldal:

KÖTELEZŐ:

- BaseDataTable
- useAdminTableState()
- AdminTableToolbar
- AdminTableSummary

HA VAN BULK:

- useAdminTableSelection()

---

## 4. Canonical listaoldal minta

Vue példa:

<script setup>
import BaseDataTable from "@/Components/Admin/BaseDataTable.vue";
import AdminTableToolbar from "@/Components/Admin/AdminTableToolbar.vue";
import AdminTableSummary from "@/Components/Admin/AdminTableSummary.vue";
import { useAdminTableState } from "@/Composables/useAdminTableState";
import { useAdminTableSelection } from "@/Composables/useAdminTableSelection";

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
    initialSortField: "created_at",
    initialSortOrder: -1,
    initialFilters: {
        search: "",
    },
});

const {
    selectedIds,
    selectedCount,
    selectableCount,
} = useAdminTableSelection(computed(() => rows.value));

async function loadRows() {
    const envelope = await fetchRows(
        buildFetchParams({
            filters: {
                search: filters.search || undefined,
            },
        }),
    );

    rows.value = envelope.data.items ?? [];
    applyMeta(envelope.meta.pagination ?? {});
}
</script>

<template>
    <AdminTableToolbar />
    <BaseDataTable>
        <!-- columns -->
    </BaseDataTable>
    <AdminTableSummary />
</template>

---

## 5. useAdminTableState contract

Felelős:

- pagination
- sorting
- filtering

KÖTELEZŐ:

- fetch paraméterek buildFetchParams()-on keresztül
- backend meta → applyMeta()

TILOS:

- modal state
- action menu state
- bulk confirm flow
- API hibakezelés

---

## 6. Selection + bulk contract

A selection:

- page-local
- nem multi-page

Derived state:

- selectedIds
- selectedCount
- selectableCount

KÖTELEZŐ:

- page change → reset
- filter change → reset
- sort change → reset
- refresh → reset
- success bulk → reset

TILOS:

- saját selection state oldalanként

---

## 7. Bulk UX szabályok

KÖTELEZŐ:

- disabled state
- selected count kijelzés
- confirm dialog
- success → toast + refresh + reset

TILOS:

- backend endpoint nélküli bulk action

---

## 8. Toolbar szabályok

AdminTableToolbar:

- standard search mező
- filter slot
- action slot
- bulk slot

TILOS:

- toolbar újraépítése oldalanként

---

## 9. Summary szabály

AdminTableSummary:

- Showing X–Y of Z
- fallback meta hiány esetén

TILOS:

- saját summary számolás

---

## 10. Row actions (kötelező minta)

KÖTELEZŐ:

- shared RowActionMenu komponens
- PrimeVue Menu (popup)
- appendTo="body"
- menu.toggle(event)

Példa:

<Menu ref="menu" :model="items" popup appendTo="body" />
<Button icon="pi pi-ellipsis-v" @click="menu.toggle($event)" />

TILOS:

- saját overlay pozicionálás
- position: fixed alapú számolás
- oldalankénti popup implementáció

---

## 11. Mikor NEM használjuk

- audit log
- read-only listák
- bulk nélküli domain

---

## 12. Anti-pattern lista

Automatikus code review elutasítás:

- saját DataTable wrapper
- saját pagination state
- saját selection ref
- toolbar újraépítés
- bulk backend nélkül
- saját overlay popup

---

## 13. Definition of Done

Egy admin listaoldal csak akkor kész, ha:

- [ ] BaseDataTable használva
- [ ] useAdminTableState használva
- [ ] toolbar standard
- [ ] summary standard
- [ ] selection contract (ha kell)
- [ ] nincs duplikált state
- [ ] nincs custom paginator

---

## 14. Migrációs szabály

Legacy refaktor sorrend:

1. BaseDataTable
2. useAdminTableState()
3. AdminTableToolbar
4. AdminTableSummary
5. useAdminTableSelection()

---

## 15. Bevezetési stratégia

- új fejlesztés → kötelező standard
- legacy → fokozatos migráció

Prioritás:

1. bulk oldalak
2. gyakran használt listák
3. read-only listák

---

## 16. Kivételek kezelése

Eltérés csak akkor engedélyezett, ha:

- dokumentált
- indokolt
- nem hoz létre duplikált rendszert
