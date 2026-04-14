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
    CompanyApiError,
    createCompany,
    deleteCompany,
    listCompanies,
    updateCompany,
} from "@/Services/companyService";
import { Head } from "@inertiajs/vue3";
import Button from "primevue/button";
import Column from "primevue/column";
import ConfirmDialog from "primevue/confirmdialog";
import InputText from "primevue/inputtext";
import Select from "primevue/select";
import Tag from "primevue/tag";
import { useConfirm } from "primevue/useconfirm";
import { useToast } from "primevue/usetoast";
import { onMounted, reactive, ref, watch } from "vue";
import CreateCompanyDialog from "./Partials/CreateCompanyDialog.vue";
import EditCompanyDialog from "./Partials/EditCompanyDialog.vue";
import { IconField, InputIcon } from "primevue";

const props = defineProps({
    companiesApi: { type: Object, required: true },
    permissions: { type: Object, required: true },
    searchValue: { type: String, default: "" },
    searchPlaceholder: { type: String, default: "Kereses nev, kod vagy e-mail alapjan" },
});

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
    { label: "Minden statusz", value: null },
    { label: "Aktiv", value: true },
    { label: "Inaktiv", value: false },
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
        handleApiError(error, "A cegek betoltese sikertelen volt.");
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
            summary: "Sikeres muvelet",
            detail: "A ceg letrehozasa sikeres volt.",
            life: 3000,
        });
        closeCreateDialog();
        resetPagination();
        await loadCompanies();
    } catch (error) {
        handleMutationError(error, "A ceg letrehozasa sikertelen volt.");
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
            summary: "Sikeres muvelet",
            detail: "A ceg adatai frissultek.",
            life: 3000,
        });
        closeEditDialog();
        await loadCompanies();
    } catch (error) {
        handleMutationError(error, "A ceg modositasa sikertelen volt.");
    } finally {
        submitting.value = false;
    }
}

function confirmDelete(company) {
    confirm.require({
        header: "Torles megerositese",
        message: `Biztosan torolni szeretned a(z) ${company.name} ceget?`,
        acceptLabel: "Torles",
        rejectLabel: "Megse",
        acceptClass: "p-button-danger",
        accept: async () => {
            try {
                await deleteCompany(props.companiesApi, company.id);
                toast.add({
                    severity: "success",
                    summary: "Sikeres muvelet",
                    detail: "A ceg torlese sikeres volt.",
                    life: 3000,
                });

                if (companies.value.length === 1 && tableState.page > 1) {
                    tableState.page -= 1;
                }

                await loadCompanies();
            } catch (error) {
                handleApiError(error, "A ceg torlese sikertelen volt.");
            }
        },
    });
}

