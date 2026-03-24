<script setup>
import PageHeader from '@/Components/PageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    status: { type: Object, required: true },
    capabilities: { type: Array, required: true },
});
</script>

<template>
    <Head title="SSO Status" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader
                title="SSO Status"
                description="This is the explicit handoff point for future redirect and callback integration with the central sso_server."
            />
        </template>

        <div class="grid gap-6 xl:grid-cols-[0.85fr_1.15fr]">
            <section class="shell-card p-6">
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">Current state</p>
                <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ status.mode }}</h2>
                <p class="mt-3 text-sm leading-7 text-slate-600">{{ status.message }}</p>

                <dl class="mt-6 space-y-4 text-sm">
                    <div>
                        <dt class="font-semibold text-slate-900">Configured</dt>
                        <dd class="mt-1 text-slate-600">{{ status.configured ? 'Yes' : 'No' }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-900">Server base URL</dt>
                        <dd class="mt-1 text-slate-600">{{ status.serverBaseUrl || 'Not configured' }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-900">Local auth enabled</dt>
                        <dd class="mt-1 text-slate-600">{{ status.localAuthEnabled ? 'Yes' : 'No' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="shell-card p-6">
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">Planned integration contract</p>
                <div class="mt-5 grid gap-4">
                    <div
                        v-for="capability in capabilities"
                        :key="capability"
                        class="rounded-3xl border border-slate-200/70 bg-slate-50 px-5 py-4"
                    >
                        <p class="font-semibold text-slate-900">{{ capability }}</p>
                    </div>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
