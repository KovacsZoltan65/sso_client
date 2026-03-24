<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';

defineProps({
    canResetPassword: { type: Boolean, default: false },
    status: { type: String, default: null },
});

const form = useForm({
    email: 'superadmin@sso-client.test',
    password: 'password',
    remember: false,
});
</script>

<template>
    <Head title="Sign in" />

    <GuestLayout>
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-400">Local bootstrap auth</p>
            <h1 class="mt-3 text-3xl font-semibold text-slate-950">Sign in to the client</h1>
            <p class="mt-3 text-sm leading-7 text-slate-600">
                This login is intentionally local and replaceable. It exists to support development until centralized SSO is connected.
            </p>
        </div>

        <div v-if="status" class="mt-6 rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ status }}</div>

        <form class="mt-8 space-y-5" @submit.prevent="form.post(route('login'))">
            <div class="space-y-2">
                <label class="text-sm font-medium text-slate-700">Email</label>
                <InputText v-model="form.email" class="w-full" autofocus />
                <small class="text-red-600">{{ form.errors.email }}</small>
            </div>

            <div class="space-y-2">
                <div class="flex items-center justify-between gap-3">
                    <label class="text-sm font-medium text-slate-700">Password</label>
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-sm text-blue-600 transition hover:text-blue-700"
                    >
                        Forgot password?
                    </Link>
                </div>
                <Password v-model="form.password" :feedback="false" toggle-mask class="w-full" input-class="w-full" />
                <small class="text-red-600">{{ form.errors.password }}</small>
            </div>

            <div class="flex items-center gap-3">
                <Checkbox v-model="form.remember" binary input-id="remember" />
                <label for="remember" class="text-sm text-slate-600">Remember me</label>
            </div>

            <Button type="submit" label="Sign in" icon="pi pi-arrow-right" icon-pos="right" class="w-full" :loading="form.processing" />
        </form>

        <p class="mt-6 text-sm text-slate-500">
            Need a seeded local account?
            <span class="font-medium text-slate-700">superadmin@sso-client.test / password</span>
        </p>
    </GuestLayout>
</template>
