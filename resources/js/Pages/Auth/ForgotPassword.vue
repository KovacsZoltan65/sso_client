<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';

defineProps({
    status: { type: String, default: null },
});

const form = useForm({
    email: '',
});
</script>

<template>
    <Head title="Forgot password" />

    <GuestLayout>
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-400">Password recovery</p>
            <h1 class="mt-3 text-3xl font-semibold text-slate-950">Reset local access</h1>
        </div>

        <p class="mt-4 text-sm leading-7 text-slate-600">
            This is only for the local fallback auth layer. Once SSO is active, the upstream identity flow can take over recovery.
        </p>

        <div v-if="status" class="mt-6 rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ status }}</div>

        <form class="mt-8 space-y-5" @submit.prevent="form.post(route('password.email'))">
            <div class="space-y-2">
                <label class="text-sm font-medium text-slate-700">Email</label>
                <InputText v-model="form.email" class="w-full" />
                <small class="text-red-600">{{ form.errors.email }}</small>
            </div>

            <Button type="submit" label="Email reset link" class="w-full" :loading="form.processing" />
        </form>
    </GuestLayout>
</template>
