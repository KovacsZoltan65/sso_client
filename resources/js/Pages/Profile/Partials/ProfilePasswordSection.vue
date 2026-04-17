<script setup>
import Button from 'primevue/button';
import Message from 'primevue/message';
import Password from 'primevue/password';
import { trans } from 'laravel-vue-i18n';

defineProps({
    form: { type: Object, required: true },
    loading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    apiAvailable: { type: Boolean, default: true },
});

const emit = defineEmits(['submit']);
</script>

<template>
    <section class="shell-card p-6">
        <div>
            <h2 class="text-xl font-semibold text-slate-950">{{ trans('profile.password_title') }}</h2>
            <p class="mt-2 text-sm text-slate-600">
                {{ trans('profile.password_description') }}
            </p>
        </div>

        <Message v-if="!apiAvailable" severity="warn" class="mt-6">
            {{ trans('profile.password_api_unavailable') }}
        </Message>

        <form class="mt-6 grid gap-4 lg:grid-cols-3" @submit.prevent="emit('submit')">
            <div class="space-y-2">
                <label class="text-sm font-medium text-slate-700" for="current-password">{{ trans('profile.current_password') }}</label>
                <Password
                    id="current-password"
                    v-model="form.current_password"
                    class="w-full"
                    input-class="w-full"
                    :feedback="false"
                    toggle-mask
                    :disabled="disabled || !apiAvailable"
                />
                <small v-if="form.errors.current_password" class="text-red-600">{{ form.errors.current_password }}</small>
            </div>

            <div class="space-y-2">
                <label class="text-sm font-medium text-slate-700" for="new-password">{{ trans('profile.new_password') }}</label>
                <Password
                    id="new-password"
                    v-model="form.password"
                    class="w-full"
                    input-class="w-full"
                    :feedback="false"
                    toggle-mask
                    :disabled="disabled || !apiAvailable"
                />
                <small v-if="form.errors.password" class="text-red-600">{{ form.errors.password }}</small>
            </div>

            <div class="space-y-2">
                <label class="text-sm font-medium text-slate-700" for="password-confirmation">{{ trans('profile.confirm_password') }}</label>
                <Password
                    id="password-confirmation"
                    v-model="form.password_confirmation"
                    class="w-full"
                    input-class="w-full"
                    :feedback="false"
                    toggle-mask
                    :disabled="disabled || !apiAvailable"
                />
                <small v-if="form.errors.password_confirmation" class="text-red-600">{{ form.errors.password_confirmation }}</small>
            </div>

            <div class="lg:col-span-3 flex justify-end">
                <Button
                    :label="trans('profile.update_password')"
                    icon="pi pi-key"
                    type="submit"
                    :disabled="disabled || !apiAvailable"
                    :loading="loading"
                />
            </div>
        </form>
    </section>
</template>
