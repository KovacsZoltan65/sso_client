<script setup>
import PageHeader from '@/Components/PageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
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

const healthBadge = computed(() => (connectionReady.value
    ? trans('sso_status.health_ready')
    : trans('sso_status.health_attention')));

const healthHeadline = computed(() => (connectionReady.value
    ? trans('sso_status.health_ready_description')
    : trans('sso_status.health_attention_description')));

const healthChecks = computed(() => [
    {
        label: trans('sso_status.check_sign_in_route_label'),
        value: props.status.authorizeEndpoint ? trans('sso_status.available') : trans('sso_status.missing'),
        detail: props.status.authorizeEndpoint
            ? trans('sso_status.check_sign_in_route_ok')
            : trans('sso_status.check_sign_in_route_missing'),
        ok: Boolean(props.status.authorizeEndpoint),
    },
    {
        label: trans('sso_status.check_token_exchange_label'),
        value: props.status.tokenEndpoint ? trans('sso_status.available') : trans('sso_status.missing'),
        detail: props.status.tokenEndpoint
            ? trans('sso_status.check_token_exchange_ok')
            : trans('sso_status.check_token_exchange_missing'),
        ok: Boolean(props.status.tokenEndpoint),
    },
    {
        label: trans('sso_status.check_callback_target_label'),
        value: props.status.redirectUri ? trans('sso_status.configured') : trans('sso_status.missing'),
        detail: props.status.redirectUri
            ? trans('sso_status.check_callback_target_ok')
            : trans('sso_status.check_callback_target_missing'),
        ok: Boolean(props.status.redirectUri),
    },
    {
        label: trans('sso_status.check_local_fallback_label'),
        value: localFallbackEnabled.value ? trans('sso_status.enabled') : trans('sso_status.disabled'),
        detail: localFallbackEnabled.value
            ? trans('sso_status.check_local_fallback_ok')
            : trans('sso_status.check_local_fallback_missing'),
        ok: localFallbackEnabled.value,
    },
]);

const nextSteps = computed(() => {
    if (connectionReady.value) {
        return [
            trans('sso_status.next_step_ready_account'),
            trans('sso_status.next_step_ready_profile'),
            trans('sso_status.next_step_ready_details'),
        ];
    }

    return [
        trans('sso_status.next_step_setup_admin'),
        trans('sso_status.next_step_setup_fallback'),
        trans('sso_status.next_step_setup_retry'),
    ];
});

const technicalDetails = computed(() => [
    { label: trans('sso_status.detail_mode'), value: props.status.mode || trans('sso_status.unknown') },
    { label: trans('sso_status.detail_configured'), value: props.status.configured ? trans('sso_status.yes') : trans('sso_status.no') },
    { label: trans('sso_status.detail_server_base_url'), value: props.status.serverBaseUrl || trans('sso_status.not_configured') },
    { label: trans('sso_status.detail_authorize_endpoint'), value: props.status.authorizeEndpoint || trans('sso_status.not_configured') },
    { label: trans('sso_status.detail_token_endpoint'), value: props.status.tokenEndpoint || trans('sso_status.not_configured') },
    { label: trans('sso_status.detail_userinfo_endpoint'), value: props.status.userinfoEndpoint || trans('sso_status.not_configured') },
    { label: trans('sso_status.detail_redirect_uri'), value: props.status.redirectUri || trans('sso_status.not_configured') },
    { label: trans('sso_status.detail_scopes'), value: props.status.scopes?.join(', ') || trans('sso_status.not_configured') },
]);
</script>

<template>
    <Head :title="trans('navigation.connection_health.label')" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader
                :title="trans('navigation.connection_health.label')"
                :description="trans('sso_status.description')"
            />
        </template>

        <div class="min-h-0 flex-1 space-y-6 overflow-y-auto pr-1">
            <section class="shell-card p-6">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                    <div class="max-w-3xl">
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">
                            {{ trans('sso_status.current_connection_state') }}
                        </p>
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
                            <div class="font-semibold text-slate-900">{{ trans('sso_status.open_my_account') }}</div>
                            <p class="mt-1 text-slate-600">{{ trans('sso_status.open_my_account_description') }}</p>
                        </Link>

                        <Link
                            :href="route('profile.edit')"
                            class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm transition hover:border-slate-300 hover:bg-white"
                        >
                            <div class="font-semibold text-slate-900">{{ trans('sso_status.review_profile_settings') }}</div>
                            <p class="mt-1 text-slate-600">{{ trans('sso_status.review_profile_settings_description') }}</p>
                        </Link>
                    </div>
                </div>
            </section>

            <div class="grid gap-6 xl:grid-cols-[1.05fr_0.95fr]">
                <section class="shell-card p-6">
                    <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">
                        {{ trans('sso_status.what_is_working') }}
                    </p>
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
                    <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">
                        {{ trans('sso_status.what_you_can_do_next') }}
                    </p>
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
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">
                            {{ trans('sso_status.supported_flow_today') }}
                        </p>
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
                        {{ trans('sso_status.technical_details_title') }}
                    </summary>
                    <p class="mt-3 text-sm leading-6 text-slate-600">
                        {{ trans('sso_status.technical_details_description') }}
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
