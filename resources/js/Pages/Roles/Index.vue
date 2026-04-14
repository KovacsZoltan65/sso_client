<script setup>
import EmptyStatePanel from "@/Components/EmptyStatePanel.vue";
import AdminTableCard from "@/Components/Admin/AdminTableCard.vue";
import BaseDataTable from "@/Components/Admin/BaseDataTable.vue";
import AdminTableSummary from "@/Components/Admin/AdminTableSummary.vue";
import AdminTableToolbar from "@/Components/Admin/AdminTableToolbar.vue";
import PageHeader from "@/Components/PageHeader.vue";
import RowActionMenu from "@/Components/Admin/RowActionMenu.vue";
import { useAdminSearchBehavior } from "@/Composables/useAdminSearchBehavior";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { useAdminTableState } from "@/Composables/useAdminTableState";
import {
    RoleApiError,
    createRole,
    deleteRole,
    listRoles,
    updateRole,
} from "@/Services/roleService";
import { Head, usePage } from "@inertiajs/vue3";
import { trans } from "laravel-vue-i18n";
import Button from "primevue/button";
import Column from "primevue/column";
import ConfirmDialog from "primevue/confirmdialog";
import InputText from "primevue/inputtext";
import Tag from "primevue/tag";
import { useConfirm } from "primevue/useconfirm";
import { useToast } from "primevue/usetoast";
import { computed, onMounted, reactive, ref, watch } from "vue";
import CreateRoleDialog from "./Partials/CreateRoleDialog.vue";
import EditRoleDialog from "./Partials/EditRoleDialog.vue";
import { IconField, InputIcon } from "primevue";

const props = defineProps({
    rolesApi: { type: Object, required: true },
    permissions: { type: Object, required: true },
    permissionOptions: { type: Array, default: () => [] },
});

const toast = useToast();
const confirm = useConfirm();
const page = usePage();
const currentLocale = computed(
    () =>
        page.props.locale?.current ||
        document.documentElement.getAttribute("lang") ||
        "hu"
);

const roles = ref([]);
const loading = ref(false);
const showCreateDialog = ref(false);
const showEditDialog = ref(false);
const editingRole = ref(null);
const submitting = ref(false);

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
    paramNames: {
        page: "page",
        perPage: "per_page",
        sortField: "sort_field",
        sortOrder: "sort_order",
    },
    serializeSortOrder: (value) => (value === 1 ? "asc" : "desc"),
});

const form = reactive(defaultForm());
const formErrors = reactive({});
const searchBehavior = useAdminSearchBehavior();

function defaultForm() {
    return { name: "", guard_name: "web", permission_ids: [] };
}

function resetForm(role = null) {
    Object.assign(form, role ? { name: role.name ?? "", guard_name: role.guard_name ?? "web", permission_ids: [...(role.permission_ids ?? [])] } : defaultForm());
    clearFormErrors();
}

function clearFormErrors() {
    Object.keys(formErrors).forEach((key) => delete formErrors[key]);
}

function getRequestParams() {
    return buildFetchParams({
        filters: {
            search: filters.search || undefined,
        },
    });
}

async function loadRoles() {
    loading.value = true;
    try {
        const envelope = await listRoles(props.rolesApi, getRequestParams());
        roles.value = envelope.data.items ?? [];
        applyMeta(envelope.meta.pagination ?? {});
    } catch (error) {
        handleApiError(error, trans("roles.loading_error"));
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
        toast.add({ severity: "success", summary: trans("common.operation_successful"), detail: trans("roles.creation_success"), life: 3000 });
        closeCreateDialog();
        resetPagination();
        await loadRoles();
    } catch (error) {
        handleMutationError(error, trans("roles.creation_error"));
    } finally {
        submitting.value = false;
    }
}

async function submitUpdate() {
    if (!editingRole.value) return;
    submitting.value = true;
    clearFormErrors();
    try {
        await updateRole(props.rolesApi, editingRole.value.id, form);
        toast.add({ severity: "success", summary: trans("common.operation_successful"), detail: trans("roles.updating_success"), life: 3000 });
        closeEditDialog();
        await loadRoles();
    } catch (error) {
        handleMutationError(error, trans("roles.updating_error"));
    } finally {
        submitting.value = false;
    }
}

