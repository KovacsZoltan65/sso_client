<script setup>
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';

const props = defineProps({
    visible: { type: Boolean, default: false },
    user: { type: Object, default: null },
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

    return Number.isNaN(date.getTime()) ? value : date.toLocaleString('hu-HU');
}

function statusSeverity(status) {
    return status === 'active' ? 'success' : 'secondary';
}
</script>

<template>
    <Dialog
        :visible="visible"
        modal
        dismissable-mask
        :style="{ width: 'min(46rem, 95vw)' }"
        header="Felhasznalo reszletei"
        @update:visible="emit('update:visible', $event)"
        @hide="closeDialog"
    >
        <div v-if="user" class="space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">
                    Identity mezok
                </p>
                <dl class="mt-4 grid gap-4 md:grid-cols-2">
                    <div>
                        <dt class="text-sm font-semibold text-slate-900">SSO user ID</dt>
                        <dd class="mt-1 text-sm text-slate-600">{{ user.sso_user_id || '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-semibold text-slate-900">Nev</dt>
                        <dd class="mt-1 text-sm text-slate-600">{{ user.name || '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-semibold text-slate-900">E-mail</dt>
                        <dd class="mt-1 text-sm text-slate-600">{{ user.email || '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-semibold text-slate-900">Utolso hitelesites</dt>
                        <dd class="mt-1 text-sm text-slate-600">{{ formatDate(user.last_authenticated_at) }}</dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">
                    Lokalis kliens metaadatok
                </p>
                <dl class="mt-4 grid gap-4 md:grid-cols-2">
                    <div>
                        <dt class="text-sm font-semibold text-slate-900">Lokalis statusz</dt>
                        <dd class="mt-2">
                            <Tag :value="user.local_status || 'unknown'" :severity="statusSeverity(user.local_status)" />
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-semibold text-slate-900">Lokalis ID</dt>
                        <dd class="mt-1 text-sm text-slate-600">{{ user.id }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-semibold text-slate-900">Letrehozva</dt>
                        <dd class="mt-1 text-sm text-slate-600">{{ formatDate(user.created_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-semibold text-slate-900">Frissitve</dt>
                        <dd class="mt-1 text-sm text-slate-600">{{ formatDate(user.updated_at) }}</dd>
                    </div>
                    <div class="md:col-span-2">
                        <dt class="text-sm font-semibold text-slate-900">Megjegyzes</dt>
                        <dd class="mt-1 whitespace-pre-line text-sm text-slate-600">{{ user.notes || 'Nincs rogzitett helyi megjegyzes.' }}</dd>
                    </div>
                </dl>
            </section>
        </div>
    </Dialog>
</template>
