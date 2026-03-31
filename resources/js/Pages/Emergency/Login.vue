<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';

const props = defineProps({
    status: { type: Object, required: true },
});

const form = useForm({
    username: '',
    password: '',
});

function submit() {
    form.post('/emergency/login');
}
</script>

<template>
    <Head title="Emergency Login" />

    <GuestLayout>
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-500">Break-glass access</p>
            <h1 class="mt-3 text-3xl font-semibold text-slate-950">Emergency operator login</h1>
            <p class="mt-3 text-sm leading-7 text-slate-600">
                This isolated login is only available while emergency mode is explicitly active. It does not reuse the normal client session or SSO flow.
            </p>
        </div>

        <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <p class="font-semibold">Current state: {{ props.status.state }}</p>
            <p class="mt-1">{{ props.status.bannerMessage || 'Limited access only.' }}</p>
        </div>

        <form class="mt-8 space-y-5" @submit.prevent="submit">
            <div class="space-y-2">
                <label class="text-sm font-medium text-slate-700">Username</label>
                <InputText v-model="form.username" class="w-full" />
                <small v-if="form.errors.username" class="text-red-600">{{ form.errors.username }}</small>
            </div>

            <div class="space-y-2">
                <label class="text-sm font-medium text-slate-700">Password</label>
                <Password v-model="form.password" input-class="w-full" class="w-full" :feedback="false" toggle-mask />
                <small v-if="form.errors.password" class="text-red-600">{{ form.errors.password }}</small>
            </div>

            <Button type="submit" label="Sign in to emergency mode" class="w-full" :loading="form.processing" />
        </form>
    </GuestLayout>
</template>