function confirmDelete(role) {
    if (role.is_protected) {
        toast.add({ severity: "error", summary: trans("roles.protected_action_summary"), detail: trans("roles.protected_delete_blocked"), life: 4000 });
        return;
    }

    confirm.require({
        header: trans("common.deletion_confirmation"),
        message: trans("roles.deletion_confirm", { name: role.name }),
        acceptLabel: trans("common.delete"),
        rejectLabel: trans("common.cancel"),
        acceptClass: "p-button-danger",
        accept: async () => {
            try {
                await deleteRole(props.rolesApi, role.id);
                toast.add({ severity: "success", summary: trans("common.operation_successful"), detail: trans("roles.deletion_success"), life: 3000 });
                if (roles.value.length === 1 && tableState.page > 1) {
                    tableState.page -= 1;
                }
                await loadRoles();
            } catch (error) {
                handleApiError(error, trans("roles.deletion_error"));
            }
        },
    });
}

function roleActionItems(role) {
    return [
        props.permissions.update && role.can?.update !== false ? { label: trans("common.edit"), icon: "pi pi-pencil", isPrimary: true, command: () => openEditDialog(role) } : null,
        props.permissions.delete && role.can?.delete !== false && !role.is_protected ? { label: trans("common.delete"), icon: "pi pi-trash", isDangerous: true, command: () => confirmDelete(role) } : null,
    ];
}

async function refreshRoles() {
    await loadRoles();
    toast.add({ severity: "success", summary: trans("common.operation_successful"), detail: trans("roles.list_updated"), life: 2500 });
}

function handleSearchInput(value) {
    filters.search = value ?? "";
    searchBehavior.queueSearch(() => {
        resetPagination();
        loadRoles();
    });
}

function submitSearch() {
    searchBehavior.submitSearch(() => {
        resetPagination();
        loadRoles();
    });
}

function handleTablePage(event) {
    setPageFromEvent(event);
    loadRoles();
}

function handleTableSort(event) {
    setSortFromEvent(event, "created_at");
    loadRoles();
}

function handleApiError(error, fallbackMessage) {
    if (error instanceof RoleApiError && error.status === 401) {
        const redirectTarget = error.meta.reauth_to || error.meta.redirect_to || route("login");
        window.location.assign(redirectTarget);
        return;
    }
    toast.add({ severity: "error", summary: trans("common.error_occured"), detail: error instanceof RoleApiError ? error.message : fallbackMessage, life: 4000 });
}

function handleMutationError(error, fallbackMessage) {
    if (error instanceof RoleApiError && error.status === 422) {
        Object.assign(formErrors, error.errors ?? {});
        return;
    }
    handleApiError(error, fallbackMessage);
}

function formatDate(value) {
    if (!value) return "-";
    const date = new Date(value.replace(" ", "T"));
    return Number.isNaN(date.getTime()) ? value : date.toLocaleString(currentLocale.value);
}

onMounted(loadRoles);
</script>

