<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});
</script>

<template>
    <Head title="Register" />

    <GuestLayout>
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-400">Development only</p>
            <h1 class="mt-3 text-3xl font-semibold text-slate-950">Create a local user</h1>
            <p class="mt-3 text-sm leading-7 text-slate-600">
                Registration remains available for bootstrap scenarios. Later, account creation can be delegated entirely to the central SSO server.
            </p>
        </div>

        <form class="mt-8 space-y-5" @submit.prevent="form.post(route('register'))">
            <div class="space-y-2">
                <label class="text-sm font-medium text-slate-700">Name</label>
                <InputText v-model="form.name" class="w-full" />
                <small class="text-red-600">{{ form.errors.name }}</small>
            </div>

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

            <Button type="submit" label="Create account" class="w-full" :loading="form.processing" />
        </form>

        <p class="mt-6 text-sm text-slate-500">
            Already have an account?
            <Link :href="route('login')" class="font-medium text-blue-600 hover:text-blue-700">Sign in</Link>
        </p>
    </GuestLayout>
</template>
