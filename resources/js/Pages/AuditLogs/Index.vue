<script setup>
import EmptyStatePanel from '@/Components/EmptyStatePanel.vue';
import AdminTableCard from '@/Components/Admin/AdminTableCard.vue';
import BaseDataTable from '@/Components/Admin/BaseDataTable.vue';
import AdminTableSummary from '@/Components/Admin/AdminTableSummary.vue';
import AdminTableToolbar from '@/Components/Admin/AdminTableToolbar.vue';
import RowActionMenu from '@/Components/Admin/RowActionMenu.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { trans } from 'laravel-vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useAdminTableState } from '@/Composables/useAdminTableState';
import AuditLogViewDialog from '@/Pages/AuditLogs/Partials/AuditLogViewDialog.vue';
import { AuditLogApiError, fetchAuditLogs, showAuditLog } from '@/Services/auditLogService';
import { Head, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Column from 'primevue/column';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    auditLogsApi: { type: Object, required: true },
    permissions: { type: Object, required: true },
});

const toast = useToast();
const page = usePage();

const items = ref([]);
const loading = ref(false);
const detailLoading = ref(false);
const showDetailDialog = ref(false);
const selectedAuditLog = ref(null);

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
    initialSortField: 'created_at',
    initialSortOrder: -1,
    initialFilters: {
        global: '',
    },
});

let searchDebounceId = null;
const currentLocale = computed(() => page.props.locale?.current ?? 'hu');

function getRequestParams() {
    return buildFetchParams({
        filters: {
            global: filters.global || undefined,
        },
    });
}

async function loadAuditLogs() {
    loading.value = true;

    try {
        const envelope = await fetchAuditLogs(props.auditLogsApi, getRequestParams());
        items.value = envelope.data.items ?? [];
        applyMeta(envelope.meta.pagination ?? {});
    } catch (error) {
        handleApiError(error, trans('audit_logs.loading_error'));
    } finally {
        loading.value = false;
    }
}

async function openDetailDialog(entry) {
    detailLoading.value = true;
    selectedAuditLog.value = null;

    try {
        const envelope = await showAuditLog(props.auditLogsApi, entry.id);
        selectedAuditLog.value = envelope.data.audit_log ?? null;
        showDetailDialog.value = true;
    } catch (error) {
        handleApiError(error, trans('audit_logs.details_loading_error'));
    } finally {
        detailLoading.value = false;
    }
}

function closeDetailDialog() {
    showDetailDialog.value = false;
    selectedAuditLog.value = null;
}

function setDetailDialogVisible(visible) {
    if (visible) {
        showDetailDialog.value = true;
        return;
    }

    closeDetailDialog();
}

async function refreshAuditLogs() {
    await loadAuditLogs();

    toast.add({
        severity: 'success',
        summary: trans('common.success'),
        detail: trans('audit_logs.refresh_detail'),
        life: 2500,
    });
}

function handleTablePage(event) {
    setPageFromEvent(event);
    loadAuditLogs();
}

function handleTableSort(event) {
    setSortFromEvent(event, 'created_at');
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
        summary: trans('common.error'),
        detail: error instanceof AuditLogApiError ? error.message : fallbackMessage,
        life: 4000,
    });
}

function formatDate(value) {
    if (!value) {
        return '-';
    }

    const date = new Date(value.replace(' ', 'T'));

    return Number.isNaN(date.getTime()) ? value : date.toLocaleString(currentLocale.value);
}