function companyActionItems(company) {
    return [
        props.permissions.update
            ? {
                  label: "Szerkesztes",
                  icon: "pi pi-pencil",
                  isPrimary: true,
                  command: () => openEditDialog(company),
              }
            : null,
        props.permissions.delete
            ? {
                  label: "Torles",
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
        summary: "Sikeres muvelet",
        detail: "A ceglista frissult.",
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
        summary: "Hiba tortent",
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
    return isActive ? "Aktiv" : "Inaktiv";
}

function statusSeverity(isActive) {
    return isActive ? "success" : "secondary";
}

function formatDate(value) {
    if (!value) {
        return "-";
    }

    const date = new Date(value.replace(" ", "T"));

    return Number.isNaN(date.getTime()) ? value : date.toLocaleString("hu-HU");
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
    <Head title="Companies" />

    <AuthenticatedLayout>
        <ConfirmDialog />

        <div class="admin-table-page">
            <PageHeader
                title="Companies"
                description="A helyi cegtorzs teljes adminisztracioja keresessel, szuressel es jogosultsagkezelessel."
            />

            <AdminTableCard>
                <div class="admin-table-shell">
                    <div class="hidden min-h-0 flex-1 lg:flex">
                        <BaseDataTable
                            :value="companies"
                            :loading="loading"
                            loading-message="Cegek betoltese folyamatban..."
                            empty-message="Nincs megjelenitheto ceg."
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
                                    :search-placeholder="searchPlaceholder"
                                    :canCreate="permissions.create"
                                    createLabel="Uj ceg"
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
                                            placeholder="Statusz"
                                            show-clear
                                            @change="handleStatusFilterChange"
                                        />
                                    </template>
                                </AdminTableToolbar>
                            </template>

                            <template #empty>
                                <div class="px-6 py-10">
                                    <EmptyStatePanel
                                        title="Nincs megjelenitheto ceg"
                                        description="A jelenlegi szurok mellett nincs talalat. Modositsd a keresest vagy hozz letre uj ceget."
                                        :tags="['Companies', 'Admin CRUD']"
                                    />
                                </div>
                            </template>

                            <!-- Name -->
                            <Column field="name" header="Cegnev" sortable />
                            <!-- Code -->
                            <Column field="code" header="Kod" sortable />
                            <!-- Email -->
                            <Column field="email" header="E-mail" sortable />
                            <!-- Phone -->
                            <Column field="phone" header="Telefonszam" />
                            <!-- Is Active -->
                            <Column field="is_active" header="Statusz" sortable>
                                <template #body="{ data }">
                                    <Tag
                                        :value="statusLabel(data.is_active)"
                                        :severity="statusSeverity(data.is_active)"
                                    />
                                </template>
                            </Column>
                            <!-- Created At -->
                            <Column field="created_at" header="Letrehozva" sortable>
                                <template #body="{ data }">
                                    {{ formatDate(data.created_at) }}
                                </template>
                            </Column>
                            <!-- Műveletek -->
                            <Column header="Muveletek" :style="{ width: '11rem' }">
                                <template #body="{ data }">
                                    <RowActionMenu :items="companyActionItems(data)" />
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
                                    :placeholder="searchPlaceholder"
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
                                placeholder="Statusz"
                                show-clear
                                @change="handleStatusFilterChange"
                            />
                        </div>

                        <div class="flex flex-none flex-wrap items-center justify-end gap-3">
                            <Button
                                label="Frissites"
                                icon="pi pi-refresh"
                                severity="secondary"
                                outlined
                                :loading="loading || submitting"
                                :disabled="loading || submitting"
                                @click="refreshCompanies"
                            />
                            <Button
                                v-if="permissions.create"
                                label="Uj ceg"
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
                            Betoltes folyamatban...
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
                                            E-mail
                                        </dt>
                                        <dd>{{ company.email || "-" }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-slate-900">
                                            Telefonszam
                                        </dt>
                                        <dd>{{ company.phone || "-" }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-slate-900">
                                            Letrehozva
                                        </dt>
                                        <dd>{{ formatDate(company.created_at) }}</dd>
                                    </div>
                                </dl>

                                <div class="mt-5 flex gap-3">
                                    <Button
                                        v-if="permissions.update"
                                        label="Szerkesztes"
                                        severity="secondary"
                                        text
                                        @click="openEditDialog(company)"
                                    />
                                    <Button
                                        v-if="permissions.delete"
                                        label="Torles"
                                        severity="danger"
                                        text
                                        @click="confirmDelete(company)"
                                    />
                                </div>
                            </article>
                        </template>

                        <EmptyStatePanel
                            v-else
                            title="Nincs megjelenitheto ceg"
                            description="A jelenlegi szurok mellett nincs talalat. Modositsd a keresest vagy hozz letre uj ceget."
                            :tags="['Companies', 'Admin CRUD']"
                        />
                    </div>
                </div>

                <template #footer>
                    <AdminTableSummary
                        :page="tableState.page"
                        :per-page="tableState.perPage"
                        :total="tableState.totalRecords"
                        :last-page="lastPage"
                        item-label="ceg"
                    />
                </template>
            </AdminTableCard>
        </div>

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
