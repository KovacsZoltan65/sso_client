<script setup>
import PageHeader from '@/Components/PageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';

const props = defineProps({
    profile: { type: Object, required: true },
    status: { type: String, default: null },
});

const profileForm = useForm({
    name: props.profile.name,
    email: props.profile.email,
});
</script>

<template>
    <Head title="Profile" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader
                title="Profile"
                description="A helyi profiloldal csak az alkalmazason beluli alap adatokhoz es szerepkorokhoz kapcsolodik, a hitelesites viszont mar az SSO szerveren tortenik."
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

            <section class="shell-card p-6">
                <h2 class="text-xl font-semibold text-slate-950">SSO identitas</h2>
                <p class="mt-2 text-sm leading-7 text-slate-600">
                    A jelszo- es accountkezeles nem ebben a kliensben tortenik. Ha a bejelentkezett felhasznalo adatai vagy hitelesitesi allapota valtozik, azt az `sso_server` oldalon kell kezelni, majd ujrainditani a bejelentkezest.
                </p>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
