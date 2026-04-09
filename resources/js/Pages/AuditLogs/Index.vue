<script setup>
import EmptyStatePanel from '@/Components/EmptyStatePanel.vue';
import AdminTableCard from '@/Components/Admin/AdminTableCard.vue';
import AdminTableToolbar from '@/Components/Admin/AdminTableToolbar.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { AuditLogApiError, fetchAuditLogs } from '@/Services/auditLogService';
import { Head } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { IconField, InputIcon } from 'primevue';

const props = defineProps({
    auditLogsApi: { type: Object, required: true },
    permissions: { type: Object, required: true },
});

const toast = useToast();

const items = ref([]);
const loading = ref(false);

const filters = reactive({
    global: '',
});

const lazyParams = reactive({
    page: 1,
    perPage: 10,
    total: 0,
    sortField: 'created_at',
    sortOrder: 'desc',
});

const firstRecordIndex = computed(() => (lazyParams.page - 1) * lazyParams.perPage);

let searchDebounceId = null;

function getRequestParams() {
    return {
        page: lazyParams.page,
        per_page: lazyParams.perPage,
        sort_field: lazyParams.sortField,
        sort_order: lazyParams.sortOrder,
        global: filters.global || undefined,
    };
}

async function loadAuditLogs() {
    loading.value = true;

    try {
        const envelope = await fetchAuditLogs(props.auditLogsApi, getRequestParams());
        items.value = envelope.data.items ?? [];

        const pagination = envelope.meta.pagination ?? {};
        lazyParams.total = pagination.total ?? 0;
        lazyParams.page = pagination.current_page ?? lazyParams.page;
        lazyParams.perPage = pagination.per_page ?? lazyParams.perPage;
    } catch (error) {
        handleApiError(error, 'Az audit logok betoltese sikertelen volt.');
    } finally {
        loading.value = false;
    }
}

async function refreshAuditLogs() {
    await loadAuditLogs();

    toast.add({
        severity: 'success',
        summary: 'Sikeres muvelet',
        detail: 'Az audit log lista frissult.',
        life: 2500,
    });
}

function handleTablePage(event) {
    lazyParams.page = (event.page ?? 0) + 1;
    lazyParams.perPage = event.rows ?? lazyParams.perPage;
    loadAuditLogs();
}

function handleTableSort(event) {
    lazyParams.sortField = event.sortField ?? 'created_at';
    lazyParams.sortOrder = event.sortOrder === 1 ? 'asc' : 'desc';
    lazyParams.page = 1;
    loadAuditLogs();
}

function handleApiError(error, fallbackMessage) {
    if (error instanceof AuditLogApiError && error.status === 401) {
        const redirectTarget = error.meta.reauth_to || error.meta.redirect_to || route('login');
        window.location.assign(redirectTarget);
        return;
    }

    toast.add({
        severity: 'error',
        summary: 'Hiba tortent',
        detail: error instanceof AuditLogApiError ? error.message : fallbackMessage,
        life: 4000,
    });
}

function formatDate(value) {
    if (!value) {
        return '-';
    }

    const date = new Date(value.replace(' ', 'T'));

    return Number.isNaN(date.getTime()) ? value : date.toLocaleString('hu-HU');
}

function eventToken(value) {
    if (!value) {
        return 'ismeretlen';
    }

    const token = String(value).split('.').pop() ?? String(value);

    return token.replace(/_/g, ' ');
}

function eventSeverity(value) {
    const token = String(value ?? '').split('.').pop() ?? '';

    switch (token) {
        case 'create':
        case 'created':
            return 'success';
        case 'update':
        case 'updated':
            return 'info';
        case 'delete':
        case 'deleted':
            return 'danger';
        case 'login':
        case 'logged_in':
        case 'authenticated':
            return 'warning';
        default:
            return 'secondary';
    }
}

function userLabel(entry) {
    return entry.causer?.name || entry.causer?.email || entry.causer?.display || 'System';
}

watch(
    () => filters.global,
    () => {
        if (searchDebounceId) {
            window.clearTimeout(searchDebounceId);
        }

        searchDebounceId = window.setTimeout(() => {
            lazyParams.page = 1;
            loadAuditLogs();
        }, 350);
    }
);

onMounted(loadAuditLogs);
</script>

