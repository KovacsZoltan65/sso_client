<script setup>
import { useAuth } from '@/Composables/useAuth';
import { Head, Link } from '@inertiajs/vue3';
import Button from 'primevue/button';

defineProps({
    appName: { type: String, required: true },
    canLogin: { type: Boolean, default: false },
    canRegister: { type: Boolean, default: false },
});

const { isAuthenticated } = useAuth();
</script>

<template>
    <Head title="Welcome" />

    <div class="auth-backdrop min-h-screen px-4 py-6 lg:px-8">
        <div class="mx-auto flex max-w-7xl flex-col gap-8">
            <header class="flex items-center justify-between rounded-[2rem] border border-white/80 bg-white/80 px-6 py-4 shadow-sm backdrop-blur">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-400">Foundation ready</p>
                    <h1 class="mt-2 text-xl font-semibold text-slate-950">{{ appName }}</h1>
                </div>
                <div class="flex items-center gap-3">
                    <Link v-if="isAuthenticated" :href="route('dashboard')">
                        <Button label="Dashboard" severity="secondary" outlined />
                    </Link>
                    <Link v-else-if="canLogin" :href="route('login')">
                        <Button label="SSO bejelentkezes" severity="secondary" outlined />
                    </Link>
                </div>
            </header>

            <main class="grid gap-6 lg:grid-cols-[1.15fr_0.85fr]">
                <section class="shell-gradient rounded-[2.25rem] p-8 text-white md:p-10">
                    <p class="text-sm font-semibold uppercase tracking-[0.32em] text-white/65">Laravel 13 + Vue 3 + Inertia</p>
                    <h2 class="mt-6 max-w-3xl text-4xl font-semibold leading-tight">
                        Minimalisan mukodo SSO kliens redirect, callback es lokalis session flow-val.
                    </h2>
                    <p class="mt-6 max-w-2xl text-sm leading-8 text-white/78">
                        A kliens az `sso_server` authorize, token es userinfo foundationre epul, mikozben a vedett oldalak tovabbra is a Laravel session + `auth` guard mogott maradnak.
                    </p>
                </section>

                <section class="grid gap-6">
                    <div class="shell-card p-6">
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">Included base modules</p>
                        <div class="mt-5 grid gap-3">
                            <div class="rounded-3xl bg-slate-50 px-4 py-4 text-sm text-slate-700">Dashboard and app shell</div>
                            <div class="rounded-3xl bg-slate-50 px-4 py-4 text-sm text-slate-700">Profile and account management</div>
                            <div class="rounded-3xl bg-slate-50 px-4 py-4 text-sm text-slate-700">Users, roles, permissions placeholders</div>
                            <div class="rounded-3xl bg-slate-50 px-4 py-4 text-sm text-slate-700">SSO status and audit preview</div>
                        </div>
                    </div>

                    <div class="shell-card p-6">
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">Auth viselkedes</p>
                        <p class="mt-3 text-sm leading-7 text-slate-600">
                            A vedett oldalak session nelkul a bejelentkezesi oldalra kerulnek, ahonnan egyetlen gomb inditja el az SSO redirect flow-t.
                        </p>
                    </div>
                </section>
            </main>
        </div>
    </div>
</template>
