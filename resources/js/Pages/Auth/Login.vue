<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { trans } from 'laravel-vue-i18n';
import { Head, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import ProgressSpinner from 'primevue/progressspinner';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    loginUrl: { type: String, required: true },
    status: { type: String, default: null },
});

const page = usePage();
const toast = useToast();
const redirecting = ref(false);
const error = ref(null);
let redirectTimeoutId = null;

const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);
const shouldAutoRedirect = computed(() => !flashSuccess.value && !flashError.value);

function handleRedirectFailure(message = trans('auth.login.redirect_failed')) {
    error.value = message;
    redirecting.value = false;

    toast.add({
        severity: 'error',
        summary: trans('auth.login.redirect_failed_summary'),
        detail: message,
        life: 4000,
    });
}

function startSsoLogin() {
    error.value = null;
    redirecting.value = true;

    try {
        window.location.assign(props.loginUrl);
    } catch (_error) {
        handleRedirectFailure();
    }
}

onMounted(() => {
    if (!shouldAutoRedirect.value) {
        redirecting.value = false;
        return;
    }

    redirecting.value = true;
    redirectTimeoutId = window.setTimeout(() => {
        startSsoLogin();
    }, 600);
});

onBeforeUnmount(() => {
    if (redirectTimeoutId !== null) {
        window.clearTimeout(redirectTimeoutId);
    }
});
</script>

<template>
    <Head :title="trans('auth.login.page_title')" />

    <GuestLayout>
        <Toast position="top-right" />

        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-400">{{ trans('auth.login.title') }}</p>
            <h1 class="mt-3 text-3xl font-semibold text-slate-950">{{ trans('auth.login.redirecting_title') }}</h1>
            <p class="mt-3 text-sm leading-7 text-slate-600">
                {{ trans('auth.login.description') }}
            </p>
        </div>

        <div v-if="status" class="mt-6 rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ status }}</div>
        <div v-if="flashSuccess" class="mt-6 rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ flashSuccess }}
        </div>
        <div v-if="flashError" class="mt-6 rounded-2xl bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ flashError }}
        </div>

        <div class="mt-8 flex flex-col items-center gap-4 text-center">
            <ProgressSpinner v-if="redirecting" style="width: 2.5rem; height: 2.5rem" stroke-width="6" />

            <p class="text-sm leading-7 text-slate-500">
                {{ trans('auth.login.redirecting_progress') }}
            </p>

            <Button
                type="button"
                :label="trans('auth.login.redirect_cta')"
                icon="pi pi-sign-in"
                class="w-full"
                :disabled="redirecting"
                @click="startSsoLogin"
            />

            <Button
                v-if="error"
                type="button"
                :label="trans('common.retry')"
                icon="pi pi-refresh"
                severity="danger"
                class="w-full"
                @click="startSsoLogin"
            />

            <p v-if="error" class="w-full rounded-2xl bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ error }}
            </p>
        </div>
    </GuestLayout>
</template>