<template>
    <Head title="Audit Logs" />

    <AuthenticatedLayout>
        <div class="admin-table-page">
            <PageHeader
                title="Audit Logs"
                description="Olvashato audit naplo szerveroldali lapozassal, keresessel es rendezesel a helyi admin es SSO folyamatok kovetesere."
            />

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
                            :rows="lazyParams.perPage"
                            :first="firstRecordIndex"
                            :total-records="lazyParams.total"
                            :sort-field="lazyParams.sortField"
                            :sort-order="lazyParams.sortOrder === 'asc' ? 1 : -1"
                            paginator-template="RowsPerPageDropdown FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink"
                            current-page-report-template="{first} - {last} / {totalRecords}"
                            :rows-per-page-options="[10, 25, 50]"
                            @page="handleTablePage"
                            @sort="handleTableSort"
                        >
                            <template #header>
                                <AdminTableToolbar
                                    :canCreate="false"
                                    :canBulkDelete="false"
                                    :selectedCount="0"
                                    :selectableCount="0"
                                    :busy="loading"
                                    @refresh="refreshAuditLogs"
                                >
                                    <template #search>
                                        <IconField class="w-full">
                                            <InputIcon class="pi pi-search text-slate-400" />
                                            <InputText
                                                v-model="filters.global"
                                                placeholder="Kereses esemeny, leiras, target vagy felhasznalo alapjan"
                                                class="w-full"
                                            />
                                        </IconField>
                                    </template>
                                </AdminTableToolbar>
                            </template>

                            <template #empty>
                                <div class="px-6 py-10">
                                    <EmptyStatePanel
                                        title="Nincs megjelenitheto audit bejegyzes"
                                        description="A jelenlegi szurok mellett nincs talalat. Modositsd a keresest vagy frissitsd a listat."
                                        :tags="['Audit Logs', 'Read only']"
                                    />
                                </div>
                            </template>

                            <Column field="id" header="ID" sortable />
                            <Column field="event" header="Event" sortable>
                                <template #body="{ data }">
                                    <Tag :value="eventToken(data.event)" :severity="eventSeverity(data.event)" />
                                </template>
                            </Column>
                            <Column field="description" header="Description" sortable>
                                <template #body="{ data }">
                                    <span class="block max-w-md truncate" :title="data.description">
                                        {{ data.description || '-' }}
                                    </span>
                                </template>
                            </Column>
                            <Column field="subject_type" header="Subject type" sortable>
                                <template #body="{ data }">
                                    {{ data.subject_type || '-' }}
                                </template>
                            </Column>
                            <Column field="subject_id" header="Subject id" sortable>
                                <template #body="{ data }">
                                    {{ data.subject_id ?? '-' }}
                                </template>
                            </Column>
                            <Column header="User">
                                <template #body="{ data }">
                                    {{ userLabel(data) }}
                                </template>
                            </Column>
                            <Column field="created_at" header="Created at" sortable>
                                <template #body="{ data }">
                                    {{ formatDate(data.created_at) }}
                                </template>
                            </Column>
                        </DataTable>
                    </div>

                    <div class="space-y-4 p-6 lg:hidden">
                        <div class="grid gap-3">
                            <div class="relative">
                                <i class="pi pi-search pointer-events-none absolute left-3 top-1/2 z-10 -translate-y-1/2 text-sm text-slate-400" />
                                <InputText
                                    v-model="filters.global"
                                    fluid
                                    class="h-11 w-full pl-10"
                                    placeholder="Kereses esemeny vagy leiras alapjan"
                                />
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center justify-end gap-3">
                            <Button
                                label="Frissites"
                                icon="pi pi-refresh"
                                severity="secondary"
                                outlined
                                :loading="loading"
                                :disabled="loading"
                                @click="refreshAuditLogs"
                            />
                        </div>

                        <div
                            v-if="loading"
                            class="rounded-2xl border border-dashed border-slate-300 px-4 py-6 text-sm text-slate-500"
                        >
                            Betoltes folyamatban...
                        </div>

                        <template v-else-if="items.length > 0">
                            <article
                                v-for="entry in items"
                                :key="entry.id"
                                class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="text-lg font-semibold text-slate-950">
                                            #{{ entry.id }}
                                        </h3>
                                        <p class="mt-1 text-sm text-slate-500">
                                            {{ userLabel(entry) }}
                                        </p>
                                    </div>
                                    <Tag :value="eventToken(entry.event)" :severity="eventSeverity(entry.event)" />
                                </div>

                                <dl class="mt-4 grid gap-3 text-sm text-slate-600">
                                    <div>
                                        <dt class="font-semibold text-slate-900">Description</dt>
                                        <dd>{{ entry.description || '-' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-slate-900">Subject</dt>
                                        <dd>{{ entry.subject_type || '-' }} #{{ entry.subject_id ?? '-' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-slate-900">Created at</dt>
                                        <dd>{{ formatDate(entry.created_at) }}</dd>
                                    </div>
                                </dl>
                            </article>
                        </template>

                        <EmptyStatePanel
                            v-else
                            title="Nincs megjelenitheto audit bejegyzes"
                            description="A jelenlegi szurok mellett nincs talalat. Modositsd a keresest vagy frissitsd a listat."
                            :tags="['Audit Logs', 'Read only']"
                        />
                    </div>
                </div>
            </AdminTableCard>
        </div>
    </AuthenticatedLayout>
</template>
