<script setup>
import EmptyStatePanel from "@/Components/EmptyStatePanel.vue";
import AdminTableCard from "@/Components/Admin/AdminTableCard.vue";
import AdminTableToolbar from "@/Components/Admin/AdminTableToolbar.vue";
import PageHeader from "@/Components/PageHeader.vue";
import RowActionMenu from "@/Components/Admin/RowActionMenu.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import {
    RoleApiError,
    createRole,
    deleteRole,
    listRoles,
    updateRole,
} from "@/Services/roleService";
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

import CreateRoleDialog from "./Partials/CreateRoleDialog.vue";
import EditRoleDialog from "./Partials/EditRoleDialog.vue";

const props = defineProps({
    rolesApi: { type: Object, required: true },
    permissions: { type: Object, required: true },
    permissionOptions: { type: Array, default: () => [] },
});

const toast = useToast();
const confirm = useConfirm();

const roles = ref([]);
const loading = ref(false);
const showCreateDialog = ref(false);
const showEditDialog = ref(false);
const editingRole = ref(null);
const submitting = ref(false);

const filters = reactive({
    search: "",
});

const tableState = reactive({
    page: 1,
    perPage: 10,
    total: 0,
    sortField: "created_at",
    sortOrder: "desc",
});

const form = reactive(defaultForm());
const formErrors = reactive({});
const firstRecordIndex = computed(() => (tableState.page - 1) * tableState.perPage);

let searchDebounceId = null;

function defaultForm() {
    return {
        name: "",
        guard_name: "web",
        permission_ids: [],
    };
}

function resetForm(role = null) {
    Object.assign(
        form,
        role
            ? {
                  name: role.name ?? "",
                  guard_name: role.guard_name ?? "web",
                  permission_ids: [...(role.permission_ids ?? [])],
              }
            : defaultForm(),
    );

    clearFormErrors();
}

function clearFormErrors() {
    Object.keys(formErrors).forEach((key) => {
        delete formErrors[key];
    });
}

function getRequestParams() {
    return {
        page: tableState.page,
        per_page: tableState.perPage,
        sort_field: tableState.sortField,
        sort_order: tableState.sortOrder,
        search: filters.search || undefined,
    };
}

async function loadRoles() {
    loading.value = true;

    try {
        const envelope = await listRoles(props.rolesApi, getRequestParams());
        roles.value = envelope.data.items ?? [];

        const pagination = envelope.meta.pagination ?? {};
        tableState.total = pagination.total ?? 0;
        tableState.page = pagination.current_page ?? tableState.page;
        tableState.perPage = pagination.per_page ?? tableState.perPage;
    } catch (error) {
        handleApiError(error, "A role lista betoltese sikertelen volt.");
    } finally {
        loading.value = false;
    }
}

function openCreateDialog() {
    resetForm();
    showCreateDialog.value = true;
}

function openEditDialog(role) {
    editingRole.value = role;
    resetForm(role);
    showEditDialog.value = true;
}

function closeCreateDialog() {
    showCreateDialog.value = false;
    resetForm();
}

function closeEditDialog() {
    showEditDialog.value = false;
    editingRole.value = null;
    resetForm();
}

async function submitCreate() {
    submitting.value = true;
    clearFormErrors();

    try {
        await createRole(props.rolesApi, form);
        toast.add({
            severity: "success",
            summary: "Sikeres muvelet",
            detail: "A role letrehozasa sikeres volt.",
            life: 3000,
        });
        closeCreateDialog();
        tableState.page = 1;
        await loadRoles();
    } catch (error) {
        handleMutationError(error, "A role letrehozasa sikertelen volt.");
    } finally {
        submitting.value = false;
    }
}

async function submitUpdate() {
    if (!editingRole.value) {
        return;
    }

    submitting.value = true;
    clearFormErrors();

    try {
        await updateRole(props.rolesApi, editingRole.value.id, form);
        toast.add({
            severity: "success",
            summary: "Sikeres muvelet",
            detail: "A role frissitese sikeres volt.",
            life: 3000,
        });
        closeEditDialog();
        await loadRoles();
    } catch (error) {
        handleMutationError(error, "A role modositasa sikertelen volt.");
    } finally {
        submitting.value = false;
    }
}

