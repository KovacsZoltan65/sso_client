<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import { trans } from 'laravel-vue-i18n';

defineProps({
    status: { type: String, default: null },
});

const form = useForm({});
</script>

<template>
    <Head :title="trans('auth.verify_email.page_title')" />

    <GuestLayout>
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-400">{{ trans('auth.verify_email.eyebrow') }}</p>
            <h1 class="mt-3 text-3xl font-semibold text-slate-950">{{ trans('auth.verify_email.title') }}</h1>
            <p class="mt-3 text-sm leading-7 text-slate-600">
                {{ trans('auth.verify_email.description') }}
            </p>
        </div>

        <div
            v-if="status === 'verification-link-sent'"
            class="mt-6 rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
        >
            {{ trans('auth.verify_email.link_sent') }}
        </div>

        <div class="mt-8 space-y-4">
            <Button :label="trans('auth.verify_email.resend_cta')" class="w-full" :loading="form.processing" @click="form.post(route('verification.send'))" />

            <Link
                :href="route('logout')"
                method="post"
                as="button"
                class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
            >
                {{ trans('common.logout') }}
            </Link>
        </div>
    </GuestLayout>
</template>
