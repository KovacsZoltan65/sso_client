<script setup>
import EmptyStatePanel from "@/Components/EmptyStatePanel.vue";
import AdminTableCard from "@/Components/Admin/AdminTableCard.vue";
import BaseDataTable from "@/Components/Admin/BaseDataTable.vue";
import AdminTableSummary from "@/Components/Admin/AdminTableSummary.vue";
import AdminTableToolbar from "@/Components/Admin/AdminTableToolbar.vue";
import PageHeader from "@/Components/PageHeader.vue";
import RowActionMenu from "@/Components/Admin/RowActionMenu.vue";
import { useAdminSearchBehavior } from "@/Composables/useAdminSearchBehavior";
import { trans } from "laravel-vue-i18n";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { useAdminTableState } from "@/Composables/useAdminTableState";
import {
    CompanyApiError,
    createCompany,
    deleteCompany,
    listCompanies,
    updateCompany,
} from "@/Services/companyService";
import { Head, usePage } from "@inertiajs/vue3";
import Button from "primevue/button";
import Column from "primevue/column";
import ConfirmDialog from "primevue/confirmdialog";
import InputText from "primevue/inputtext";
import Select from "primevue/select";
import Tag from "primevue/tag";
import { useConfirm } from "primevue/useconfirm";
import { useToast } from "primevue/usetoast";
import { computed, onMounted, reactive, ref, watch } from "vue";
import CreateCompanyDialog from "./Partials/CreateCompanyDialog.vue";
import EditCompanyDialog from "./Partials/EditCompanyDialog.vue";
import { IconField, InputIcon } from "primevue";

const props = defineProps({
    companiesApi: { type: Object, required: true },
    permissions: { type: Object, required: true },
    searchValue: { type: String, default: "" },
    searchPlaceholder: { type: String, default: "" },
});

const resolvedSearchPlaceholder = computed(
    () => props.searchPlaceholder || trans("companies.search_placeholder")
);
const page = usePage();
const currentLocale = computed(
    () =>
        page.props.locale?.current ||
        document.documentElement.getAttribute("lang") ||
        "hu"
);

const toast = useToast();
const confirm = useConfirm();

const companies = ref([]);
const loading = ref(false);
const showCreateDialog = ref(false);
const showEditDialog = ref(false);
const editingCompany = ref(null);
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
        is_active: null,
    },
});

const form = reactive(defaultForm());
const formErrors = reactive({});

const statusOptions = [
    { label: trans("common.all_statuses"), value: null },
    { label: trans("common.active"), value: true },
    { label: trans("common.inactive"), value: false },
];

const compactSelectPt = {
    root: { class: "min-h-11" },
    label: { class: "flex min-h-11 items-center py-0" },
    dropdown: { class: "w-11" },
};
const searchBehavior = useAdminSearchBehavior();

function defaultForm() {
    return {
        name: "",
        code: "",
        email: "",
        phone: "",
        address: "",
        is_active: true,
    };
}

function resetForm(company = null) {
    Object.assign(
        form,
        company
            ? {
                  name: company.name ?? "",
                  code: company.code ?? "",
                  email: company.email ?? "",
                  phone: company.phone ?? "",
                  address: company.address ?? "",
                  is_active: Boolean(company.is_active),
              }
            : defaultForm()
    );

    clearFormErrors();
}

function clearFormErrors() {
    Object.keys(formErrors).forEach((key) => {
        delete formErrors[key];
    });
}

function getRequestParams() {
    return buildFetchParams({
        filters: {
            search: filters.search || undefined,
            is_active: filters.is_active,
        },
    });
}

async function loadCompanies() {
    loading.value = true;

    try {
        const envelope = await listCompanies(props.companiesApi, getRequestParams());
        companies.value = envelope.data.items ?? [];
        applyMeta(envelope.meta.pagination ?? {});
    } catch (error) {
        handleApiError(error, trans("companies.loading_error"));
    } finally {
        loading.value = false;
    }
}

function openCreateDialog() {
    resetForm();
    showCreateDialog.value = true;
}

