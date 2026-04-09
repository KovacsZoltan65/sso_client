<script setup>
import EmptyStatePanel from "@/Components/EmptyStatePanel.vue";
import AdminTableCard from "@/Components/Admin/AdminTableCard.vue";
import AdminTableToolbar from "@/Components/Admin/AdminTableToolbar.vue";
import PageHeader from "@/Components/PageHeader.vue";
import RowActionMenu from "@/Components/Admin/RowActionMenu.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import {
    PermissionApiError,
    createPermission,
    deletePermission,
    listPermissions,
    updatePermission,
} from "@/Services/permissionService";
import { Head } from "@inertiajs/vue3";
import Button from "primevue/button";
import Column from "primevue/column";
import ConfirmDialog from "primevue/confirmdialog";
import DataTable from "primevue/datatable";
import InputText from "primevue/inputtext";
import Tag from "primevue/tag";
import { useConfirm } from "primevue/useconfirm";
import { useToast } from "primevue/usetoast";
import { computed, onMounted, reactive, ref, watch } from "vue";
import { IconField, InputIcon } from "primevue";

import CreatePermissionDialog from "./Partials/CreatePermissionDialog.vue";
import EditPermissionDialog from "./Partials/EditPermissionDialog.vue";

const props = defineProps({
    permissionsApi: { type: Object, required: true },
    permissions: { type: Object, required: true },
});

const toast = useToast();
const confirm = useConfirm();

const items = ref([]);
const loading = ref(false);
const showCreateDialog = ref(false);
const showEditDialog = ref(false);
const editingPermission = ref(null);
const submitting = ref(false);

const filters = reactive({ search: "" });
const tableState = reactive({ page: 1, perPage: 10, total: 0, sortField: "created_at", sortOrder: "desc" });
const form = reactive(defaultForm());
const formErrors = reactive({});
const firstRecordIndex = computed(() => (tableState.page - 1) * tableState.perPage);
let searchDebounceId = null;

function defaultForm() {
    return { name: "", guard_name: "web" };
}

function resetForm(permission = null) {
    Object.assign(form, permission ? { name: permission.name ?? "", guard_name: permission.guard_name ?? "web" } : defaultForm());
    clearFormErrors();
}

function clearFormErrors() {
    Object.keys(formErrors).forEach((key) => delete formErrors[key]);
}

function getRequestParams() {
    return { page: tableState.page, per_page: tableState.perPage, sort_field: tableState.sortField, sort_order: tableState.sortOrder, search: filters.search || undefined };
}

async function loadPermissions() {
    loading.value = true;
    try {
        const envelope = await listPermissions(props.permissionsApi, getRequestParams());
        items.value = envelope.data.items ?? [];
        const pagination = envelope.meta.pagination ?? {};
        tableState.total = pagination.total ?? 0;
        tableState.page = pagination.current_page ?? tableState.page;
        tableState.perPage = pagination.per_page ?? tableState.perPage;
    } catch (error) {
        handleApiError(error, "A permission lista betoltese sikertelen volt.");
    } finally {
        loading.value = false;
    }
}

function openCreateDialog() { resetForm(); showCreateDialog.value = true; }
function openEditDialog(permission) { editingPermission.value = permission; resetForm(permission); showEditDialog.value = true; }
function closeCreateDialog() { showCreateDialog.value = false; resetForm(); }
function closeEditDialog() { showEditDialog.value = false; editingPermission.value = null; resetForm(); }

async function submitCreate() {
    submitting.value = true;
    clearFormErrors();
    try {
        await createPermission(props.permissionsApi, form);
        toast.add({ severity: "success", summary: "Sikeres muvelet", detail: "A permission letrehozasa sikeres volt.", life: 3000 });
        closeCreateDialog();
        tableState.page = 1;
        await loadPermissions();
    } catch (error) {
        handleMutationError(error, "A permission letrehozasa sikertelen volt.");
    } finally {
        submitting.value = false;
    }
}

async function submitUpdate() {
    if (!editingPermission.value) return;
    submitting.value = true;
    clearFormErrors();
    try {
        await updatePermission(props.permissionsApi, editingPermission.value.id, form);
        toast.add({ severity: "success", summary: "Sikeres muvelet", detail: "A permission frissitese sikeres volt.", life: 3000 });
        closeEditDialog();
        await loadPermissions();
    } catch (error) {
        handleMutationError(error, "A permission modositasa sikertelen volt.");
    } finally {
        submitting.value = false;
    }
}

