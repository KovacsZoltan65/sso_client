<script setup>
import Dialog from 'primevue/dialog';
import { trans } from 'laravel-vue-i18n';
import Tag from 'primevue/tag';
import { computed } from 'vue';

const props = defineProps({
    visible: { type: Boolean, default: false },
    user: { type: Object, default: null },
    locale: { type: String, default: 'hu' },
});

const emit = defineEmits(['update:visible']);

function closeDialog() {
    emit('update:visible', false);
}

function formatDate(value) {
    if (!value) {
        return '-';
    }

    const date = new Date(value.replace(' ', 'T'));

    return Number.isNaN(date.getTime()) ? value : date.toLocaleString(props.locale);
}

function statusSeverity(status) {
    return status === 'active' ? 'success' : 'secondary';
}

const localStatusLabel = computed(() =>
    props.user?.local_status === 'inactive'
        ? trans('common.inactive')
        : trans('common.active')
);
</script>

<template>
    <Dialog
        :visible="visible"
        modal
        dismissable-mask
        :style="{ width: 'min(46rem, 95vw)' }"
        :header="trans('users.view_dialog_title')"
        @update:visible="emit('update:visible', $event)"
        @hide="closeDialog"
    >
        <div v-if="user" class="space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">
                    {{ trans('users.identity_fields') }}
                </p>
                <dl class="mt-4 grid gap-4 md:grid-cols-2">
                    <div>
                        <dt class="text-sm font-semibold text-slate-900">{{ trans('users.sso_user_id') }}</dt>
                        <dd class="mt-1 text-sm text-slate-600">{{ user.sso_user_id || '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-semibold text-slate-900">{{ trans('table.name') }}</dt>
                        <dd class="mt-1 text-sm text-slate-600">{{ user.name || '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-semibold text-slate-900">{{ trans('table.email') }}</dt>
                        <dd class="mt-1 text-sm text-slate-600">{{ user.email || '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-semibold text-slate-900">{{ trans('users.last_authenticated_at') }}</dt>
                        <dd class="mt-1 text-sm text-slate-600">{{ formatDate(user.last_authenticated_at) }}</dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">
                    {{ trans('users.local_client_metadata') }}
                </p>
                <dl class="mt-4 grid gap-4 md:grid-cols-2">
                    <div>
                        <dt class="text-sm font-semibold text-slate-900">{{ trans('users.local_status') }}</dt>
                        <dd class="mt-2">
                            <Tag :value="localStatusLabel" :severity="statusSeverity(user.local_status)" />
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-semibold text-slate-900">{{ trans('users.local_id') }}</dt>
                        <dd class="mt-1 text-sm text-slate-600">{{ user.id }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-semibold text-slate-900">{{ trans('table.created_at') }}</dt>
                        <dd class="mt-1 text-sm text-slate-600">{{ formatDate(user.created_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-semibold text-slate-900">{{ trans('users.updated_at') }}</dt>
                        <dd class="mt-1 text-sm text-slate-600">{{ formatDate(user.updated_at) }}</dd>
                    </div>
                    <div class="md:col-span-2">
                        <dt class="text-sm font-semibold text-slate-900">{{ trans('users.notes') }}</dt>
                        <dd class="mt-1 whitespace-pre-line text-sm text-slate-600">{{ user.notes || trans('users.no_notes') }}</dd>
                    </div>
                </dl>
            </section>
        </div>
    </Dialog>
</template>