function openEditDialog(company) {
    editingCompany.value = company;
    resetForm(company);
    showEditDialog.value = true;
}

function closeCreateDialog() {
    showCreateDialog.value = false;
    resetForm();
}

function closeEditDialog() {
    showEditDialog.value = false;
    editingCompany.value = null;
    resetForm();
}

async function submitCreate() {
    submitting.value = true;
    clearFormErrors();

    try {
        await createCompany(props.companiesApi, form);
        toast.add({
            severity: "success",
            summary: trans("common.operation_successful"),
            detail: trans("companies.creation_success"),
            life: 3000,
        });
        closeCreateDialog();
        resetPagination();
        await loadCompanies();
    } catch (error) {
        handleMutationError(error, "");
    } finally {
        submitting.value = false;
    }
}

async function submitUpdate() {
    if (!editingCompany.value) {
        return;
    }

    submitting.value = true;
    clearFormErrors();

    try {
        await updateCompany(props.companiesApi, editingCompany.value.id, form);
        toast.add({
            severity: "success",
            summary: trans("common.operation_successful"),
            detail: trans("companies.updating_success"),
            life: 3000,
        });
        closeEditDialog();
        await loadCompanies();
    } catch (error) {
        handleMutationError(error, trans("companies.updating_error"));
    } finally {
        submitting.value = false;
    }
}

function confirmDelete(company) {
    confirm.require({
        header: trans("common.deletion_confirmation"),
        message: trans("companies.deletion_confirm", { name: company.name }),
        acceptLabel: trans("actions.delete"),
        rejectLabel: trans("common.cancel"),
        acceptClass: "p-button-danger",
        accept: async () => {
            try {
                await deleteCompany(props.companiesApi, company.id);
                toast.add({
                    severity: "success",
                    summary: trans("common.operation_successful"),
                    detail: trans("companies.deletion_success"),
                    life: 3000,
                });

                if (companies.value.length === 1 && tableState.page > 1) {
                    tableState.page -= 1;
                }

                await loadCompanies();
            } catch (error) {
                handleApiError(error, trans("companies.deletion_error"));
            }
        },
    });
}

function companyActionItems(company) {
    return [
        props.permissions.update
            ? {
                  label: trans("actions.edit"),
                  icon: "pi pi-pencil",
                  isPrimary: true,
                  command: () => openEditDialog(company),
              }
            : null,
        props.permissions.delete
            ? {
                  label: trans("actions.delete"),
                  icon: "pi pi-trash",
                  isDangerous: true,
                  command: () => confirmDelete(company),
              }
            : null,
    ];
}

async function refreshCompanies() {
    await loadCompanies();

    toast.add({
        severity: "success",
        summary: trans("common.operation_successful"),
        detail: trans("companies.list_updated"),
        life: 2500,
    });
}

function handleSearchInput(value) {
    filters.search = value ?? "";
    searchBehavior.queueSearch(() => {
        resetPagination();
        loadCompanies();
    });
}

function submitSearch() {
    searchBehavior.submitSearch(() => {
        resetPagination();
        loadCompanies();
    });
}

function handleStatusFilterChange() {
    searchBehavior.applyFilterChange(() => {
        resetPagination();
        loadCompanies();
    });
}

function handleTablePage(event) {
    setPageFromEvent(event);
    loadCompanies();
}

function handleTableSort(event) {
    setSortFromEvent(event, "created_at");
    loadCompanies();
}

function handleApiError(error, fallbackMessage) {
    if (error instanceof CompanyApiError && error.status === 401) {
        const redirectTarget =
            error.meta.reauth_to || error.meta.redirect_to || route("login");
        window.location.assign(redirectTarget);
        return;
    }

    toast.add({
        severity: "error",
        summary: trans("common.error_occured"),
        detail: error instanceof CompanyApiError ? error.message : fallbackMessage,
        life: 4000,
    });
}

function handleMutationError(error, fallbackMessage) {
    if (error instanceof CompanyApiError && error.status === 422) {
        Object.assign(formErrors, error.errors ?? {});
        return;
    }

    handleApiError(error, fallbackMessage);
}

