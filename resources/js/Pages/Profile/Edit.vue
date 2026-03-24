<script setup>
import PageHeader from '@/Components/PageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';

const props = defineProps({
    profile: { type: Object, required: true },
    status: { type: String, default: null },
});

const profileForm = useForm({
    name: props.profile.name,
    email: props.profile.email,
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const deleteForm = useForm({
    password: '',
});
</script>

<template>
    <Head title="Profile" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader
                title="Profile"
                description="Local profile management remains available for development and can later coexist with centrally authenticated SSO identity data."
            />
        </template>

        <div class="space-y-6">
            <section class="shell-card p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-slate-950">Identity details</h2>
                        <p class="mt-2 text-sm text-slate-600">Update the local bootstrap profile used by this client application.</p>
                    </div>
                    <span
                        v-if="status"
                        class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700"
                    >
                        {{ status }}
                    </span>
                </div>

                <form class="mt-6 grid gap-4 md:grid-cols-2" @submit.prevent="profileForm.patch(route('profile.update'))">
                    <div class="space-y-2">
                        <label class="text-sm font-medium text-slate-700">Name</label>
                        <InputText v-model="profileForm.name" class="w-full" />
                        <small class="text-red-600">{{ profileForm.errors.name }}</small>
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium text-slate-700">Email</label>
                        <InputText v-model="profileForm.email" class="w-full" />
                        <small class="text-red-600">{{ profileForm.errors.email }}</small>
                    </div>

                    <div class="md:col-span-2">
                        <Button label="Save profile" icon="pi pi-save" type="submit" />
                    </div>
                </form>

                <div class="mt-6 flex flex-wrap gap-2">
                    <span
                        v-for="role in profile.roles"
                        :key="role"
                        class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500"
                    >
                        {{ role }}
                    </span>
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-2">
                <div class="shell-card p-6">
                    <h2 class="text-xl font-semibold text-slate-950">Password</h2>
                    <p class="mt-2 text-sm text-slate-600">Keep the local bootstrap login usable until the SSO flow is introduced.</p>

                    <form
                        class="mt-6 space-y-4"
                        @submit.prevent="passwordForm.put(route('password.update'), { onSuccess: () => passwordForm.reset() })"
                    >
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-slate-700">Current password</label>
                            <Password v-model="passwordForm.current_password" :feedback="false" toggle-mask class="w-full" input-class="w-full" />
                            <small class="text-red-600">{{ passwordForm.errors.current_password }}</small>
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium text-slate-700">New password</label>
                            <Password v-model="passwordForm.password" toggle-mask class="w-full" input-class="w-full" />
                            <small class="text-red-600">{{ passwordForm.errors.password }}</small>
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium text-slate-700">Confirm password</label>
                            <Password v-model="passwordForm.password_confirmation" :feedback="false" toggle-mask class="w-full" input-class="w-full" />
                        </div>

                        <Button label="Update password" icon="pi pi-key" type="submit" />
                    </form>
                </div>

                <div class="shell-card border-red-100 p-6">
                    <h2 class="text-xl font-semibold text-slate-950">Danger zone</h2>
                    <p class="mt-2 text-sm text-slate-600">This removes the local account record only. Future SSO identity management will remain external.</p>

                    <form class="mt-6 space-y-4" @submit.prevent="deleteForm.delete(route('profile.destroy'))">
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-slate-700">Confirm with password</label>
                            <Password v-model="deleteForm.password" :feedback="false" toggle-mask class="w-full" input-class="w-full" />
                            <small class="text-red-600">{{ deleteForm.errors.password }}</small>
                        </div>

                        <Button label="Delete account" icon="pi pi-trash" severity="danger" type="submit" />
                    </form>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