function confirmDelete(permission) {
    if (permission.is_protected) {
        toast.add({ severity: "error", summary: "Muvelet tiltva", detail: "A vedett rendszer-jogosultsag nem torolheto.", life: 4000 });
        return;
    }

    confirm.require({
        header: "Torles megerositese",
        message: `Biztosan torolni szeretned a(z) ${permission.name} permissiont?`,
        acceptLabel: "Torles",
        rejectLabel: "Megse",
        acceptClass: "p-button-danger",
        accept: async () => {
            try {
                await deletePermission(props.permissionsApi, permission.id);
                toast.add({ severity: "success", summary: "Sikeres muvelet", detail: "A permission torlese sikeres volt.", life: 3000 });
                if (items.value.length === 1 && tableState.page > 1) tableState.page -= 1;
                await loadPermissions();
            } catch (error) {
                handleApiError(error, "A permission torlese sikertelen volt.");
            }
        },
    });
}

function permissionActionItems(permission) {
    return [
        props.permissions.update && permission.can?.update !== false ? { label: "Szerkesztes", icon: "pi pi-pencil", command: () => openEditDialog(permission) } : null,
        props.permissions.delete && permission.can?.delete !== false && !permission.is_protected ? { label: "Torles", icon: "pi pi-trash", command: () => confirmDelete(permission) } : null,
    ];
}

async function refreshPermissions() {
    await loadPermissions();
    toast.add({ severity: "success", summary: "Sikeres muvelet", detail: "A permission lista frissult.", life: 2500 });
}

function handleTablePage(event) { tableState.page = (event.page ?? 0) + 1; tableState.perPage = event.rows ?? tableState.perPage; loadPermissions(); }
function handleTableSort(event) { tableState.sortField = event.sortField ?? "created_at"; tableState.sortOrder = event.sortOrder === 1 ? "asc" : "desc"; tableState.page = 1; loadPermissions(); }

function handleApiError(error, fallbackMessage) {
    if (error instanceof PermissionApiError && error.status === 401) {
        const redirectTarget = error.meta.reauth_to || error.meta.redirect_to || route("login");
        window.location.assign(redirectTarget);
        return;
    }
    toast.add({ severity: "error", summary: "Hiba tortent", detail: error instanceof PermissionApiError ? error.message : fallbackMessage, life: 4000 });
}

function handleMutationError(error, fallbackMessage) {
    if (error instanceof PermissionApiError && error.status === 422) {
        Object.assign(formErrors, error.errors ?? {});
        return;
    }
    handleApiError(error, fallbackMessage);
}

function formatDate(value) {
    if (!value) return "-";
    const date = new Date(value.replace(" ", "T"));
    return Number.isNaN(date.getTime()) ? value : date.toLocaleString("hu-HU");
}

watch(() => filters.search, () => {
    if (searchDebounceId) window.clearTimeout(searchDebounceId);
    searchDebounceId = window.setTimeout(() => { tableState.page = 1; loadPermissions(); }, 350);
});

onMounted(loadPermissions);
</script>

