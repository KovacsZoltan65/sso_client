<script setup>
import EmptyStatePanel from "@/Components/EmptyStatePanel.vue";
import AdminTableCard from "@/Components/Admin/AdminTableCard.vue";
import BaseDataTable from "@/Components/Admin/BaseDataTable.vue";
import AdminTableToolbar from "@/Components/Admin/AdminTableToolbar.vue";
import PageHeader from "@/Components/PageHeader.vue";
import RowActionMenu from "@/Components/Admin/RowActionMenu.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { useAdminSearchBehavior } from "@/Composables/useAdminSearchBehavior";
import UserEditDialog from "@/Pages/Users/Partials/UserEditDialog.vue";
import UserViewDialog from "@/Pages/Users/Partials/UserViewDialog.vue";
import { UserApiError, listUsers, showUser, updateUser } from "@/Services/userService";
import { Head, usePage } from "@inertiajs/vue3";
import { FilterMatchMode } from "@primevue/core/api";
import { trans } from "laravel-vue-i18n";
import Button from "primevue/button";
import Column from "primevue/column";
import InputText from "primevue/inputtext";
import Select from "primevue/select";
import Tag from "primevue/tag";
import { useToast } from "primevue/usetoast";
import { computed, onMounted, reactive, ref, watch } from "vue";
import { IconField, InputIcon } from "primevue";

const props = defineProps({
    usersApi: { type: Object, required: true },
    permissions: { type: Object, required: true },
});

const toast = useToast();
const page = usePage();
const currentLocale = computed(
    () =>
        page.props.locale?.current ||
        document.documentElement.getAttribute("lang") ||
        "hu"
);

const users = ref([]);
const loading = ref(false);
const submitting = ref(false);
const dialogLoading = ref(false);
const showViewDialog = ref(false);
const showEditDialog = ref(false);
const selectedUser = ref(null);

const tableFilters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
    localStatus: { value: null, matchMode: FilterMatchMode.EQUALS },
    hasSsoLink: { value: null, matchMode: FilterMatchMode.EQUALS },
});

const tableState = reactive({
    page: 1,
    perPage: 10,
    total: 0,
    sortField: "created_at",
    sortOrder: "desc",
});

const editForm = reactive(defaultEditForm());
const formErrors = reactive({});

const localStatusOptions = [
    { label: trans("common.all_statuses"), value: null },
    { label: trans("common.active"), value: "active" },
    { label: trans("common.inactive"), value: "inactive" },
];

const linkOptions = [
    { label: trans("users.all_links"), value: null },
    { label: trans("users.sso_linked"), value: true },
    { label: trans("users.sso_unlinked"), value: false },
];

const firstRecordIndex = computed(() => (tableState.page - 1) * tableState.perPage);
const compactSelectPt = {
    root: { class: "min-h-11" },
    label: { class: "flex min-h-11 items-center py-0" },
    dropdown: { class: "w-11" },
};

const searchBehavior = useAdminSearchBehavior();

function defaultEditForm() {
    return {
        id: null,
        sso_user_id: "",
        name: "",
        email: "",
        local_status: "active",
        notes: "",
    };
}

function resetForm(user = null) {
    Object.assign(
        editForm,
        user
            ? {
                  id: user.id ?? null,
                  sso_user_id: user.sso_user_id ?? "",
                  name: user.name ?? "",
                  email: user.email ?? "",
                  local_status: user.local_status ?? "active",
                  notes: user.notes ?? "",
              }
            : defaultEditForm()
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
        global: tableFilters.value.global.value || undefined,
        local_status: tableFilters.value.localStatus.value || undefined,
        has_sso_link: normalizeBooleanFilter(tableFilters.value.hasSsoLink.value),
    };
}

function normalizeBooleanFilter(value) {
    if (value === null || value === undefined || value === "") {
        return undefined;
    }

    if (value === true || value === "true") {
        return true;
    }

    if (value === false || value === "false") {
        return false;
    }

    return value;
}

async function loadUsers() {
    loading.value = true;

    try {
        const envelope = await listUsers(props.usersApi, getRequestParams());
        users.value = envelope.data.items ?? [];

        const pagination = envelope.meta.pagination ?? {};
        tableState.total = pagination.total ?? 0;
        tableState.page = pagination.current_page ?? tableState.page;
        tableState.perPage = pagination.per_page ?? tableState.perPage;
    } catch (error) {
        handleApiError(error, trans("users.loading_error"));
    } finally {
        loading.value = false;
    }
}