<template>
    <Head :title="trans('navigation.roles.label')" />

    <AuthenticatedLayout>
        <ConfirmDialog />

        <div class="admin-table-page">
            <PageHeader :title="trans('navigation.roles.label')" :description="trans('navigation.roles.description')" />

            <AdminTableCard>
                <div class="admin-table-shell">
                    <div class="hidden min-h-0 flex-1 lg:flex">
                        <BaseDataTable
                            :value="roles"
                            :loading="loading"
                            :loading-message="trans('roles.loading_message')"
                            :empty-message="trans('roles.empty_message')"
                            removable-sort
                            data-key="id"
                            :rows="tableState.perPage"
                            :first="first"
                            :total-records="tableState.totalRecords"
                            :sort-field="tableState.sortField"
                            :sort-order="tableState.sortOrder"
                            :rows-per-page-options="[10, 25, 50]"
                            @page="handleTablePage"
                            @sort="handleTableSort"
                        >
                            <template #header>
                                <AdminTableToolbar
                                    searchable
                                    :search-value="filters.search"
                                    :search-placeholder="trans('roles.search_placeholder')"
                                    :canCreate="permissions.create"
                                    :createLabel="trans('roles.new')"
                                    :canBulkDelete="false"
                                    :selectedCount="0"
                                    :selectableCount="0"
                                    :busy="loading || submitting"
                                    @update:searchValue="handleSearchInput"
                                    @submit-search="submitSearch"
                                    @create="openCreateDialog"
                                    @refresh="refreshRoles"
                                />
                            </template>

                            <template #empty>
                                <div class="px-6 py-10">
                                    <EmptyStatePanel :title="trans('roles.filter_empty_title')" :description="trans('roles.filter_empty_detail')" :tags="[trans('navigation.roles.label'), trans('roles.tag_local_rbac')]" />
                                </div>
                            </template>

                            <Column field="id" header="ID" sortable />
                            <Column field="name" :header="trans('roles.role_name')" sortable>
                                <template #body="{ data }">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-medium text-slate-900">{{ data.name }}</span>
                                        <Tag v-if="data.is_protected" :value="data.protection_label" severity="warn" />
                                    </div>
                                </template>
                            </Column>
                            <Column field="guard_name" :header="trans('roles.guard_name')" sortable />
                            <Column field="permissions_count" :header="trans('roles.permissions')" sortable>
                                <template #body="{ data }">
                                    <Tag :value="String(data.permissions_count ?? 0)" severity="info" />
                                </template>
                            </Column>
                            <Column field="created_at" :header="trans('table.created_at')" sortable>
                                <template #body="{ data }">
                                    {{ formatDate(data.created_at) }}
                                </template>
                            </Column>
                            <Column :header="trans('table.actions')" :style="{ width: '11rem' }">
                                <template #body="{ data }">
                                    <RowActionMenu :items="roleActionItems(data)" />
                                </template>
                            </Column>
                        </BaseDataTable>
                    </div>

                    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto p-6 lg:hidden">
                        <div class="grid flex-none gap-3">
                            <IconField class="w-full">
                                <InputIcon class="pi pi-search" />
                                <InputText
                                    :modelValue="filters.search"
                                    class="h-11 w-full"
                                    :placeholder="trans('roles.search_placeholder')"
                                    @update:modelValue="handleSearchInput"
                                    @keyup.enter="submitSearch"
                                />
                            </IconField>
                        </div>

                        <div class="flex flex-none flex-wrap items-center justify-end gap-3">
                            <Button :label="trans('common.refresh')" icon="pi pi-refresh" severity="secondary" outlined :loading="loading || submitting" :disabled="loading || submitting" @click="refreshRoles" />
                            <Button v-if="permissions.create" :label="trans('roles.new')" icon="pi pi-plus" severity="primary" :disabled="loading || submitting" @click="openCreateDialog" />
                        </div>

                        <div v-if="loading" class="rounded-2xl border border-dashed border-slate-300 px-4 py-6 text-sm text-slate-500">{{ trans("roles.loading_message") }}</div>

                        <template v-else-if="roles.length > 0">
                            <article v-for="role in roles" :key="role.id" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="text-lg font-semibold text-slate-950">{{ role.name }}</h3>
                                            <Tag v-if="role.is_protected" :value="role.protection_label" severity="warn" />
                                        </div>
                                        <p class="mt-1 text-sm text-slate-500">{{ role.guard_name }}</p>
                                    </div>
                                    <Tag :value="trans('roles.permissions_count_badge', { count: role.permissions_count ?? 0 })" severity="info" />
                                </div>

                                <dl class="mt-4 grid gap-3 text-sm text-slate-600">
                                    <div>
                                        <dt class="font-semibold text-slate-900">{{ trans("roles.permissions") }}</dt>
                                        <dd>{{ role.permission_names?.join(", ") || "-" }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-slate-900">{{ trans("table.created_at") }}</dt>
                                        <dd>{{ formatDate(role.created_at) }}</dd>
                                    </div>
                                </dl>

                                <div class="mt-5 flex gap-3">
                                    <Button v-if="permissions.update && role.can?.update !== false" :label="trans('common.edit')" severity="secondary" text @click="openEditDialog(role)" />
                                    <Button v-if="permissions.delete && role.can?.delete !== false && !role.is_protected" :label="trans('common.delete')" severity="danger" text @click="confirmDelete(role)" />
                                </div>
                            </article>
                        </template>

                        <EmptyStatePanel v-else :title="trans('roles.filter_empty_title')" :description="trans('roles.filter_empty_detail')" :tags="[trans('navigation.roles.label'), trans('roles.tag_local_rbac')]" />
                    </div>
                </div>

                <template #footer>
                    <AdminTableSummary
                        :page="tableState.page"
                        :per-page="tableState.perPage"
                        :total="tableState.totalRecords"
                        :last-page="lastPage"
                        :item-label="trans('role')"
                    />
                </template>
            </AdminTableCard>
        </div>

        <CreateRoleDialog :visible="showCreateDialog" :form="form" :errors="formErrors" :submitting="submitting" :permissionOptions="permissionOptions" @update:visible="(value) => value ? (showCreateDialog = value) : closeCreateDialog()" @submit="submitCreate" />
        <EditRoleDialog :visible="showEditDialog" :form="form" :errors="formErrors" :submitting="submitting" :permissionOptions="permissionOptions" :role="editingRole" @update:visible="(value) => value ? (showEditDialog = value) : closeEditDialog()" @submit="submitUpdate" />
    </AuthenticatedLayout>
</template>
