<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';

defineProps({
    decision: { type: Object, required: true },
    status: { type: String, default: null },
});

const form = useForm({
    email: '',
    password: '',
});
</script>

<template>
    <Head title="Local fallback login" />

    <GuestLayout>
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-400">Korlatozott fallback auth</p>
            <h1 class="mt-3 text-3xl font-semibold text-slate-950">Helyi hitelesites SSO kieses idejere</h1>
            <p class="mt-3 text-sm leading-7 text-slate-600">
                Ez a belepesi ut csak dedikalt, local-only allowlistelt felhasznaloknak erheto el, amikor az SSO szerver tenylegesen nem elerheto.
            </p>
        </div>

        <div class="mt-6 rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <p class="font-semibold">Helyi hitelesites aktiv</p>
            <p class="mt-1" v-if="decision.fallbackReason === 'degraded_allowed'">
                Az SSO szerver reszben hibas allapotban van. Korlatozott fallback mod fut, fokozott ovatossag szukseges.
            </p>
            <p class="mt-1" v-else>
                Az SSO szerver nem erheto el. Korlatozott fallback mod fut.
            </p>
            <p v-if="decision.incidentId" class="mt-2 text-xs uppercase tracking-[0.18em] text-amber-700">
                Incident: {{ decision.incidentId }}
            </p>
        </div>

        <div v-if="status" class="mt-6 rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ status }}</div>
        <div v-if="$page.props.flash.error" class="mt-6 rounded-2xl bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $page.props.flash.error }}
        </div>

        <form class="mt-8 space-y-5" @submit.prevent="form.post(route('local-login.store'))">
            <div class="space-y-2">
                <label class="text-sm font-medium text-slate-700">Email</label>
                <InputText v-model="form.email" class="w-full" autocomplete="username" />
                <small class="text-red-600">{{ form.errors.email }}</small>
            </div>

            <div class="space-y-2">
                <label class="text-sm font-medium text-slate-700">Password</label>
                <Password v-model="form.password" :feedback="false" toggle-mask class="w-full" input-class="w-full" autocomplete="current-password" />
                <small class="text-red-600">{{ form.errors.password }}</small>
            </div>

            <Button type="submit" label="Local fallback login" class="w-full" :loading="form.processing" />
        </form>

        <div class="mt-8 rounded-[1.75rem] border border-dashed border-slate-300 px-5 py-4 text-sm leading-7 text-slate-600">
            <p class="font-semibold text-slate-900">Korlatozasok</p>
            <p class="mt-2">Ez a session nem nyitja meg a teljes admin feluletet, csak a minimalis uzemi oldalak maradnak elerhetok.</p>
        </div>
    </GuestLayout>
</template>
