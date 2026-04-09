<script setup>
import EmptyStatePanel from "@/Components/EmptyStatePanel.vue";
import AdminTableCard from "@/Components/Admin/AdminTableCard.vue";
import AdminTableToolbar from "@/Components/Admin/AdminTableToolbar.vue";
import PageHeader from "@/Components/PageHeader.vue";
import RowActionMenu from "@/Components/Admin/RowActionMenu.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
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
import DataTable from "primevue/datatable";
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
});

const toast = useToast();
const confirm = useConfirm();

const companies = ref([]);
const loading = ref(false);
const showCreateDialog = ref(false);
const showEditDialog = ref(false);
const editingCompany = ref(null);
const submitting = ref(false);

const filters = reactive({
    search: "",
    is_active: null,
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

const statusOptions = [
    { label: "Minden statusz", value: null },
    { label: "Aktiv", value: true },
    { label: "Inaktiv", value: false },
];

const firstRecordIndex = computed(() => (tableState.page - 1) * tableState.perPage);
const compactSelectPt = {
    root: { class: "min-h-11" },
    label: { class: "flex min-h-11 items-center py-0" },
    dropdown: { class: "w-11" },
};

let searchDebounceId = null;

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
    return {
        page: tableState.page,
        per_page: tableState.perPage,
        sort_field: tableState.sortField,
        sort_order: tableState.sortOrder,
        search: filters.search || undefined,
        is_active: filters.is_active,
    };
}

async function loadCompanies() {
    loading.value = true;

    try {
        const envelope = await listCompanies(props.companiesApi, getRequestParams());
        companies.value = envelope.data.items ?? [];

        const pagination = envelope.meta.pagination ?? {};
        tableState.total = pagination.total ?? 0;
        tableState.page = pagination.current_page ?? tableState.page;
        tableState.perPage = pagination.per_page ?? tableState.perPage;
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
        tableState.page = 1;
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
                  command: () => openEditDialog(company),
              }
            : null,
        props.permissions.delete
            ? {
                  label: "Torles",
                  icon: "pi pi-trash",
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

function handleTablePage(event) {
    tableState.page = (event.page ?? 0) + 1;
    tableState.perPage = event.rows ?? tableState.perPage;
    loadCompanies();
}

function handleTableSort(event) {
    tableState.sortField = event.sortField ?? "created_at";
    tableState.sortOrder = event.sortOrder === 1 ? "asc" : "desc";
    tableState.page = 1;
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
        tableState.page = 1;
        loadCompanies();
    }
);

watch(
    () => filters.search,
    () => {
        if (searchDebounceId) {
            window.clearTimeout(searchDebounceId);
        }

        searchDebounceId = window.setTimeout(() => {
            tableState.page = 1;
            loadCompanies();
        }, 350);
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
                        <DataTable
                            :value="companies"
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
                                    createLabel="Uj ceg"
                                    :canBulkDelete="false"
                                    :selectedCount="0"
                                    :selectableCount="0"
                                    :busy="loading || submitting"
                                    @create="openCreateDialog"
                                    @refresh="refreshCompanies"
                                >
                                    <template #search>
                                        <IconField class="w-full">
                                            <InputIcon
                                                class="pi pi-search text-slate-400"
                                            />
                                            <InputText
                                                v-model="filters.search"
                                                fluid
                                                placeholder="Kereses nev, kod vagy e-mail alapjan"
                                                class="w-full"
                                            />
                                        </IconField>
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

                            <Column field="name" header="Cegnev" sortable />
                            <Column field="code" header="Kod" sortable />
                            <Column field="email" header="E-mail" sortable />
                            <Column field="phone" header="Telefonszam" />
                            <Column field="is_active" header="Statusz" sortable>
                                <template #body="{ data }">
                                    <Tag
                                        :value="statusLabel(data.is_active)"
                                        :severity="statusSeverity(data.is_active)"
                                    />
                                </template>
                            </Column>
                            <Column field="created_at" header="Letrehozva" sortable>
                                <template #body="{ data }">
                                    {{ formatDate(data.created_at) }}
                                </template>
                            </Column>
                            <Column header="Muveletek" :style="{ width: '120px' }">
                                <template #body="{ data }">
                                    <RowActionMenu :items="companyActionItems(data)" />
                                </template>
                            </Column>
                        </DataTable>
                    </div>

                    <div class="space-y-4 p-6 lg:hidden">
                        <div class="grid gap-3">
                            <div class="relative">
                                <i
                                    class="pi pi-search pointer-events-none absolute left-3 top-1/2 z-10 -translate-y-1/2 text-sm text-slate-400"
                                />
                                <InputText
                                    v-model="filters.search"
                                    fluid
                                    class="h-11 w-full pl-10"
                                    placeholder="Kereses nev, kod vagy e-mail alapjan"
                                />
                            </div>

                            <Select
                                v-model="filters.is_active"
                                :options="statusOptions"
                                :pt="compactSelectPt"
                                class="w-full"
                                option-label="label"
                                option-value="value"
                                placeholder="Statusz"
                                show-clear
                            />
                        </div>

                        <div class="flex flex-wrap items-center justify-end gap-3">
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
            </AdminTableCard>
        </div>

        <CreateCompanyDialog
            :visible="showCreateDialog"
            :form="form"
            :errors="formErrors"
            :submitting="submitting"
            @update:visible="(value) => value ? (showCreateDialog = value) : closeCreateDialog()"
            @submit="submitCreate"
        />
        <EditCompanyDialog
            :visible="showEditDialog"
            :form="form"
            :errors="formErrors"
            :submitting="submitting"
            @update:visible="(value) => value ? (showEditDialog = value) : closeEditDialog()"
            @submit="submitUpdate"
        />
    </AuthenticatedLayout>
</template>

