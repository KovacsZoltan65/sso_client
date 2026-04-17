<script setup>
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';
import { usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps({
    visible: {
        type: Boolean,
        default: false,
    },
    auditLog: {
        type: Object,
        default: null,
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['update:visible']);
const page = usePage();
const currentLocale = computed(() => page.props.locale?.current ?? 'hu');

// The detail dialog prefers human-readable audit payload sections when it can
// recognize common activitylog structures. We keep the raw JSON fallback so
// unfamiliar payloads remain inspectable instead of disappearing behind
// over-eager formatting rules.
const structuredPropertySections = computed(() => {
    const properties = props.auditLog?.properties;

    if (!isPlainObject(properties)) {
        return [];
    }

    const sections = [
        buildPropertySection('changes', trans('audit_logs.properties_changes'), properties.changes),
        buildPropertySection('attributes', trans('audit_logs.properties_new_values'), properties.attributes),
        buildPropertySection('new', trans('audit_logs.properties_new_state'), properties.new),
        buildPropertySection('old', trans('audit_logs.properties_previous_values'), properties.old),
    ].filter(Boolean);

    return sections;
});

const hasStructuredProperties = computed(() => structuredPropertySections.value.length > 0);

const prettyProperties = computed(() => {
    if (!props.auditLog?.properties) {
        return '{}';
    }

    return JSON.stringify(props.auditLog.properties, null, 2);
});

function isPlainObject(value) {
    return Object.prototype.toString.call(value) === '[object Object]';
}

// We intentionally keep the recognition logic conservative: only plain-object
// sections with known keys are promoted into dedicated UI blocks.
function buildPropertySection(key, label, value) {
    if (!isPlainObject(value) || Object.keys(value).length === 0) {
        return null;
    }

    return {
        key,
        label,
        rows: Object.entries(value).map(([field, fieldValue]) => ({
            field,
            value: formatPropertyValue(fieldValue),
        })),
    };
}

// Values are stringified consistently so diff-like sections and the raw JSON
// fallback tell the same story to the admin, even for nested arrays/objects.
function formatPropertyValue(value) {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    if (Array.isArray(value) || isPlainObject(value)) {
        return JSON.stringify(value, null, 2);
    }

    if (typeof value === 'boolean') {
        return value ? trans('audit_logs.boolean_true') : trans('audit_logs.boolean_false');
    }

    return String(value);
}

function hasContextValue(value) {
    return value !== null && value !== undefined && value !== '';
}

function formatDate(value) {
    if (!value) {
        return '-';
    }

    const date = new Date(String(value).replace(' ', 'T'));

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
</script>

<template>
    <Dialog
        :visible="visible"
        modal
        :header="trans('audit_logs.details_dialog_title')"
        :style="{ width: 'min(960px, 96vw)' }"
        :dismissable-mask="true"
        @update:visible="$emit('update:visible', $event)"
    >
        <div class="flex max-h-[75vh] min-h-[20rem] flex-col gap-6 overflow-y-auto pr-1">
            <div
                v-if="loading"
                class="rounded-2xl border border-dashed border-slate-300 px-4 py-6 text-sm text-slate-500"
            >
                {{ trans('audit_logs.loading_short') }}
            </div>

            <template v-else-if="auditLog">
                <section class="grid gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-5 md:grid-cols-2">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ trans('audit_logs.identifier') }}</div>
                        <div class="mt-2 text-base font-semibold text-slate-950">#{{ auditLog.id }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ trans('audit_logs.event') }}</div>
                        <div class="mt-2">
                            <Tag :value="eventToken(auditLog.event)" :severity="eventSeverity(auditLog.event)" />
                        </div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ trans('audit_logs.log_name') }}</div>
                        <div class="mt-2 break-all text-sm text-slate-700">{{ auditLog.log_name || '-' }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ trans('table.columns.created_at') }}</div>
                        <div class="mt-2 text-sm text-slate-700">{{ formatDate(auditLog.created_at) }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ trans('audit_logs.updated_at') }}</div>
                        <div class="mt-2 text-sm text-slate-700">{{ formatDate(auditLog.updated_at) }}</div>
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5">
                    <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ trans('audit_logs.description_label') }}</div>
                    <p class="mt-3 whitespace-pre-wrap break-words text-sm leading-7 text-slate-700">
                        {{ auditLog.description || '-' }}
                    </p>
                </section>

                <section class="grid gap-4 md:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5">
                        <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ trans('audit_logs.causer') }}</div>
                        <dl class="mt-4 grid gap-3 text-sm text-slate-700">
                            <div>
                                <dt class="font-semibold text-slate-900">{{ trans('table.columns.name') }}</dt>
                                <dd>{{ auditLog.causer?.name || trans('audit_logs.system_user') }}</dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-slate-900">{{ trans('table.columns.email') }}</dt>
                                <dd>{{ auditLog.causer?.email || '-' }}</dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-slate-900">ID</dt>
                                <dd>{{ auditLog.causer?.id ?? '-' }}</dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-slate-900">Type</dt>
                                <dd>{{ auditLog.causer?.type || '-' }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-5">
                        <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ trans('audit_logs.subject') }}</div>
                        <dl class="mt-4 grid gap-3 text-sm text-slate-700">
                            <div>
                                <dt class="font-semibold text-slate-900">Type</dt>
                                <dd>{{ auditLog.subject_type || '-' }}</dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-slate-900">Type (FQN)</dt>
                                <dd class="break-all">{{ auditLog.subject_type_fqn || '-' }}</dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-slate-900">ID</dt>
                                <dd>{{ auditLog.subject_id ?? '-' }}</dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-slate-900">Display</dt>
                                <dd>{{ auditLog.subject?.display || '-' }}</dd>
                            </div>
                        </dl>
                    </div>
                </section>

                <section
                    v-if="hasContextValue(auditLog.context?.ip_address)
                        || hasContextValue(auditLog.context?.user_agent)
                        || hasContextValue(auditLog.context?.route)
                        || hasContextValue(auditLog.context?.reason)
                        || hasContextValue(auditLog.context?.status)
                        || hasContextValue(auditLog.context?.result)"
                    class="rounded-2xl border border-slate-200 bg-white p-5"
                >
                    <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ trans('audit_logs.context') }}</div>
                    <dl class="mt-4 grid gap-3 text-sm text-slate-700 md:grid-cols-2">
                        <div v-if="hasContextValue(auditLog.context?.ip_address)">
                            <dt class="font-semibold text-slate-900">{{ trans('audit_logs.ip_address') }}</dt>
                            <dd>{{ auditLog.context.ip_address }}</dd>
                        </div>
                        <div v-if="hasContextValue(auditLog.context?.route)">
                            <dt class="font-semibold text-slate-900">Route</dt>
                            <dd class="break-all">{{ auditLog.context.route }}</dd>
                        </div>
                        <div v-if="hasContextValue(auditLog.context?.status)">
                            <dt class="font-semibold text-slate-900">Status</dt>
                            <dd>{{ auditLog.context.status }}</dd>
                        </div>
                        <div v-if="hasContextValue(auditLog.context?.result)">
                            <dt class="font-semibold text-slate-900">{{ trans('audit_logs.result') }}</dt>
                            <dd>{{ auditLog.context.result }}</dd>
                        </div>
                        <div v-if="hasContextValue(auditLog.context?.reason)" class="md:col-span-2">
                            <dt class="font-semibold text-slate-900">{{ trans('common.reason') }}</dt>
                            <dd class="whitespace-pre-wrap break-words">{{ auditLog.context.reason }}</dd>
                        </div>
                        <div v-if="hasContextValue(auditLog.context?.user_agent)" class="md:col-span-2">
                            <dt class="font-semibold text-slate-900">{{ trans('audit_logs.user_agent') }}</dt>
                            <dd class="whitespace-pre-wrap break-words">{{ auditLog.context.user_agent }}</dd>
                        </div>
                    </dl>
                </section>

                <section v-if="hasStructuredProperties" class="grid gap-4">
                    <section
                        v-for="section in structuredPropertySections"
                        :key="section.key"
                        class="rounded-2xl border border-slate-200 bg-white p-5"
                    >
                        <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ section.label }}</div>
                        <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200">
                            <div
                                v-for="row in section.rows"
                                :key="`${section.key}-${row.field}`"
                                class="grid gap-2 border-b border-slate-200 px-4 py-3 text-sm last:border-b-0 md:grid-cols-[minmax(180px,240px)_1fr]"
                            >
                                <div class="font-semibold text-slate-900">{{ row.field }}</div>
                                <pre class="overflow-x-auto whitespace-pre-wrap break-words text-slate-700">{{ row.value }}</pre>
                            </div>
                        </div>
                    </section>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5">
                    <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                        {{ hasStructuredProperties ? trans('audit_logs.properties_raw_json') : trans('audit_logs.properties') }}
                    </div>
                    <pre class="mt-4 overflow-x-auto rounded-2xl bg-slate-950 p-4 text-xs leading-6 text-slate-100">{{ prettyProperties }}</pre>
                </section>
            </template>

            <section
                v-else
                class="rounded-2xl border border-dashed border-slate-300 px-4 py-6 text-sm text-slate-500"
            >
                {{ trans('audit_logs.no_details') }}
            </section>
        </div>
    </Dialog>
</template>
