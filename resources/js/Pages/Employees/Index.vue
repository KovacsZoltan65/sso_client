<script setup>
import EmptyStatePanel from "@/Components/EmptyStatePanel.vue";
import AdminTableCard from "@/Components/Admin/AdminTableCard.vue";
import BaseDataTable from "@/Components/Admin/BaseDataTable.vue";
import AdminTableSummary from "@/Components/Admin/AdminTableSummary.vue";
import AdminTableToolbar from "@/Components/Admin/AdminTableToolbar.vue";
import PageHeader from "@/Components/PageHeader.vue";
import RowActionMenu from "@/Components/Admin/RowActionMenu.vue";
import { useAdminSearchBehavior } from "@/Composables/useAdminSearchBehavior";
import { trans } from 'laravel-vue-i18n';
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { useAdminTableState } from "@/Composables/useAdminTableState";
import {
    EmployeeApiError,
    createEmployee,
    deleteEmployee,
    listEmployees,
    updateEmployee,
} from "@/Services/employeeService";
import { Head } from "@inertiajs/vue3";
import Button from "primevue/button";
import Column from "primevue/column";
import ConfirmDialog from "primevue/confirmdialog";
import InputText from "primevue/inputtext";
import Select from "primevue/select";
import Tag from "primevue/tag";
import { useConfirm } from "primevue/useconfirm";
import { useToast } from "primevue/usetoast";
import { computed, onMounted, reactive, ref, watch } from "vue";

import CreateEmployeeDialog from "./Partials/CreateEmployeeDialog.vue";
import EditEmployeeDialog from "./Partials/EditEmployeeDialog.vue";
import { IconField, InputIcon } from "primevue";

const props = defineProps({
    employeesApi: { type: Object, required: true },
    permissions: { type: Object, required: true },
    companies: { type: Array, default: () => [] },
    searchValue: { type: String, default: "" },
    searchPlaceholder: {
        type: String,
        default: "",
    },
});

const toast = useToast();
const confirm = useConfirm();

const employees = ref([]);
const loading = ref(false);
const showCreateDialog = ref(false);
const showEditDialog = ref(false);
const editingEmployee = ref(null);
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
        company_id: null,
        is_active: null,
    },
    paramNames: {
        page: "page",
        perPage: "per_page",
        sortField: "sort_field",
        sortOrder: "sort_order",
    },
    serializeSortOrder: (value) => value,
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
const resolvedSearchPlaceholder = computed(() => props.searchPlaceholder || trans('employees.search_placeholder'));

function defaultForm() {
    return {
        company_id: null,
        employee_number: "",
        name: "",
        email: "",
        phone: "",
        position: "",
        is_active: true,
    };
}

function resetForm(employee = null) {
    Object.assign(
        form,
        employee
            ? {
                  company_id: employee.company_id ?? null,
                  employee_number: employee.employee_number ?? "",
                  name: employee.name ?? "",
                  email: employee.email ?? "",
                  phone: employee.phone ?? "",
                  position: employee.position ?? "",
                  is_active: Boolean(employee.is_active),
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
            global: filters.search || undefined,
            company_id: filters.company_id ?? undefined,
            status: filters.is_active,
        },
    });
}

async function loadEmployees() {
    loading.value = true;

    try {
        const envelope = await listEmployees(props.employeesApi, getRequestParams());
        employees.value = envelope.data.items ?? [];
        applyMeta(envelope.meta.pagination ?? {});
    } catch (error) {
        handleApiError(error, "Az alkalmazottak betoltese sikertelen volt.");
    } finally {
        loading.value = false;
    }
}

function openCreateDialog() {
    resetForm();
    showCreateDialog.value = true;
}

function openEditDialog(employee) {
    editingEmployee.value = employee;
    resetForm(employee);
    showEditDialog.value = true;
}

function closeCreateDialog() {
    showCreateDialog.value = false;
    resetForm();
}

function closeEditDialog() {
    showEditDialog.value = false;
    editingEmployee.value = null;
    resetForm();
}

async function submitCreate() {
    submitting.value = true;
    clearFormErrors();

    try {
        await createEmployee(props.employeesApi, form);
        toast.add({
            severity: "success",
            summary: "Sikeres muvelet",
            detail: "Az alkalmazott letrehozasa sikeres volt.",
            life: 3000,
        });
        closeCreateDialog();
        resetPagination();
        await loadEmployees();
    } catch (error) {
        handleMutationError(error, "Az alkalmazott letrehozasa sikertelen volt.");
    } finally {
        submitting.value = false;
    }
}

async function submitUpdate() {
    if (!editingEmployee.value) {
        return;
    }

    submitting.value = true;
    clearFormErrors();

    try {
        await updateEmployee(props.employeesApi, editingEmployee.value.id, form);
        toast.add({
            severity: "success",
            summary: "Sikeres muvelet",
            detail: "Az alkalmazott adatai frissultek.",
            life: 3000,
        });
        closeEditDialog();
        await loadEmployees();
    } catch (error) {
        handleMutationError(error, "Az alkalmazott modositasa sikertelen volt.");
    } finally {
        submitting.value = false;
    }
}

