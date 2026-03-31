<script setup>
import PageHeader from '@/Components/PageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    status: { type: Object, required: true },
    reachability: { type: Object, required: true },
    capabilities: { type: Array, required: true },
});
</script>

<template>
    <Head title="SSO Status" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader
                title="SSO Status"
                description="Ez az oldal a jelenlegi, mar mukodo SSO redirect, callback es session flow konfiguracios allapotat mutatja."
            />
        </template>

        <div class="grid gap-6 xl:grid-cols-[0.85fr_1.15fr]">
            <section class="shell-card p-6">
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">Current state</p>
                <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ status.mode }}</h2>
                <p class="mt-3 text-sm leading-7 text-slate-600">{{ status.message }}</p>

                <div
                    class="mt-6 rounded-3xl px-5 py-4 text-sm"
                    :class="reachability.status === 'maintenance'
                        ? 'border border-amber-200 bg-amber-50 text-amber-900'
                        : reachability.status === 'unreachable'
                            ? 'border border-red-200 bg-red-50 text-red-800'
                            : 'border border-emerald-200 bg-emerald-50 text-emerald-800'"
                >
                    <p class="font-semibold">SSO reachability</p>
                    <p class="mt-1 uppercase tracking-[0.18em]">{{ reachability.status }}</p>
                    <p v-if="reachability.reason" class="mt-2 text-sm normal-case tracking-normal">{{ reachability.reason }}</p>
                    <p v-if="reachability.retryAfter" class="mt-2 text-xs uppercase tracking-[0.18em]">
                        Retry-After: {{ reachability.retryAfter }}
                    </p>
                </div>

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
                    <div>
                        <dt class="font-semibold text-slate-900">Authorize endpoint</dt>
                        <dd class="mt-1 break-all text-slate-600">{{ status.authorizeEndpoint || 'Not configured' }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-900">Token endpoint</dt>
                        <dd class="mt-1 break-all text-slate-600">{{ status.tokenEndpoint || 'Not configured' }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-900">Userinfo endpoint</dt>
                        <dd class="mt-1 break-all text-slate-600">{{ status.userinfoEndpoint || 'Not configured' }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-900">Redirect URI</dt>
                        <dd class="mt-1 break-all text-slate-600">{{ status.redirectUri || 'Not configured' }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-900">Scopes</dt>
                        <dd class="mt-1 text-slate-600">{{ status.scopes?.join(', ') || 'Not configured' }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-900">Reachability reason</dt>
                        <dd class="mt-1 text-slate-600">{{ reachability.reason || 'Not available' }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-900">HTTP status</dt>
                        <dd class="mt-1 text-slate-600">{{ reachability.httpStatus ?? 'Not available' }}</dd>
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
