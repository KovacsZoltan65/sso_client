
# BaseDataTable – Gyors indulás (Junior Proof)

## 🎯 Cél

Ebből a dokumentumból **önállóan meg tudsz csinálni egy működő admin táblázatot**.

Ha ezt végigcsinálod, lesz:
- működő lista
- működő lapozás
- működő rendezés
- backend adatbetöltés

---

## 🧠 Mit építünk?

Egy tipikus admin listaoldalt:

- backendből jön az adat
- van lapozás
- van rendezés
- van keresés (alap)

---

## ⚡ 1. Lépés – State létrehozása

```js
import { ref } from "vue";

const rows = ref([]);
const loading = ref(false);
```

---

## ⚡ 2. Lépés – Table state (KÖTELEZŐ)

```js
import { useAdminTableState } from "@/Composables/useAdminTableState";

const {
    state: tableState,
    filters,
    first,
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
```

---

## ⚡ 3. Lépés – Backend hívás

```js
async function loadRows() {
    loading.value = true;

    try {
        const response = await fetch("/api/your-endpoint", {
            method: "GET"
        });

        const data = await response.json();

        rows.value = data.data.items;
        applyMeta(data.meta.pagination);

    } finally {
        loading.value = false;
    }
}
```

---

## ⚡ 4. Lépés – Event kezelők

```js
function onPage(event) {
    setPageFromEvent(event);
    loadRows();
}

function onSort(event) {
    setSortFromEvent(event, "created_at");
    loadRows();
}
```

---

## ⚡ 5. Lépés – Template

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
    <Column field="name" header="Name" sortable />
</BaseDataTable>
```

---

## ⚡ 6. Lépés – Első betöltés

```js
onMounted(() => {
    loadRows();
});
```

---

## 🧪 Ellenőrzés

Ha minden jól működik:

- megjelenik a lista
- lapozás működik
- rendezés működik
- nem dob hibát

---

## 🚨 Gyakori hibák

❌ Nem hívod meg az applyMeta()-t  
👉 nincs lapozás  

❌ Nem használod a first-et  
👉 paginator elcsúszik  

❌ Nem hívod újra loadRows()-t  
👉 UI nem frissül  

❌ Kihagyod a lazy-t  
👉 furcsa viselkedés  

---

## 🧠 Rövid szabály

👉 BaseDataTable = UI  
👉 useAdminTableState = state  
👉 loadRows = adat  

---

## 🎉 Kész vagy

Ha idáig eljutottál:

👉 már tudsz admin listát építeni