function confirmDelete(employee) {
    confirm.require({
        header: "Torles megerositese",
        message: `Biztosan torolni szeretned a(z) ${employee.name} alkalmazottat?`,
        acceptLabel: "Torles",
        rejectLabel: "Megse",
        acceptClass: "p-button-danger",
        accept: async () => {
            try {
                await deleteEmployee(props.employeesApi, employee.id);
                toast.add({
                    severity: "success",
                    summary: "Sikeres muvelet",
                    detail: "Az alkalmazott torlese sikeres volt.",
                    life: 3000,
                });

                if (employees.value.length === 1 && tableState.page > 1) {
                    tableState.page -= 1;
                }

                await loadEmployees();
            } catch (error) {
                handleApiError(error, "Az alkalmazott torlese sikertelen volt.");
            }
        },
    });
}

function employeeActionItems(employee) {
    return [
        props.permissions.update
            ? {
                  label: "Szerkesztes",
                  icon: "pi pi-pencil",
                  isPrimary: true,
                  command: () => openEditDialog(employee),
              }
            : null,
        props.permissions.delete
            ? {
                  label: "Torles",
                  icon: "pi pi-trash",
                  isDangerous: true,
                  command: () => confirmDelete(employee),
              }
            : null,
    ];
}

async function refreshEmployees() {
    await loadEmployees();

    toast.add({
        severity: "success",
        summary: "Sikeres muvelet",
        detail: "Az alkalmazott lista frissult.",
        life: 2500,
    });
}

function handleSearchInput(value) {
    filters.search = value ?? "";
    searchBehavior.queueSearch(() => {
        resetPagination();
        loadEmployees();
    });
}

function submitSearch() {
    searchBehavior.submitSearch(() => {
        resetPagination();
        loadEmployees();
    });
}

function handleCompanyFilterChange() {
    searchBehavior.applyFilterChange(() => {
        resetPagination();
        loadEmployees();
    });
}

function handleStatusFilterChange() {
    searchBehavior.applyFilterChange(() => {
        resetPagination();
        loadEmployees();
    });
}

function handleTablePage(event) {
    setPageFromEvent(event);
    loadEmployees();
}

function handleTableSort(event) {
    setSortFromEvent(event, "created_at");
    loadEmployees();
}

function handleApiError(error, fallbackMessage) {
    if (error instanceof EmployeeApiError && error.status === 401) {
        const redirectTarget =
            error.meta.reauth_to || error.meta.redirect_to || route("login");
        window.location.assign(redirectTarget);
        return;
    }

    toast.add({
        severity: "error",
        summary: "Hiba tortent",
        detail: error instanceof EmployeeApiError ? error.message : fallbackMessage,
        life: 4000,
    });
}

