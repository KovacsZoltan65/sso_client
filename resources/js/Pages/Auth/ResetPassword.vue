<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';

const props = defineProps({
    email: { type: String, required: true },
    token: { type: String, required: true },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});
</script>

<template>
    <Head title="Reset password" />

    <GuestLayout>
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-400">Password reset</p>
            <h1 class="mt-3 text-3xl font-semibold text-slate-950">Choose a new password</h1>
        </div>

        <form class="mt-8 space-y-5" @submit.prevent="form.post(route('password.store'))">
            <div class="space-y-2">
                <label class="text-sm font-medium text-slate-700">Email</label>
                <InputText v-model="form.email" class="w-full" />
                <small class="text-red-600">{{ form.errors.email }}</small>
            </div>

            <div class="space-y-2">
                <label class="text-sm font-medium text-slate-700">Password</label>
                <Password v-model="form.password" toggle-mask class="w-full" input-class="w-full" />
                <small class="text-red-600">{{ form.errors.password }}</small>
            </div>

            <div class="space-y-2">
                <label class="text-sm font-medium text-slate-700">Confirm password</label>
                <Password v-model="form.password_confirmation" :feedback="false" toggle-mask class="w-full" input-class="w-full" />
            </div>

            <Button type="submit" label="Reset password" class="w-full" :loading="form.processing" />
        </form>
    </GuestLayout>
</template>
