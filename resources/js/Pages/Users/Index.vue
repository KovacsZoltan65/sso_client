<script setup>
import EmptyStatePanel from "@/Components/EmptyStatePanel.vue";
import RowActionMenu from "@/Components/Admin/RowActionMenu.vue";
import PageHeader from "@/Components/PageHeader.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import UserEditDialog from "@/Pages/Users/Partials/UserEditDialog.vue";
import UserViewDialog from "@/Pages/Users/Partials/UserViewDialog.vue";
import { UserApiError, listUsers, showUser, updateUser } from "@/Services/userService";
import { Head } from "@inertiajs/vue3";
import Button from "primevue/button";
import Column from "primevue/column";
import ConfirmDialog from "primevue/confirmdialog";
import DataTable from "primevue/datatable";
import InputText from "primevue/inputtext";
import Select from "primevue/select";
import Tag from "primevue/tag";
import { useToast } from "primevue/usetoast";
import { computed, onMounted, reactive, ref, watch } from "vue";

const props = defineProps({
    usersApi: { type: Object, required: true },
    permissions: { type: Object, required: true },
});

const toast = useToast();

const users = ref([]);
const loading = ref(false);
const submitting = ref(false);
const dialogLoading = ref(false);
const showViewDialog = ref(false);
const showEditDialog = ref(false);
const selectedUser = ref(null);

const filters = reactive({
    global: "",
    local_status: null,
    has_sso_link: null,
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
    { label: "Minden statusz", value: null },
    { label: "Aktiv", value: "active" },
    { label: "Inaktiv", value: "inactive" },
];

const linkOptions = [
    { label: "Minden kapcsolat", value: null },
    { label: "SSO kapcsolt", value: true },
    { label: "SSO kapcsolat nelkul", value: false },
];

const firstRecordIndex = computed(() => (tableState.page - 1) * tableState.perPage);

let searchDebounceId = null;

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
        global: filters.global || undefined,
        local_status: filters.local_status,
        has_sso_link: filters.has_sso_link,
    };
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
        handleApiError(error, "A felhasznalok betoltese sikertelen volt.");
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
        handleApiError(error, "A felhasznalo reszleteinek betoltese sikertelen volt.");
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