function statusLabel(isActive) {
    return isActive ? trans("common.active") : trans("common.inactive");
}

function statusSeverity(isActive) {
    return isActive ? "success" : "secondary";
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

watch(
    () => filters.is_active,
    () => {
        handleStatusFilterChange();
    }
);

onMounted(loadCompanies);
</script>

<template>
    <Head :title="trans('navigation.companies.label')" />

    <AuthenticatedLayout>
        <ConfirmDialog />

        <div class="admin-table-page">
            <PageHeader
                :title="trans('navigation.companies.label')"
                :description="trans('navigation.companies.description')"
            />

            <AdminTableCard>
                <div class="admin-table-shell">
                    <div class="hidden min-h-0 flex-1 lg:flex">
                        <BaseDataTable
                            :value="companies"
                            :loading="loading"
                            :loading-message="trans('companies.loading_message')"
                            :empty-message="trans('companies.empty_message')"
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
                                    :search-placeholder="resolvedSearchPlaceholder"
                                    :canCreate="permissions.create"
                                    :createLabel="trans('companies.new')"
                                    :canBulkDelete="false"
                                    :selectedCount="0"
                                    :selectableCount="0"
                                    :busy="loading || submitting"
                                    @update:searchValue="handleSearchInput"
                                    @submit-search="submitSearch"
                                    @create="openCreateDialog"
                                    @refresh="refreshCompanies"
                                >
                                    <template #filters>
                                        <Select
                                            v-model="filters.is_active"
                                            :options="statusOptions"
                                            class="w-full sm:w-56"
                                            option-label="label"
                                            option-value="value"
                                            :placeholder="trans('common.status')"
                                            show-clear
                                            @change="handleStatusFilterChange"
                                        />
                                    </template>
                                </AdminTableToolbar>
                            </template>

                            <template #empty>
                                <div class="px-6 py-10">
                                    <EmptyStatePanel
                                        :title="trans('companies.filter_empty_title')"
                                        :description="
                                            trans('companies.filter_empty_detail')
                                        "
                                        :tags="[
                                            trans('navigation.companies.label'),
                                            trans('companies.tag_admin_crud'),
                                        ]"
                                    />
                                </div>
                            </template>

                            <!-- Name -->
                            <Column field="name" :header="trans('table.columns.name')" sortable />
                            <!-- Code -->
                            <Column field="code" :header="trans('table.columns.code')" sortable />
                            <!-- Email -->
                            <Column
                                field="email"
                                :header="trans('table.columns.email')"
                                sortable
                            />
                            <!-- Phone -->
                            <Column field="phone" :header="trans('table.columns.phone')" />
                            <!-- Is Active -->
                            <Column
                                field="is_active"
                                :header="trans('table.columns.status')"
                                sortable
                            >
                                <template #body="{ data }">
                                    <Tag
                                        :value="statusLabel(data.is_active)"
                                        :severity="statusSeverity(data.is_active)"
                                    />
                                </template>
                            </Column>

                            <!-- Created At -->
                            <Column
                                field="created_at"
                                :header="trans('table.columns.created_at')"
                                sortable
                            >
                                <template #body="{ data }">
                                    {{ formatDate(data.created_at) }}
                                </template>
                            </Column>

                            <!-- Műveletek -->
                            <Column
                                :header="trans('table.columns.actions')"
                                :style="{ width: '11rem' }"
                            >
                                <template #body="{ data }">
                                    <RowActionMenu :items="companyActionItems(data)" />
                                </template>
                            </Column>
                        </BaseDataTable>
                    </div>

                    <div
                        class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto p-6 lg:hidden"
                    >
                        <div class="grid flex-none gap-3">
                            <IconField class="w-full">
                                <InputIcon class="pi pi-search" />
                                <InputText
                                    :modelValue="filters.search"
                                    :placeholder="resolvedSearchPlaceholder"
                                    class="h-11 w-full"
                                    @update:modelValue="handleSearchInput"
                                    @keyup.enter="submitSearch"
                                />
                            </IconField>

                            <Select
                                v-model="filters.is_active"
                                :options="statusOptions"
                                :pt="compactSelectPt"
                                class="w-full"
                                option-label="label"
                                option-value="value"
                                :placeholder="trans('common.status')"
                                show-clear
                                @change="handleStatusFilterChange"
                            />
                        </div>

                        <div
                            class="flex flex-none flex-wrap items-center justify-end gap-3"
                        >
                            <!-- Frissítés -->
                            <Button
                                :label="trans('common.refresh')"
                                icon="pi pi-refresh"
                                severity="secondary"
                                outlined
                                :loading="loading || submitting"
                                :disabled="loading || submitting"
                                @click="refreshCompanies"
                            />

                            <!-- Új -->
                            <Button
                                v-if="permissions.create"
                                :label="trans('companies.new')"
                                icon="pi pi-plus"
                                severity="primary"
                                :disabled="loading || submitting"
                                @click="openCreateDialog"
                            />
                        </div>

                        <div
                            v-if="loading"
                            class="rounded-2xl border border-dashed border-slate-300 px-4 py-6 text-sm text-slate-500"
                        >
                            {{ trans("companies.loading_message") }}
                        </div>

                        <template v-else-if="companies.length > 0">
                            <article
                                v-for="company in companies"
                                :key="company.id"
                                class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="text-lg font-semibold text-slate-950">
                                            {{ company.name }}
                                        </h3>
                                        <p class="mt-1 text-sm text-slate-500">
                                            {{ company.code }}
                                        </p>
                                    </div>
                                    <Tag
                                        :value="statusLabel(company.is_active)"
                                        :severity="statusSeverity(company.is_active)"
                                    />
                                </div>

                                <dl class="mt-4 grid gap-3 text-sm text-slate-600">
                                    <div>
                                        <dt class="font-semibold text-slate-900">
                                            {{ trans("table.columns.email") }}
                                        </dt>
                                        <dd>{{ company.email || "-" }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-slate-900">
                                            {{ trans("table.columns.phone") }}
                                        </dt>
                                        <dd>{{ company.phone || "-" }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-slate-900">
                                            {{ trans("table.columns.created_at") }}
                                        </dt>
                                        <dd>{{ formatDate(company.created_at) }}</dd>
                                    </div>
                                </dl>

                                <div class="mt-5 flex gap-3">
                                    <!-- Szerkesztés -->
                                    <Button
                                        v-if="permissions.update"
                                        :label="trans('actions.edit')"
                                        severity="secondary"
                                        text
                                        @click="openEditDialog(company)"
                                    />

                                    <!-- Törlés -->
                                    <Button
                                        v-if="permissions.delete"
                                        :label="trans('actions.delete')"
                                        severity="danger"
                                        text
                                        @click="confirmDelete(company)"
                                    />
                                </div>
                            </article>
                        </template>

                        <EmptyStatePanel
                            v-else
                            :title="trans('companies.loading_empty')"
                            :description="trans('companies.filter_empty_detail')"
                            :tags="[
                                trans('navigation.companies.label'),
                                trans('companies.tag_admin_crud'),
                            ]"
                        />
                    </div>
                </div>

                <template #footer>
                    <AdminTableSummary
                        :page="tableState.page"
                        :per-page="tableState.perPage"
                        :total="tableState.totalRecords"
                        :last-page="lastPage"
                        :item-label="trans('company')"
                    />
                </template>
            </AdminTableCard>
        </div>

        <!-- Új -->
        <CreateCompanyDialog
            :visible="showCreateDialog"
            :form="form"
            :errors="formErrors"
            :submitting="submitting"
            @update:visible="
                (value) => (value ? (showCreateDialog = value) : closeCreateDialog())
            "
            @submit="submitCreate"
        />

        <!-- Szerkesztés -->
        <EditCompanyDialog
            :visible="showEditDialog"
            :form="form"
            :errors="formErrors"
            :submitting="submitting"
            @update:visible="
                (value) => (value ? (showEditDialog = value) : closeEditDialog())
            "
            @submit="submitUpdate"
        />
    </AuthenticatedLayout>
</template>
