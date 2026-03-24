<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Password from 'primevue/password';

const form = useForm({
    password: '',
});
</script>

<template>
    <Head title="Confirm password" />

    <GuestLayout>
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-400">Secure action</p>
            <h1 class="mt-3 text-3xl font-semibold text-slate-950">Confirm your password</h1>
            <p class="mt-3 text-sm leading-7 text-slate-600">
                This protects sensitive local actions until the client fully delegates authentication to SSO.
            </p>
        </div>

        <form class="mt-8 space-y-5" @submit.prevent="form.post(route('password.confirm'))">
            <div class="space-y-2">
                <label class="text-sm font-medium text-slate-700">Password</label>
                <Password v-model="form.password" :feedback="false" toggle-mask class="w-full" input-class="w-full" />
                <small class="text-red-600">{{ form.errors.password }}</small>
            </div>

            <Button type="submit" label="Confirm" class="w-full" :loading="form.processing" />
        </form>
    </GuestLayout>
</template>
