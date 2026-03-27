<script setup>
import Button from 'primevue/button';
import Message from 'primevue/message';
import Password from 'primevue/password';

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
            <h2 class="text-xl font-semibold text-slate-950">Change password</h2>
            <p class="mt-2 text-sm text-slate-600">
                Password validation, current-password checks, hashing, and audit logging all stay on the SSO server.
            </p>
        </div>

        <Message v-if="!apiAvailable" severity="warn" class="mt-6">
            Password change is unavailable until the upstream self-service API is configured.
        </Message>

        <form class="mt-6 grid gap-4 lg:grid-cols-3" @submit.prevent="emit('submit')">
            <div class="space-y-2">
                <label class="text-sm font-medium text-slate-700" for="current-password">Current password</label>
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
                <label class="text-sm font-medium text-slate-700" for="new-password">New password</label>
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
                <label class="text-sm font-medium text-slate-700" for="password-confirmation">Confirm password</label>
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
                    label="Update password"
                    icon="pi pi-key"
                    type="submit"
                    :disabled="disabled || !apiAvailable"
                    :loading="loading"
                />
            </div>
        </form>
    </section>
</template>
