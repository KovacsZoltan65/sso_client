<script setup>
import PageHeader from '@/Components/PageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    status: { type: Object, required: true },
    capabilities: { type: Array, required: true },
});

const connectionReady = computed(() => Boolean(
    props.status.configured
    && props.status.authorizeEndpoint
    && props.status.tokenEndpoint
    && props.status.redirectUri,
));

const localFallbackEnabled = computed(() => props.status.localAuthEnabled === true);

const healthTone = computed(() => (connectionReady.value ? 'emerald' : 'amber'));

const healthBadge = computed(() => (connectionReady.value ? 'Ready to use' : 'Needs attention'));

const healthHeadline = computed(() => (connectionReady.value
    ? 'Users can continue to the central sign-in flow without extra setup on this client.'
    : 'This client still needs SSO setup or review before the connection can be trusted end to end.'));

const healthChecks = computed(() => [
    {
        label: 'Central sign-in route',
        value: props.status.authorizeEndpoint ? 'Available' : 'Missing',
        detail: props.status.authorizeEndpoint
            ? 'The client knows where to send users for sign-in.'
            : 'The client does not yet have a valid authorize endpoint.',
        ok: Boolean(props.status.authorizeEndpoint),
    },
    {
        label: 'Token exchange',
        value: props.status.tokenEndpoint ? 'Available' : 'Missing',
        detail: props.status.tokenEndpoint
            ? 'The callback flow can exchange the authorization result for tokens.'
            : 'The token endpoint is missing, so sign-in completion is at risk.',
        ok: Boolean(props.status.tokenEndpoint),
    },
    {
        label: 'Callback target',
        value: props.status.redirectUri ? 'Configured' : 'Missing',
        detail: props.status.redirectUri
            ? 'The SSO server has a redirect target for this client flow.'
            : 'No redirect URI is configured for the SSO callback.',
        ok: Boolean(props.status.redirectUri),
    },
    {
        label: 'Local fallback access',
        value: localFallbackEnabled.value ? 'Enabled' : 'Disabled',
        detail: localFallbackEnabled.value
            ? 'Local sign-in remains available if the central flow is temporarily unavailable.'
            : 'This client relies fully on the central SSO path.',
        ok: localFallbackEnabled.value,
    },
]);

const nextSteps = computed(() => {
    if (connectionReady.value) {
        return [
            'Use My Account to review what this app knows about your signed-in identity.',
            'Open Profile if you need to confirm self-service account details.',
            'Only visit the technical details below when support or integration review needs it.',
        ];
    }

    return [
        'Ask an administrator to verify the SSO server base URL and endpoints shown below.',
        'Keep local access available until the redirect and token exchange are confirmed.',
        'Retry the sign-in flow only after the missing configuration items are fixed.',
    ];
});

const technicalDetails = computed(() => [
    { label: 'Mode', value: props.status.mode || 'Unknown' },
    { label: 'Configured', value: props.status.configured ? 'Yes' : 'No' },
    { label: 'Server base URL', value: props.status.serverBaseUrl || 'Not configured' },
    { label: 'Authorize endpoint', value: props.status.authorizeEndpoint || 'Not configured' },
    { label: 'Token endpoint', value: props.status.tokenEndpoint || 'Not configured' },
    { label: 'Userinfo endpoint', value: props.status.userinfoEndpoint || 'Not configured' },
    { label: 'Redirect URI', value: props.status.redirectUri || 'Not configured' },
    { label: 'Scopes', value: props.status.scopes?.join(', ') || 'Not configured' },
]);
</script>

<template>
    <Head title="Connection Health" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader
                title="Connection Health"
                description="At a glance, this page shows whether the shared sign-in connection is ready, what it means for users, and what to do next."
            />
        </template>

        <div class="min-h-0 flex-1 space-y-6 overflow-y-auto pr-1">
            <section class="shell-card p-6">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                    <div class="max-w-3xl">
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">Current connection state</p>
                        <div class="mt-3 flex flex-wrap items-center gap-3">
                            <h2 class="text-2xl font-semibold text-slate-950">{{ healthBadge }}</h2>
                            <span
                                class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold"
                                :class="healthTone === 'emerald' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                            >
                                {{ status.mode }}
                            </span>
                        </div>
                        <p class="mt-3 text-sm leading-7 text-slate-600">{{ healthHeadline }}</p>
                        <p class="mt-3 text-sm leading-7 text-slate-500">{{ status.message }}</p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 lg:w-[22rem] lg:grid-cols-1">
                        <Link
                            :href="route('account.show')"
                            class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm transition hover:border-slate-300 hover:bg-white"
                        >
                            <div class="font-semibold text-slate-900">Open my account</div>
                            <p class="mt-1 text-slate-600">Check the identity and role data this app currently exposes to you.</p>
                        </Link>

                        <Link
                            :href="route('profile.edit')"
                            class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm transition hover:border-slate-300 hover:bg-white"
                        >
                            <div class="font-semibold text-slate-900">Review profile settings</div>
                            <p class="mt-1 text-slate-600">Use the self-service profile surface if you need to validate user-facing account details.</p>
                        </Link>
                    </div>
                </div>
            </section>

            <div class="grid gap-6 xl:grid-cols-[1.05fr_0.95fr]">
                <section class="shell-card p-6">
                    <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">What is working</p>
                    <div class="mt-5 grid gap-4">
                        <div
                            v-for="check in healthChecks"
                            :key="check.label"
                            class="rounded-3xl border px-5 py-4"
                            :class="check.ok ? 'border-emerald-200 bg-emerald-50/70' : 'border-amber-200 bg-amber-50/80'"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <p class="font-semibold text-slate-900">{{ check.label }}</p>
                                <span
                                    class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold"
                                    :class="check.ok ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                                >
                                    {{ check.value }}
                                </span>
                            </div>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ check.detail }}</p>
                        </div>
                    </div>
                </section>

                <section class="shell-card p-6">
                    <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">What you can do next</p>
                    <div class="mt-5 space-y-3">
                        <div
                            v-for="step in nextSteps"
                            :key="step"
                            class="rounded-3xl border border-slate-200/70 bg-slate-50 px-5 py-4"
                        >
                            <p class="text-sm leading-6 text-slate-700">{{ step }}</p>
                        </div>
                    </div>

                    <div class="mt-6 rounded-3xl border border-slate-200/70 bg-white/80 px-5 py-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">Supported flow today</p>
                        <div class="mt-4 grid gap-3">
                            <div
                                v-for="capability in capabilities"
                                :key="capability"
                                class="rounded-2xl border border-slate-200/70 bg-slate-50 px-4 py-3"
                            >
                                <p class="text-sm font-medium text-slate-900">{{ capability }}</p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <section class="shell-card p-6">
                <details>
                    <summary class="cursor-pointer list-none text-sm font-semibold text-slate-900">
                        Technical details for support and integration review
                    </summary>
                    <p class="mt-3 text-sm leading-6 text-slate-600">
                        These fields are still available for debugging, but they now sit behind a secondary disclosure so the page stays readable for normal users.
                    </p>

                    <dl class="mt-5 grid gap-4 md:grid-cols-2">
                        <div
                            v-for="detail in technicalDetails"
                            :key="detail.label"
                            class="rounded-2xl border border-slate-200/70 bg-slate-50 px-4 py-4"
                        >
                            <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ detail.label }}</dt>
                            <dd class="mt-2 break-all text-sm leading-6 text-slate-700">{{ detail.value }}</dd>
                        </div>
                    </dl>
                </details>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
