<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';

defineProps({
    status: { type: String, default: null },
});

const form = useForm({});
</script>

<template>
    <Head title="Verify email" />

    <GuestLayout>
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-400">Email verification</p>
            <h1 class="mt-3 text-3xl font-semibold text-slate-950">Verify your email address</h1>
            <p class="mt-3 text-sm leading-7 text-slate-600">
                Email verification remains available for local development accounts and can be revisited once SSO becomes the primary identity source.
            </p>
        </div>

        <div
            v-if="status === 'verification-link-sent'"
            class="mt-6 rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
        >
            A fresh verification link has been sent to your email address.
        </div>

        <div class="mt-8 space-y-4">
            <Button label="Resend verification email" class="w-full" :loading="form.processing" @click="form.post(route('verification.send'))" />

            <Link
                :href="route('logout')"
                method="post"
                as="button"
                class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
            >
                Sign out
            </Link>
        </div>
    </GuestLayout>
</template>
