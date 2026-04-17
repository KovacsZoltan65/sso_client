<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import { trans } from 'laravel-vue-i18n';

defineProps({
    status: { type: String, default: null },
});

const form = useForm({
    email: '',
});
</script>

<template>
    <Head :title="trans('auth.forgot_password_page.page_title')" />

    <GuestLayout>
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-400">{{ trans('auth.forgot_password_page.eyebrow') }}</p>
            <h1 class="mt-3 text-3xl font-semibold text-slate-950">{{ trans('auth.forgot_password_page.title') }}</h1>
        </div>

        <p class="mt-4 text-sm leading-7 text-slate-600">
            {{ trans('auth.forgot_password_page.description') }}
        </p>

        <div v-if="status" class="mt-6 rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ status }}</div>

        <form class="mt-8 space-y-5" @submit.prevent="form.post(route('password.email'))">
            <div class="space-y-2">
                <label class="text-sm font-medium text-slate-700">{{ trans('auth.email') }}</label>
                <InputText v-model="form.email" class="w-full" />
                <small class="text-red-600">{{ form.errors.email }}</small>
            </div>

            <Button type="submit" :label="trans('auth.forgot_password_page.submit')" class="w-full" :loading="form.processing" />
        </form>
    </GuestLayout>
</template>