async function loadUserDetails(userId) {
    dialogLoading.value = true;

    try {
        const envelope = await showUser(props.usersApi, userId);
        return envelope.data.user ?? null;
    } catch (error) {
        handleApiError(error, trans("users.details_loading_error"));
        return null;
    } finally {
        dialogLoading.value = false;
    }
}

async function openViewDialog(user) {
    const details = await loadUserDetails(user.id);

    if (!details) {
        return;
    }

    selectedUser.value = details;
    showViewDialog.value = true;
}

async function openEditDialog(user) {
    const details = await loadUserDetails(user.id);

    if (!details) {
        return;
    }

    selectedUser.value = details;
    resetForm(details);
    showEditDialog.value = true;
}

function closeEditDialog() {
    showEditDialog.value = false;
    selectedUser.value = null;
    resetForm();
}

function setViewDialogVisible(visible) {
    showViewDialog.value = visible;

    if (!visible) {
        selectedUser.value = null;
    }
}

function setEditDialogVisible(visible) {
    if (visible) {
        showEditDialog.value = true;
        return;
    }

    closeEditDialog();
}

async function submitEdit() {
    if (!editForm.id) {
        return;
    }

    submitting.value = true;
    clearFormErrors();

    try {
        const envelope = await updateUser(props.usersApi, editForm.id, {
            local_status: editForm.local_status,
            notes: editForm.notes,
        });
        const updatedUser = envelope.data.user ?? null;

        if (updatedUser) {
            selectedUser.value = updatedUser;
        }

        toast.add({
            severity: "success",
            summary: trans("common.operation_successful"),
            detail: trans("users.updating_success"),
            life: 3000,
        });

        closeEditDialog();
        await loadUsers();
    } catch (error) {
        if (error instanceof UserApiError && error.status === 422) {
            Object.assign(formErrors, error.errors ?? {});
            return;
        }

        handleApiError(error, trans("users.updating_error"));
    } finally {
        submitting.value = false;
    }
}

function handleTablePage(event) {
    tableState.page = (event.page ?? 0) + 1;
    tableState.perPage = event.rows ?? tableState.perPage;
    loadUsers();
}

function handleTableSort(event) {
    tableState.sortField = event.sortField ?? "created_at";
    tableState.sortOrder = event.sortOrder === 1 ? "asc" : "desc";
    tableState.page = 1;
    loadUsers();
}

function onGlobalFilterInput(value) {
    tableFilters.value.global.value = value ?? null;
    searchBehavior.queueSearch(() => {
        tableState.page = 1;
        loadUsers();
    });
}

function submitSearch() {
    searchBehavior.submitSearch(() => {
        tableState.page = 1;
        loadUsers();
    });
}

function onFilter() {
    tableState.page = 1;
    loadUsers();
}

async function refreshUsers() {
    await loadUsers();

    toast.add({
        severity: "success",
        summary: trans("common.operation_successful"),
        detail: trans("users.list_updated"),
        life: 2500,
    });
}

function handleApiError(error, fallbackMessage) {
    if (error instanceof UserApiError && error.status === 401) {
        const redirectTarget =
            error.meta.reauth_to || error.meta.redirect_to || route("login");
        window.location.assign(redirectTarget);
        return;
    }

    toast.add({
        severity: "error",
        summary: trans("common.error"),
        detail: error instanceof UserApiError ? error.message : fallbackMessage,
        life: 4000,
    });
}

function formatDate(value) {
    if (!value) {
        return "-";
    }

    const date = new Date(value.replace(" ", "T"));

    return Number.isNaN(date.getTime())
        ? value
        : date.toLocaleString(currentLocale.value);
}

function statusLabel(status) {
    return status === "inactive" ? trans("common.inactive") : trans("common.active");
}

function statusSeverity(status) {
    return status === "inactive" ? "secondary" : "success";
}

function ssoLinkLabel(user) {
    return user.sso_user_id ? trans("users.sso_linked") : trans("users.sso_unlinked");
}

function ssoLinkSeverity(user) {
    return user.sso_user_id ? "info" : "warning";
}

function userActionItems(user) {
    return [
        {
            label: trans("common.view"),
            icon: "pi pi-eye",
            isPrimary: !props.permissions.manage || !user.can?.update,
            command: () => openViewDialog(user),
        },
        props.permissions.manage && user.can?.update
            ? {
                  label: trans("actions.edit"),
                  icon: "pi pi-pencil",
                  isPrimary: true,
                  command: () => openEditDialog(user),
              }
            : null,
    ];
}

watch(
    () => tableFilters.value.localStatus.value,
    () => {
        tableState.page = 1;
        loadUsers();
    }
);

