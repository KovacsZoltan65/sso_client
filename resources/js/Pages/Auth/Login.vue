<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import Button from 'primevue/button';
import { ref } from 'vue';

const props = defineProps({
    loginUrl: { type: String, required: true },
    localLoginUrl: { type: String, required: true },
    status: { type: String, default: null },
    ssoStatus: { type: Object, required: true },
    decision: { type: Object, required: true },
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
        <div v-if="decision.warning" class="mt-6 rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-800">
            {{ decision.warning }}
        </div>
        <div
            v-if="decision.reachability?.status === 'maintenance'"
            class="mt-6 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900"
        >
            Az SSO szerver jelenleg karbantartas alatt van. A normal belepes ideiglenesen nem erheto el.
            <span v-if="decision.reachability?.retryAfter" class="mt-1 block text-xs uppercase tracking-[0.18em] text-amber-700">
                Retry-After: {{ decision.reachability.retryAfter }}
            </span>
        </div>
        <div
            v-else-if="decision.reachability?.status === 'degraded'"
            class="mt-6 rounded-2xl border border-orange-300 bg-orange-50 px-4 py-3 text-sm text-orange-900"
        >
            Az SSO szerver jelenleg reszben hibas allapotban van. A normal belepes kockazatos lehet, a fallback csak kulon engedellyel erheto el.
        </div>
        <div
            v-else-if="decision.reachability?.status === 'unreachable'"
            class="mt-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
        >
            Az SSO szerver jelenleg nem erheto el. A normal belepes atmenetileg nem indithato el.
        </div>
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
                <div class="flex items-start justify-between gap-4">
                    <dt>SSO reachability</dt>
                    <dd class="text-right">{{ decision.reachability?.status || 'unknown' }}</dd>
                </div>
                <div v-if="decision.reachability?.retryAfter" class="flex items-start justify-between gap-4">
                    <dt>Retry-After</dt>
                    <dd class="text-right">{{ decision.reachability.retryAfter }}</dd>
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

        <div
            v-if="decision.featureEnabled"
            class="mt-8 rounded-[1.75rem] border border-amber-200 bg-amber-50 px-5 py-4 text-sm leading-7 text-amber-900"
        >
            <p class="font-semibold">Local fallback allapot</p>
            <p class="mt-2">
                <span v-if="decision.currentlyAllowed">
                    <span v-if="decision.fallbackReason === 'degraded_allowed'">
                        Az SSO szerver jelenleg reszben hibas allapotban van, ezert a korlatozott local fallback mod ideiglenesen engedelyezett.
                    </span>
                    <span v-else>
                        Az SSO szerver jelenleg nem erheto el, ezert a korlatozott local fallback mod hasznalhato.
                    </span>
                </span>
                <span v-else-if="decision.reachability?.status === 'maintenance'">
                    A fallback kepesseg aktiv, de jelenleg blokkolt, mert az SSO szerver karbantartas alatt van.
                </span>
                <span v-else-if="decision.reachability?.status === 'degraded'">
                    A fallback kepesseg aktiv, de a degraded fallback jelenleg nincs engedelyezve.
                </span>
                <span v-else>
                    A fallback kepesseg aktiv, de a local login jelenleg blokkolt.
                </span>
            </p>
            <p v-if="decision.incidentId" class="mt-2 text-xs uppercase tracking-[0.18em] text-amber-700">
                Incident: {{ decision.incidentId }}
            </p>
            <Link
                v-if="decision.currentlyAllowed"
                :href="localLoginUrl"
                class="mt-4 inline-flex rounded-2xl bg-amber-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-amber-700"
            >
                Local fallback login megnyitasa
            </Link>
        </div>

        <div class="mt-8 rounded-[1.75rem] border border-dashed border-slate-300 px-5 py-4 text-sm leading-7 text-slate-600">
            <p class="font-semibold text-slate-900">Hibakezeles</p>
            <p class="mt-2">
                Ervenytelen state, hianyzo code, token csere hiba vagy userinfo hiba eseten nem jon letre lokalis session, es a rendszer visszahoz erre az oldalra.
            </p>
        </div>
    </GuestLayout>
</template>
