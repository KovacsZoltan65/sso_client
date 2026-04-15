<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import { trans } from 'laravel-vue-i18n';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});
</script>

<template>
    <Head :title="trans('auth.register_page.page_title')" />

    <GuestLayout>
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-400">{{ trans('auth.register_page.eyebrow') }}</p>
            <h1 class="mt-3 text-3xl font-semibold text-slate-950">{{ trans('auth.register_page.title') }}</h1>
            <p class="mt-3 text-sm leading-7 text-slate-600">
                {{ trans('auth.register_page.description') }}
            </p>
        </div>

        <form class="mt-8 space-y-5" @submit.prevent="form.post(route('register'))">
            <div class="space-y-2">
                <label class="text-sm font-medium text-slate-700">{{ trans('table.columns.name') }}</label>
                <InputText v-model="form.name" class="w-full" />
                <small class="text-red-600">{{ form.errors.name }}</small>
            </div>

            <div class="space-y-2">
                <label class="text-sm font-medium text-slate-700">{{ trans('auth.email') }}</label>
                <InputText v-model="form.email" class="w-full" />
                <small class="text-red-600">{{ form.errors.email }}</small>
            </div>

            <div class="space-y-2">
                <label class="text-sm font-medium text-slate-700">{{ trans('auth.password') }}</label>
                <Password v-model="form.password" toggle-mask class="w-full" input-class="w-full" />
                <small class="text-red-600">{{ form.errors.password }}</small>
            </div>

            <div class="space-y-2">
                <label class="text-sm font-medium text-slate-700">{{ trans('profile.confirm_password') }}</label>
                <Password v-model="form.password_confirmation" :feedback="false" toggle-mask class="w-full" input-class="w-full" />
            </div>

            <Button type="submit" :label="trans('auth.register_page.submit')" class="w-full" :loading="form.processing" />
        </form>

        <p class="mt-6 text-sm text-slate-500">
            {{ trans('auth.register_page.already_have_account') }}
            <Link :href="route('login')" class="font-medium text-blue-600 hover:text-blue-700">{{ trans('auth.sign_in') }}</Link>
        </p>
    </GuestLayout>
</template>