function confirmDelete(role) {
    confirm.require({
        header: "Torles megerositese",
        message: `Biztosan torolni szeretned a(z) ${role.name} role-t?`,
        acceptLabel: "Torles",
        rejectLabel: "Megse",
        acceptClass: "p-button-danger",
        accept: async () => {
            try {
                await deleteRole(props.rolesApi, role.id);
                toast.add({
                    severity: "success",
                    summary: "Sikeres muvelet",
                    detail: "A role torlese sikeres volt.",
                    life: 3000,
                });

                if (roles.value.length === 1 && tableState.page > 1) {
                    tableState.page -= 1;
                }

                await loadRoles();
            } catch (error) {
                handleApiError(error, "A role torlese sikertelen volt.");
            }
        },
    });
}

function roleActionItems(role) {
    return [
        props.permissions.update && role.can?.update !== false
            ? {
                  label: "Szerkesztes",
                  icon: "pi pi-pencil",
                  command: () => openEditDialog(role),
              }
            : null,
        props.permissions.delete && role.can?.delete !== false
            ? {
                  label: "Torles",
                  icon: "pi pi-trash",
                  command: () => confirmDelete(role),
              }
            : null,
    ];
}

async function refreshRoles() {
    await loadRoles();

    toast.add({
        severity: "success",
        summary: "Sikeres muvelet",
        detail: "A role lista frissult.",
        life: 2500,
    });
}

function handleTablePage(event) {
    tableState.page = (event.page ?? 0) + 1;
    tableState.perPage = event.rows ?? tableState.perPage;
    loadRoles();
}

function handleTableSort(event) {
    tableState.sortField = event.sortField ?? "created_at";
    tableState.sortOrder = event.sortOrder === 1 ? "asc" : "desc";
    tableState.page = 1;
    loadRoles();
}

function handleApiError(error, fallbackMessage) {
    if (error instanceof RoleApiError && error.status === 401) {
        const redirectTarget = error.meta.reauth_to || error.meta.redirect_to || route("login");
        window.location.assign(redirectTarget);
        return;
    }

    toast.add({
        severity: "error",
        summary: "Hiba tortent",
        detail: error instanceof RoleApiError ? error.message : fallbackMessage,
        life: 4000,
    });
}

function handleMutationError(error, fallbackMessage) {
    if (error instanceof RoleApiError && error.status === 422) {
        Object.assign(formErrors, error.errors ?? {});
        return;
    }

    handleApiError(error, fallbackMessage);
}

function formatDate(value) {
    if (!value) {
        return "-";
    }

    const date = new Date(value.replace(" ", "T"));

    return Number.isNaN(date.getTime()) ? value : date.toLocaleString("hu-HU");
}

watch(
    () => filters.search,
    () => {
        if (searchDebounceId) {
            window.clearTimeout(searchDebounceId);
        }

        searchDebounceId = window.setTimeout(() => {
            tableState.page = 1;
            loadRoles();
        }, 350);
    },
);

onMounted(loadRoles);
</script>

