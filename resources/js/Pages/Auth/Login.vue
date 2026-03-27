<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head } from '@inertiajs/vue3';
import Button from 'primevue/button';
import { ref } from 'vue';

const props = defineProps({
    loginUrl: { type: String, required: true },
    status: { type: String, default: null },
    ssoStatus: { type: Object, required: true },
});

const loading = ref(false);

function startSsoLogin() {
    loading.value = true;
    window.location.assign(props.loginUrl);
}
</script>

<template>
    <Head title="Bejelentkezes" />

    <GuestLayout>
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-400">Central SSO login</p>
            <h1 class="mt-3 text-3xl font-semibold text-slate-950">Bejelentkezes az SSO szerveren keresztul</h1>
            <p class="mt-3 text-sm leading-7 text-slate-600">
                A kliens nem tarol sajat hitelesitesi logikat. A bejelentkezes az `sso_server` authorize, token es userinfo vegpontjain keresztul tortenik.
            </p>
        </div>

        <div v-if="status" class="mt-6 rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ status }}</div>
        <div v-if="$page.props.flash.error" class="mt-6 rounded-2xl bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $page.props.flash.error }}
        </div>

        <div class="mt-8 rounded-[1.75rem] border border-slate-200/70 bg-slate-50 p-5">
            <p class="text-sm font-semibold text-slate-900">Aktiv kapcsolat</p>
            <dl class="mt-4 space-y-3 text-sm text-slate-600">
                <div class="flex items-start justify-between gap-4">
                    <dt>SSO szerver</dt>
                    <dd class="text-right">{{ ssoStatus.serverBaseUrl || 'Nincs beallitva' }}</dd>
                </div>
                <div class="flex items-start justify-between gap-4">
                    <dt>Redirect URI</dt>
                    <dd class="text-right">{{ ssoStatus.redirectUri || 'Nincs beallitva' }}</dd>
                </div>
                <div class="flex items-start justify-between gap-4">
                    <dt>Scope-ok</dt>
                    <dd class="text-right">{{ ssoStatus.scopes?.join(', ') || 'Nincsenek beallitva' }}</dd>
                </div>
            </dl>
        </div>

        <div class="mt-8 space-y-4">
            <Button
                type="button"
                label="SSO bejelentkezes inditasa"
                icon="pi pi-sign-in"
                class="w-full"
                :loading="loading"
                @click="startSsoLogin"
            />

            <p class="text-sm leading-7 text-slate-500">
                Ha a session lejart, a vedett oldalak ide iranyitanak vissza, innen pedig ujraindithato a redirect alapu bejelentkezes.
            </p>
        </div>

        <div class="mt-8 rounded-[1.75rem] border border-dashed border-slate-300 px-5 py-4 text-sm leading-7 text-slate-600">
            <p class="font-semibold text-slate-900">Hibakezeles</p>
            <p class="mt-2">
                Ervenytelen state, hianyzo code, token csere hiba vagy userinfo hiba eseten nem jon letre lokalis session, es a rendszer visszahoz erre az oldalra.
            </p>
        </div>
    </GuestLayout>
</template>