<template>
    <Head title="Permissions" />

    <AuthenticatedLayout>
        <ConfirmDialog />

        <div class="admin-table-page">
            <PageHeader title="Permissions" description="A helyi jogosultsag-katalogus teljes adminisztracioja, lokalis role-hozzarendelesekhez." />

            <AdminTableCard>
                <div class="admin-table-shell">
                    <div class="hidden min-h-0 flex-1 lg:flex">
                        <DataTable
                            :value="items"
                            :loading="loading"
                            class="admin-datatable"
                            scrollable
                            scroll-height="flex"
                            lazy
                            paginator
                            removable-sort
                            data-key="id"
                            :rows="tableState.perPage"
                            :first="firstRecordIndex"
                            :total-records="tableState.total"
                            :sort-field="tableState.sortField"
                            :sort-order="tableState.sortOrder === 'asc' ? 1 : -1"
                            paginator-template="RowsPerPageDropdown FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink"
                            current-page-report-template="{first} - {last} / {totalRecords}"
                            :rows-per-page-options="[10, 25, 50]"
                            @page="handleTablePage"
                            @sort="handleTableSort"
                        >
                            <template #header>
                                <AdminTableToolbar :canCreate="permissions.create" createLabel="Uj permission" :canBulkDelete="false" :selectedCount="0" :selectableCount="0" :busy="loading || submitting" @create="openCreateDialog" @refresh="refreshPermissions">
                                    <template #search>
                                        <IconField class="w-full">
                                            <InputIcon class="pi pi-search text-slate-400" />
                                            <InputText v-model="filters.search" fluid placeholder="Kereses permission nev vagy guard alapjan" class="w-full" />
                                        </IconField>
                                    </template>
                                </AdminTableToolbar>
                            </template>

                            <template #empty>
                                <div class="px-6 py-10">
                                    <EmptyStatePanel title="Nincs megjelenitheto permission" description="A jelenlegi szurok mellett nincs talalat. Modositsd a keresest vagy hozz letre uj permissiont." :tags="['Permissions', 'Local RBAC']" />
                                </div>
                            </template>

                            <Column field="id" header="ID" sortable />
                            <Column field="name" header="Permission" sortable>
                                <template #body="{ data }">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-medium text-slate-900">{{ data.name }}</span>
                                        <Tag v-if="data.is_protected" :value="data.protection_label" severity="warn" />
                                    </div>
                                </template>
                            </Column>
                            <Column field="guard_name" header="Guard" sortable />
                            <Column field="roles_count" header="Roles" sortable>
                                <template #body="{ data }"><Tag :value="String(data.roles_count ?? 0)" severity="info" /></template>
                            </Column>
                            <Column field="created_at" header="Letrehozva" sortable>
                                <template #body="{ data }">{{ formatDate(data.created_at) }}</template>
                            </Column>
                            <Column header="Muveletek" :style="{ width: '120px' }">
                                <template #body="{ data }"><RowActionMenu :items="permissionActionItems(data)" /></template>
                            </Column>
                        </DataTable>
                    </div>

                    <div class="space-y-4 p-6 lg:hidden">
                        <div class="grid gap-3">
                            <div class="relative">
                                <i class="pi pi-search pointer-events-none absolute left-3 top-1/2 z-10 -translate-y-1/2 text-sm text-slate-400" />
                                <InputText v-model="filters.search" fluid class="h-11 w-full pl-10" placeholder="Kereses permission nev vagy guard alapjan" />
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center justify-end gap-3">
                            <Button label="Frissites" icon="pi pi-refresh" severity="secondary" outlined :loading="loading || submitting" :disabled="loading || submitting" @click="refreshPermissions" />
                            <Button v-if="permissions.create" label="Uj permission" icon="pi pi-plus" severity="primary" :disabled="loading || submitting" @click="openCreateDialog" />
                        </div>

                        <div v-if="loading" class="rounded-2xl border border-dashed border-slate-300 px-4 py-6 text-sm text-slate-500">Betoltes folyamatban...</div>

                        <template v-else-if="items.length > 0">
                            <article v-for="permission in items" :key="permission.id" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="text-lg font-semibold text-slate-950">{{ permission.name }}</h3>
                                            <Tag v-if="permission.is_protected" :value="permission.protection_label" severity="warn" />
                                        </div>
                                        <p class="mt-1 text-sm text-slate-500">{{ permission.guard_name }}</p>
                                    </div>
                                    <Tag :value="`${permission.roles_count ?? 0} role`" severity="info" />
                                </div>

                                <dl class="mt-4 grid gap-3 text-sm text-slate-600">
                                    <div>
                                        <dt class="font-semibold text-slate-900">Letrehozva</dt>
                                        <dd>{{ formatDate(permission.created_at) }}</dd>
                                    </div>
                                </dl>

                                <div class="mt-5 flex gap-3">
                                    <Button v-if="permissions.update && permission.can?.update !== false" label="Szerkesztes" severity="secondary" text @click="openEditDialog(permission)" />
                                    <Button v-if="permissions.delete && permission.can?.delete !== false && !permission.is_protected" label="Torles" severity="danger" text @click="confirmDelete(permission)" />
                                </div>
                            </article>
                        </template>

                        <EmptyStatePanel v-else title="Nincs megjelenitheto permission" description="A jelenlegi szurok mellett nincs talalat. Modositsd a keresest vagy hozz letre uj permissiont." :tags="['Permissions', 'Local RBAC']" />
                    </div>
                </div>
            </AdminTableCard>
        </div>

        <CreatePermissionDialog :visible="showCreateDialog" :form="form" :errors="formErrors" :submitting="submitting" @update:visible="(value) => value ? (showCreateDialog = value) : closeCreateDialog()" @submit="submitCreate" />
        <EditPermissionDialog :visible="showEditDialog" :form="form" :errors="formErrors" :submitting="submitting" :permission="editingPermission" @update:visible="(value) => value ? (showEditDialog = value) : closeEditDialog()" @submit="submitUpdate" />
    </AuthenticatedLayout>
</template>