<template>
    <Head title="Roles" />

    <AuthenticatedLayout>
        <ConfirmDialog />

        <div class="admin-table-page">
            <PageHeader
                title="Roles"
                description="A helyi szerepkorok teljes adminisztracioja, jogosultsag-hozzarendelessel es lokalis RBAC kezelessel."
            />

            <AdminTableCard>
                <div class="admin-table-shell">
                    <div class="hidden min-h-0 flex-1 lg:flex">
                        <DataTable
                            :value="roles"
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
                                <AdminTableToolbar
                                    :canCreate="permissions.create"
                                    createLabel="Uj role"
                                    :canBulkDelete="false"
                                    :selectedCount="0"
                                    :selectableCount="0"
                                    :busy="loading || submitting"
                                    @create="openCreateDialog"
                                    @refresh="refreshRoles"
                                >
                                    <template #search>
                                        <IconField class="w-full">
                                            <InputIcon class="pi pi-search text-slate-400" />
                                            <InputText
                                                v-model="filters.search"
                                                fluid
                                                placeholder="Kereses role nev vagy guard alapjan"
                                                class="w-full"
                                            />
                                        </IconField>
                                    </template>
                                </AdminTableToolbar>
                            </template>

                            <template #empty>
                                <div class="px-6 py-10">
                                    <EmptyStatePanel
                                        title="Nincs megjelenitheto role"
                                        description="A jelenlegi szurok mellett nincs talalat. Modositsd a keresest vagy hozz letre uj role-t."
                                        :tags="['Roles', 'Local RBAC']"
                                    />
                                </div>
                            </template>

                            <Column field="id" header="ID" sortable />
                            <Column field="name" header="Role" sortable />
                            <Column field="guard_name" header="Guard" sortable />
                            <Column field="permissions_count" header="Permissions" sortable>
                                <template #body="{ data }">
                                    <Tag :value="String(data.permissions_count ?? 0)" severity="info" />
                                </template>
                            </Column>
                            <Column field="created_at" header="Letrehozva" sortable>
                                <template #body="{ data }">
                                    {{ formatDate(data.created_at) }}
                                </template>
                            </Column>
                            <Column header="Muveletek" :style="{ width: '120px' }">
                                <template #body="{ data }">
                                    <RowActionMenu :items="roleActionItems(data)" />
                                </template>
                            </Column>
                        </DataTable>
                    </div>

                    <div class="space-y-4 p-6 lg:hidden">
                        <div class="grid gap-3">
                            <div class="relative">
                                <i class="pi pi-search pointer-events-none absolute left-3 top-1/2 z-10 -translate-y-1/2 text-sm text-slate-400" />
                                <InputText
                                    v-model="filters.search"
                                    fluid
                                    class="h-11 w-full pl-10"
                                    placeholder="Kereses role nev vagy guard alapjan"
                                />
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center justify-end gap-3">
                            <Button
                                label="Frissites"
                                icon="pi pi-refresh"
                                severity="secondary"
                                outlined
                                :loading="loading || submitting"
                                :disabled="loading || submitting"
                                @click="refreshRoles"
                            />
                            <Button
                                v-if="permissions.create"
                                label="Uj role"
                                icon="pi pi-plus"
                                severity="primary"
                                :disabled="loading || submitting"
                                @click="openCreateDialog"
                            />
                        </div>

                        <div v-if="loading" class="rounded-2xl border border-dashed border-slate-300 px-4 py-6 text-sm text-slate-500">
                            Betoltes folyamatban...
                        </div>

                        <template v-else-if="roles.length > 0">
                            <article
                                v-for="role in roles"
                                :key="role.id"
                                class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="text-lg font-semibold text-slate-950">{{ role.name }}</h3>
                                        <p class="mt-1 text-sm text-slate-500">{{ role.guard_name }}</p>
                                    </div>
                                    <Tag :value="`${role.permissions_count ?? 0} permission`" severity="info" />
                                </div>

                                <dl class="mt-4 grid gap-3 text-sm text-slate-600">
                                    <div>
                                        <dt class="font-semibold text-slate-900">Permissionok</dt>
                                        <dd>{{ role.permission_names?.join(', ') || '-' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-slate-900">Letrehozva</dt>
                                        <dd>{{ formatDate(role.created_at) }}</dd>
                                    </div>
                                </dl>

                                <div class="mt-5 flex gap-3">
                                    <Button
                                        v-if="permissions.update && role.can?.update !== false"
                                        label="Szerkesztes"
                                        severity="secondary"
                                        text
                                        @click="openEditDialog(role)"
                                    />
                                    <Button
                                        v-if="permissions.delete && role.can?.delete !== false"
                                        label="Torles"
                                        severity="danger"
                                        text
                                        @click="confirmDelete(role)"
                                    />
                                </div>
                            </article>
                        </template>

                        <EmptyStatePanel
                            v-else
                            title="Nincs megjelenitheto role"
                            description="A jelenlegi szurok mellett nincs talalat. Modositsd a keresest vagy hozz letre uj role-t."
                            :tags="['Roles', 'Local RBAC']"
                        />
                    </div>
                </div>
            </AdminTableCard>
        </div>

        <CreateRoleDialog
            :visible="showCreateDialog"
            :form="form"
            :errors="formErrors"
            :submitting="submitting"
            :permissionOptions="permissionOptions"
            @update:visible="(value) => value ? (showCreateDialog = value) : closeCreateDialog()"
            @submit="submitCreate"
        />
        <EditRoleDialog
            :visible="showEditDialog"
            :form="form"
            :errors="formErrors"
            :submitting="submitting"
            :permissionOptions="permissionOptions"
            @update:visible="(value) => value ? (showEditDialog = value) : closeEditDialog()"
            @submit="submitUpdate"
        />
    </AuthenticatedLayout>
</template>