watch(
    () => tableFilters.value.hasSsoLink.value,
    () => {
        tableState.page = 1;
        loadUsers();
    }
);

onMounted(loadUsers);
</script>

<template>
    <Head :title="trans('navigation.users.label')" />

    <AuthenticatedLayout>
        <PageHeader
            :title="trans('navigation.users.label')"
            :description="trans('navigation.users.description')"
        />

        <div class="admin-table-page">
            <AdminTableCard>
                <div class="admin-table-shell">
                    <div class="hidden min-h-0 flex-1 lg:flex">
                        <BaseDataTable
                            :value="users"
                            v-model:filters="tableFilters"
                            :loading="loading"
                            :loading-message="trans('users.loading_message')"
                            :empty-message="trans('users.empty_message')"
                            scrollable
                            lazy
                            paginator
                            removable-sort
                            filterDisplay="menu"
                            data-key="id"
                            :rows="tableState.perPage"
                            :first="firstRecordIndex"
                            :total-records="tableState.total"
                            :sort-field="tableState.sortField"
                            :sort-order="tableState.sortOrder === 'asc' ? 1 : -1"
                            @page="handleTablePage"
                            @sort="handleTableSort"
                            @filter="onFilter"
                        >
                            <template #header>
                                <AdminTableToolbar
                                    :canCreate="false"
                                    :canBulkDelete="false"
                                    :selectedCount="0"
                                    :selectableCount="0"
                                    :busy="loading || dialogLoading || submitting"
                                    @refresh="refreshUsers"
                                >
                                    <template #search>
                                        <IconField class="w-full">
                                            <InputIcon
                                                class="pi pi-search text-slate-400"
                                            />
                                            <InputText
                                                :modelValue="tableFilters.global.value"
                                                :placeholder="trans('users.search_placeholder')"
                                                class="w-full"
                                                @update:modelValue="onGlobalFilterInput"
                                                @keyup.enter="submitSearch"
                                            />
                                        </IconField>
                                    </template>
                                </AdminTableToolbar>
                            </template>

                            <template #empty>
                                <div class="px-6 py-10">
                                    <EmptyStatePanel
                                        :title="trans('users.filter_empty_title')"
                                        :description="trans('users.filter_empty_detail')"
                                        :tags="[trans('navigation.users.label'), trans('users.tag_sso_projection')]"
                                    />
                                </div>
                            </template>

                            <Column field="id" :header="trans('users.local_id')" sortable />
                            <Column field="sso_user_id" :header="trans('users.sso_user_id')" sortable />
                            <Column field="name" :header="trans('table.columns.name')" sortable />
                            <Column field="email" :header="trans('table.columns.email')" sortable />
                            <Column
                                field="local_status"
                                :header="trans('table.columns.status')"
                                sortable
                                :showFilterMatchModes="false"
                                :showFilterOperator="false"
                                :showAddButton="false"
                            >
                                <template #body="{ data }">
                                    <Tag
                                        :value="statusLabel(data.local_status)"
                                        :severity="statusSeverity(data.local_status)"
                                    />
                                </template>

                                <template #filter="{ filterModel, filterCallback }">
                                    <Select
                                        v-model="filterModel.value"
                                        :options="localStatusOptions"
                                        option-label="label"
                                        option-value="value"
                                        :placeholder="trans('common.all_statuses')"
                                        class="w-full"
                                        @change="filterCallback()"
                                    />
                                </template>
                            </Column>
                            <Column
                                field="hasSsoLink"
                                :header="trans('users.link_status')"
                                :showFilterMatchModes="false"
                                :showFilterOperator="false"
                                :showAddButton="false"
                            >
                                <template #body="{ data }">
                                    <Tag
                                        :value="ssoLinkLabel(data)"
                                        :severity="ssoLinkSeverity(data)"
                                    />
                                </template>

                                <template #filter="{ filterModel, filterCallback }">
                                    <Select
                                        v-model="filterModel.value"
                                        :options="linkOptions"
                                        option-label="label"
                                        option-value="value"
                                        :placeholder="trans('users.all_links')"
                                        class="w-full"
                                        @change="filterCallback()"
                                    />
                                </template>
                            </Column>
                            <Column
                                field="last_authenticated_at"
                                :header="trans('users.last_authenticated_at')"
                                sortable
                            >
                                <template #body="{ data }">
                                    {{ formatDate(data.last_authenticated_at) }}
                                </template>
                            </Column>
                            <Column field="created_at" :header="trans('table.columns.created_at')" sortable>
                                <template #body="{ data }">
                                    {{ formatDate(data.created_at) }}
                                </template>
                            </Column>
                            <Column field="updated_at" :header="trans('users.updated_at')" sortable>
                                <template #body="{ data }">
                                    {{ formatDate(data.updated_at) }}
                                </template>
                            </Column>
                            <Column :header="trans('table.columns.actions')" :style="{ width: '11rem' }">
                                <template #body="{ data }">
                                    <RowActionMenu :items="userActionItems(data)" />
                                </template>
                            </Column>
                        </BaseDataTable>
                    </div>

                    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto p-6 lg:hidden">
                        <div class="grid flex-none gap-3">
                            <IconField class="w-full">
                                <InputIcon class="pi pi-search" />
                                <InputText
                                    :modelValue="tableFilters.global.value"
                                    class="h-11 w-full"
                                    :placeholder="trans('users.search_placeholder')"
                                    @update:modelValue="onGlobalFilterInput"
                                    @keyup.enter="submitSearch"
                                />
                            </IconField>

                            <Select
                                v-model="tableFilters.localStatus.value"
                                :options="localStatusOptions"
                                :pt="compactSelectPt"
                                class="w-full"
                                option-label="label"
                                option-value="value"
                                :placeholder="trans('users.local_status')"
                                show-clear
                            />

                            <Select
                                v-model="tableFilters.hasSsoLink.value"
                                :options="linkOptions"
                                :pt="compactSelectPt"
                                class="w-full"
                                option-label="label"
                                option-value="value"
                                :placeholder="trans('users.link_status')"
                                show-clear
                            />
                        </div>

                        <div class="flex flex-none flex-wrap items-center justify-end gap-3">
                            <Button
                                :label="trans('common.refresh')"
                                icon="pi pi-refresh"
                                severity="secondary"
                                outlined
                                :loading="loading || dialogLoading || submitting"
                                :disabled="loading || dialogLoading || submitting"
                                @click="refreshUsers"
                            />
                        </div>

                        <div
                            v-if="loading"
                            class="rounded-2xl border border-dashed border-slate-300 px-4 py-6 text-sm text-slate-500"
                        >
                            {{ trans("users.loading_message") }}
                        </div>

                        <template v-else-if="users.length > 0">
                            <article
                                v-for="user in users"
                                :key="user.id"
                                class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="text-lg font-semibold text-slate-950">
                                            {{ user.name || "-" }}
                                        </h3>
                                        <p class="mt-1 text-sm text-slate-500">
                                            {{ user.email || "-" }}
                                        </p>
                                    </div>
                                    <Tag
                                        :value="statusLabel(user.local_status)"
                                        :severity="statusSeverity(user.local_status)"
                                    />
                                </div>

                                <dl class="mt-4 grid gap-3 text-sm text-slate-600">
                                    <div>
                                        <dt class="font-semibold text-slate-900">
                                            {{ trans("users.local_id") }}
                                        </dt>
                                        <dd>{{ user.id }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-slate-900">
                                            {{ trans("users.sso_user_id") }}
                                        </dt>
                                        <dd>{{ user.sso_user_id || "-" }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-slate-900">
                                            {{ trans("users.link_status") }}
                                        </dt>
                                        <dd>
                                            <Tag
                                                :value="ssoLinkLabel(user)"
                                                :severity="ssoLinkSeverity(user)"
                                            />
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-slate-900">
                                            {{ trans("users.last_authenticated_at") }}
                                        </dt>
                                        <dd>
                                            {{ formatDate(user.last_authenticated_at) }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-slate-900">
                                            {{ trans("users.updated_at") }}
                                        </dt>
                                        <dd>{{ formatDate(user.updated_at) }}</dd>
                                    </div>
                                </dl>

                                <div class="mt-5 flex justify-end">
                                    <RowActionMenu :items="userActionItems(user)" />
                                </div>
                            </article>
                        </template>

                        <EmptyStatePanel
                            v-else
                            :title="trans('users.filter_empty_title')"
                            :description="trans('users.filter_empty_detail')"
                            :tags="[trans('navigation.users.label'), trans('users.tag_sso_projection')]"
                        />
                    </div>
                </div>
            </AdminTableCard>
        </div>

        <UserViewDialog
            :visible="showViewDialog"
            :user="selectedUser"
            :locale="currentLocale"
            @update:visible="setViewDialogVisible"
        />
        <UserEditDialog
            :visible="showEditDialog"
            :form="editForm"
            :errors="formErrors"
            :submitting="submitting"
            @update:visible="setEditDialogVisible"
            @submit="submitEdit"
        />
    </AuthenticatedLayout>
</template>