function handleMutationError(error, fallbackMessage) {
    if (error instanceof EmployeeApiError && error.status === 422) {
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
    () => filters.company_id,
    () => {
        handleCompanyFilterChange();
    }
);

watch(
    () => filters.is_active,
    () => {
        handleStatusFilterChange();
    }
);

onMounted(loadEmployees);
</script>

<template>
    <Head title="Employees" />

    <AuthenticatedLayout>
        <ConfirmDialog />

        <div class="admin-table-page">
            <PageHeader
                title="Employees"
                description="A helyi alkalmazott torzs teljes adminisztracioja keresessel, szuressel es jogosultsagkezelessel."
            />

            <AdminTableCard>
                <div class="admin-table-shell">
                    <div class="hidden min-h-0 flex-1 lg:flex">
                        <BaseDataTable
                            :value="employees"
                            :loading="loading"
                            loading-message="Alkalmazottak betoltese folyamatban..."
                            empty-message="Nincs megjelenitheto alkalmazott."
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
                                    searchContainerClass="w-full lg:flex-1 lg:min-w-0"
                                    :search-placeholder="resolvedSearchPlaceholder"
                                    :canCreate="permissions.create"
                                    createLabel="Uj alkalmazott"
                                    :canBulkDelete="false"
                                    :selectedCount="0"
                                    :selectableCount="0"
                                    :busy="loading || submitting"
                                    @update:searchValue="filters.search = $event"
                                    @create="openCreateDialog"
                                    @refresh="refreshEmployees"
                                >
                                    <template #filters>
                                        <!-- Cégek szűrő -->
                                        <Select
                                            v-model="filters.company_id"
                                            :options="companies"
                                            option-label="name"
                                            option-value="id"
                                            placeholder="Ceg"
                                            show-clear
                                            class="w-full sm:w-56"
                                        />

                                        <!-- Státusz szűrő -->
                                        <Select
                                            v-model="filters.is_active"
                                            :options="statusOptions"
                                            option-label="label"
                                            option-value="value"
                                            placeholder="Statusz"
                                            show-clear
                                            class="w-full sm:w-56"
                                        />
                                    </template>
                                </AdminTableToolbar>
                            </template>

                            <template #empty>
                                <div class="px-6 py-10">
                                    <EmptyStatePanel
                                        title="Nincs megjelenitheto alkalmazott"
                                        description="A jelenlegi szurok mellett nincs talalat. Modositsd a keresest vagy hozz letre uj alkalmazottat."
                                        :tags="['Employees', 'Admin CRUD']"
                                    />
                                </div>
                            </template>

                            <Column field="employee_number" header="Azonosito" sortable />
                            <Column field="name" header="Nev" sortable />
                            <Column field="email" header="E-mail" sortable />
                            <Column field="phone" header="Telefonszam" />
                            <Column field="position" header="Pozicio" sortable />
                            <Column field="company_name" header="Ceg" sortable />
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
                            <Column header="Muveletek" :style="{ width: '11rem' }">
                                <template #body="{ data }">
                                    <RowActionMenu :items="employeeActionItems(data)" />
                                </template>
                            </Column>
                        </BaseDataTable>
                    </div>

                    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto p-6 lg:hidden">
                        <div class="grid flex-none gap-3">
                            <!--<div class="relative">
                                <i
                                    class="pi pi-search pointer-events-none absolute left-3 top-1/2 z-10 -translate-y-1/2 text-sm text-slate-400"
                                />
                                <InputText
                                    v-model="filters.search"
                                    fluid
                                    class="h-11 w-full pl-10"
                                    placeholder="Kereses nev, e-mail, pozicio vagy azonosito alapjan"
                                />
                            </div>-->
                            <IconField class="w-full">
                                <InputIcon class="pi pi-search" />
                                <InputText
                                    v-model="filters.search"
                                    :placeholder="resolvedSearchPlaceholder"
                                    class="h-11 w-full"
                                />
                            </IconField>

                            <Select
                                v-model="filters.company_id"
                                :options="companies"
                                :pt="compactSelectPt"
                                class="w-full"
                                option-label="name"
                                option-value="id"
                                placeholder="Ceg"
                                show-clear
                            />

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

                        <div class="flex flex-none flex-wrap items-center justify-end gap-3">
                            <Button
                                label="Frissites"
                                icon="pi pi-refresh"
                                severity="secondary"
                                outlined
                                :loading="loading || submitting"
                                :disabled="loading || submitting"
                                @click="refreshEmployees"
                            />
                            <Button
                                v-if="permissions.create"
                                label="Uj alkalmazott"
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

                        <template v-else-if="employees.length > 0">
                            <article
                                v-for="employee in employees"
                                :key="employee.id"
                                class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="text-lg font-semibold text-slate-950">
                                            {{ employee.name }}
                                        </h3>
                                        <p class="mt-1 text-sm text-slate-500">
                                            {{ employee.employee_number || "-" }}
                                        </p>
                                    </div>
                                    <Tag
                                        :value="statusLabel(employee.is_active)"
                                        :severity="statusSeverity(employee.is_active)"
                                    />
                                </div>

                                <dl class="mt-4 grid gap-3 text-sm text-slate-600">
                                    <div>
                                        <dt class="font-medium text-slate-900">E-mail</dt>
                                        <dd>{{ employee.email || "-" }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-medium text-slate-900">
                                            Telefon
                                        </dt>
                                        <dd>{{ employee.phone || "-" }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-medium text-slate-900">
                                            Pozicio
                                        </dt>
                                        <dd>{{ employee.position || "-" }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-medium text-slate-900">Ceg</dt>
                                        <dd>{{ employee.company_name || "-" }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-medium text-slate-900">
                                            Letrehozva
                                        </dt>
                                        <dd>{{ formatDate(employee.created_at) }}</dd>
                                    </div>
                                </dl>

                                <div class="mt-5 flex justify-end">
                                    <RowActionMenu
                                        :items="employeeActionItems(employee)"
                                    />
                                </div>
                            </article>
                        </template>

                        <div
                            v-else
                            class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8"
                        >
                            <EmptyStatePanel
                                title="Nincs megjelenitheto alkalmazott"
                                description="A jelenlegi szurok mellett nincs talalat. Modositsd a keresest vagy hozz letre uj alkalmazottat."
                                :tags="['Employees', 'Admin CRUD']"
                            />
                        </div>
                    </div>
                </div>

                <template #footer>
                    <AdminTableSummary
                        :page="tableState.page"
                        :per-page="tableState.perPage"
                        :total="tableState.totalRecords"
                        :last-page="lastPage"
                        item-label="alkalmazott"
                    />
                </template>
            </AdminTableCard>
        </div>

        <CreateEmployeeDialog
            :visible="showCreateDialog"
            :companies="companies"
            :form="form"
            :errors="formErrors"
            :submitting="submitting"
            @update:visible="
                (value) => (value ? (showCreateDialog = value) : closeCreateDialog())
            "
            @submit="submitCreate"
        />
        <EditEmployeeDialog
            :visible="showEditDialog"
            :companies="companies"
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