function eventToken(value) {
    if (!value) {
        return trans('audit_logs.unknown_event');
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
    return entry.causer?.name || entry.causer?.email || entry.causer?.display || trans('audit_logs.system_user');
}

function auditLogActionItems(entry) {
    return [
        {
            label: trans('common.details'),
            icon: 'pi pi-eye',
            isPrimary: true,
            command: () => openDetailDialog(entry),
        },
    ];
}

watch(
    () => filters.global,
    () => {
        if (searchDebounceId) {
            window.clearTimeout(searchDebounceId);
        }

        searchDebounceId = window.setTimeout(() => {
            resetPagination();
            loadAuditLogs();
        }, 350);
    }
);

onMounted(loadAuditLogs);
</script>

<template>
    <Head :title="trans('navigation.audit_logs.label')" />

    <AuthenticatedLayout>
        <div class="admin-table-page">
            <PageHeader
                :title="trans('navigation.audit_logs.label')"
                :description="trans('audit_logs.description')"
            />

            <AdminTableCard>
                <div class="admin-table-shell">
                    <div class="hidden min-h-0 flex-1 lg:flex">
                        <BaseDataTable
                            :value="items"
                            :loading="loading"
                            :loading-message="trans('audit_logs.loading_message')"
                            :empty-message="trans('audit_logs.loading_empty')"
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
                                    :search-value="filters.global"
                                    :search-placeholder="trans('audit_logs.search_placeholder')"
                                    :canCreate="false"
                                    :canBulkDelete="false"
                                    :selectedCount="0"
                                    :selectableCount="0"
                                    :busy="loading || detailLoading"
                                    @update:searchValue="filters.global = $event"
                                    @refresh="refreshAuditLogs"
                                />
                            </template>

                            <template #empty>
                                <div class="px-6 py-10">
                                    <EmptyStatePanel
                                        :title="trans('audit_logs.loading_empty')"
                                        :description="trans('audit_logs.empty_description')"
                                        :tags="[trans('navigation.audit_logs.label'), trans('audit_logs.read_only')]"
                                    />
                                </div>
                            </template>

                            <Column field="id" :header="trans('table.columns.id')" sortable />
                            <Column field="event" :header="trans('audit_logs.event')" sortable>
                                <template #body="{ data }">
                                    <Tag :value="eventToken(data.event)" :severity="eventSeverity(data.event)" />
                                </template>
                            </Column>
                            <Column field="description" :header="trans('audit_logs.description_label')" sortable>
                                <template #body="{ data }">
                                    <span class="block max-w-md truncate" :title="data.description">
                                        {{ data.description || '-' }}
                                    </span>
                                </template>
                            </Column>
                            <Column field="subject_type" :header="trans('audit_logs.subject_type')" sortable>
                                <template #body="{ data }">
                                    {{ data.subject_type || '-' }}
                                </template>
                            </Column>
                            <Column field="subject_id" :header="trans('audit_logs.subject_id')" sortable>
                                <template #body="{ data }">
                                    {{ data.subject_id ?? '-' }}
                                </template>
                            </Column>
                            <Column :header="trans('common.user')">
                                <template #body="{ data }">
                                    {{ userLabel(data) }}
                                </template>
                            </Column>
                            <Column field="created_at" :header="trans('table.columns.created_at')" sortable>
                                <template #body="{ data }">
                                    {{ formatDate(data.created_at) }}
                                </template>
                            </Column>
                            <Column :header="trans('table.columns.actions')" :style="{ width: '11rem' }">
                                <template #body="{ data }">
                                    <RowActionMenu :items="auditLogActionItems(data)" :disabled="detailLoading" />
                                </template>
                            </Column>
                        </BaseDataTable>
                    </div>

                    <div class="space-y-4 p-6 lg:hidden">
                        <div class="grid gap-3">
                            <div class="relative">
                                <i class="pi pi-search pointer-events-none absolute left-3 top-1/2 z-10 -translate-y-1/2 text-sm text-slate-400" />
                                <InputText
                                    v-model="filters.global"
                                    fluid
                                    class="h-11 w-full pl-10"
                                    :placeholder="trans('audit_logs.mobile_search_placeholder')"
                                />
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center justify-end gap-3">
                            <Button
                                :label="trans('common.refresh')"
                                icon="pi pi-refresh"
                                severity="secondary"
                                outlined
                                :loading="loading || detailLoading"
                                :disabled="loading || detailLoading"
                                @click="refreshAuditLogs"
                            />
                        </div>

                        <div
                            v-if="loading"
                            class="rounded-2xl border border-dashed border-slate-300 px-4 py-6 text-sm text-slate-500"
                        >
                            {{ trans('audit_logs.loading_short') }}
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
                                        <dt class="font-semibold text-slate-900">{{ trans('audit_logs.description_label') }}</dt>
                                        <dd>{{ entry.description || '-' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-slate-900">{{ trans('audit_logs.subject') }}</dt>
                                        <dd>{{ entry.subject_type || '-' }} #{{ entry.subject_id ?? '-' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-slate-900">{{ trans('table.columns.created_at') }}</dt>
                                        <dd>{{ formatDate(entry.created_at) }}</dd>
                                    </div>
                                </dl>

                                <div class="mt-5 flex justify-end">
                                    <Button
                                        :label="trans('common.details')"
                                        icon="pi pi-eye"
                                        severity="secondary"
                                        text
                                        :disabled="detailLoading"
                                        @click="openDetailDialog(entry)"
                                    />
                                </div>
                            </article>
                        </template>

                        <EmptyStatePanel
                            v-else
                            :title="trans('audit_logs.loading_empty')"
                            :description="trans('audit_logs.empty_description')"
                            :tags="[trans('navigation.audit_logs.label'), trans('audit_logs.read_only')]"
                        />
                    </div>
                </div>

                <template #footer>
                    <AdminTableSummary
                        :page="tableState.page"
                        :per-page="tableState.perPage"
                        :total="tableState.totalRecords"
                        :last-page="lastPage"
                        :item-label="trans('audit_logs.item_label')"
                    />
                </template>
            </AdminTableCard>
        </div>

        <AuditLogViewDialog
            :visible="showDetailDialog"
            :audit-log="selectedAuditLog"
            :loading="detailLoading"
            @update:visible="setDetailDialogVisible"
        />
    </AuthenticatedLayout>
</template>