function closeViewDialog() {
    showViewDialog.value = false;
    selectedUser.value = null;
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
            summary: "Sikeres muvelet",
            detail: "A helyi felhasznaloi metaadatok frissultek.",
            life: 3000,
        });

        closeEditDialog();
        await loadUsers();
    } catch (error) {
        if (error instanceof UserApiError && error.status === 422) {
            Object.assign(formErrors, error.errors ?? {});
            return;
        }

        handleApiError(error, "A felhasznalo frissitese sikertelen volt.");
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

function handleApiError(error, fallbackMessage) {
    if (error instanceof UserApiError && error.status === 401) {
        const redirectTarget =
            error.meta.reauth_to || error.meta.redirect_to || route("login");
        window.location.assign(redirectTarget);
        return;
    }

    toast.add({
        severity: "error",
        summary: "Hiba tortent",
        detail: error instanceof UserApiError ? error.message : fallbackMessage,
        life: 4000,
    });
}

function formatDate(value) {
    if (!value) {
        return "-";
    }

    const date = new Date(value.replace(" ", "T"));

    return Number.isNaN(date.getTime()) ? value : date.toLocaleString("hu-HU");
}

function statusLabel(status) {
    return status === "inactive" ? "Inaktiv" : "Aktiv";
}

function statusSeverity(status) {
    return status === "inactive" ? "secondary" : "success";
}

function userActionItems(user) {
    return [
        {
            label: "Megtekintes",
            icon: "pi pi-eye",
            command: () => openViewDialog(user),
        },
        props.permissions.manage && user.can?.update
            ? {
                  label: "Szerkesztes",
                  icon: "pi pi-pencil",
                  command: () => openEditDialog(user),
              }
            : null,
    ];
}

watch(
    () => filters.local_status,
    () => {
        tableState.page = 1;
        loadUsers();
    }
);

watch(
    () => filters.has_sso_link,
    () => {
        tableState.page = 1;
        loadUsers();
    }
);

watch(
    () => filters.global,
    () => {
        if (searchDebounceId) {
            window.clearTimeout(searchDebounceId);
        }

        searchDebounceId = window.setTimeout(() => {
            tableState.page = 1;
            loadUsers();
        }, 350);
    }
);

onMounted(loadUsers);
</script>

<template>
    <Head title="Users" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader
                title="Users"
                description="SSO projection alapju felhasznalolista readonly identity mezokkel es kliens-specifikus helyi metaadatokkal."
            >
                <div v-if="dialogLoading" class="text-sm text-slate-500">
                    Reszletek betoltese...
                </div>
            </PageHeader>
        </template>

        <ConfirmDialog />

        <section class="shell-card overflow-hidden">
            <div
                class="flex flex-col gap-4 border-b border-slate-200/70 px-6 py-5 lg:flex-row lg:items-end lg:justify-between"
            >
                <div>
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400"
                    >
                        SSO projection directory
                    </p>
                    <h2 class="mt-2 text-xl font-semibold text-slate-950">
                        Felhasznalo admin lista
                    </h2>
                    <p class="mt-2 text-sm text-slate-600">
                        Az identity mezok az SSO serverrol jonnek, itt csak a helyi kliens
                        metaadatok szerkeszthetok.
                    </p>
                </div>

                <div class="grid gap-3 xl:min-w-[48rem] xl:grid-cols-[1fr_12rem_12rem]">
                    <span class="p-input-icon-left">
                        <i class="pi pi-search text-slate-400" />
                        <InputText
                            v-model="filters.global"
                            fluid
                            placeholder="Kereses ID, SSO ID, nev vagy e-mail alapjan"
                        />
                    </span>

                    <Select
                        v-model="filters.local_status"
                        :options="localStatusOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="Lokalis statusz"
                        show-clear
                    />

                    <Select
                        v-model="filters.has_sso_link"
                        :options="linkOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="SSO kapcsolat"
                        show-clear
                    />
                </div>
            </div>

            <div class="hidden lg:block">
                <DataTable
                    :value="users"
                    :loading="loading"
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
                    <template #empty>
                        <div class="px-6 py-10">
                            <EmptyStatePanel
                                title="Nincs megjelenitheto felhasznalo"
                                description="A jelenlegi szurok mellett nincs talalat. Modositsd a keresest vagy a szuresi felteteleket."
                                :tags="['Users', 'SSO projection']"
                            />
                        </div>
                    </template>

                    <!-- ID -->
                    <Column field="id" header="Local ID" sortable />

                    <!-- SSO User ID -->
                    <Column field="sso_user_id" header="SSO User ID" sortable />

                    <!-- Name -->
                    <Column field="name" header="Nev" sortable />

                    <!-- Email -->
                    <Column field="email" header="E-mail" sortable />

                    <!-- Local Status -->
                    <Column field="local_status" header="Statusz" sortable>
                        <template #body="{ data }">
                            <Tag
                                :value="statusLabel(data.local_status)"
                                :severity="statusSeverity(data.local_status)"
                            />
                        </template>
                    </Column>

                    <!-- Last Authenticated At -->
                    <Column
                        field="last_authenticated_at"
                        header="Utolso hitelesites"
                        sortable
                    >
                        <template #body="{ data }">
                            {{ formatDate(data.last_authenticated_at) }}
                        </template>
                    </Column>

                    <!-- Created At -->
                    <Column field="created_at" header="Letrehozva" sortable>
                        <template #body="{ data }">
                            {{ formatDate(data.created_at) }}
                        </template>
                    </Column>

                    <!-- Updated At -->
                    <Column field="updated_at" header="Frissitve" sortable>
                        <template #body="{ data }">
                            {{ formatDate(data.updated_at) }}
                        </template>
                    </Column>

                    <!-- Műveletek -->
                    <Column header="Muveletek" :style="{ width: '120px' }">
                        <template #body="{ data }">
                            <RowActionMenu :items="userActionItems(data)" />
                        </template>
                    </Column>
                </DataTable>
            </div>

            <div class="space-y-4 p-6 lg:hidden">
                <div
                    v-if="loading"
                    class="rounded-2xl border border-dashed border-slate-300 px-4 py-6 text-sm text-slate-500"
                >
                    Betoltes folyamatban...
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
                                    {{ user.name }}
                                </h3>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ user.email }}
                                </p>
                            </div>
                            <Tag
                                :value="statusLabel(user.local_status)"
                                :severity="statusSeverity(user.local_status)"
                            />
                        </div>

                        <dl class="mt-4 grid gap-3 text-sm text-slate-600">
                            <div>
                                <dt class="font-semibold text-slate-900">Local ID</dt>
                                <dd>{{ user.id }}</dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-slate-900">SSO user ID</dt>
                                <dd>{{ user.sso_user_id || "-" }}</dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-slate-900">
                                    Utolso hitelesites
                                </dt>
                                <dd>{{ formatDate(user.last_authenticated_at) }}</dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-slate-900">Frissitve</dt>
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
                    title="Nincs megjelenitheto felhasznalo"
                    description="A jelenlegi szurok mellett nincs talalat. Modositsd a keresest vagy a szuresi felteteleket."
                    :tags="['Users', 'SSO projection']"
                />
            </div>
        </section>

        <UserViewDialog
            :visible="showViewDialog"
            :user="selectedUser"
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
