<script setup>
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import { trans } from 'laravel-vue-i18n';

defineProps({
    form: { type: Object, required: true },
    loading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    apiAvailable: { type: Boolean, default: true },
    submitLabel: { type: String, default: null },
});

const emit = defineEmits(['submit']);
</script>

<template>
    <section class="shell-card p-6">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-950">{{ trans('profile.identity_title') }}</h2>
                <p class="mt-2 text-sm text-slate-600">
                    {{ trans('profile.identity_description') }}
                </p>
            </div>

            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                {{ trans('profile.source_of_truth') }}
            </span>
        </div>

        <Message v-if="!apiAvailable" severity="warn" class="mt-6">
            {{ trans('profile.api_unavailable') }}
        </Message>

        <form class="mt-6 grid gap-4 md:grid-cols-2" @submit.prevent="emit('submit')">
            <div class="space-y-2">
                <label class="text-sm font-medium text-slate-700" for="profile-name">{{ trans('profile.display_name') }}</label>
                <InputText id="profile-name" v-model="form.name" class="w-full" :disabled="disabled || !apiAvailable" />
                <small v-if="form.errors.name" class="text-red-600">{{ form.errors.name }}</small>
            </div>

            <div class="space-y-2">
                <label class="text-sm font-medium text-slate-700" for="profile-email">{{ trans('auth.email') }}</label>
                <InputText id="profile-email" :model-value="form.email" class="w-full" disabled readonly />
                <small class="text-slate-500">
                    {{ trans('profile.email_readonly_help') }}
                </small>
                <small v-if="form.errors.email" class="text-red-600">{{ form.errors.email }}</small>
            </div>

            <div class="md:col-span-2 flex justify-end">
                <Button
                    :label="submitLabel || trans('profile.save_profile')"
                    icon="pi pi-save"
                    type="submit"
                    :disabled="disabled || !apiAvailable"
                    :loading="loading"
                />
            </div>
        </form>
    </section>
</template>
