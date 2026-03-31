<script setup>
import AppBrand from '@/Components/AppBrand.vue';
import Button from 'primevue/button';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
    status: { type: Object, required: true },
    account: { type: Object, default: null },
    logoutUrl: { type: String, default: null },
});

function logout() {
    if (props.logoutUrl) {
        router.post(props.logoutUrl);
    }
}
</script>

<template>
    <div class="min-h-screen bg-slate-950 px-4 py-6 text-slate-100 sm:px-6 lg:px-8">
        <div class="mx-auto flex max-w-7xl flex-col gap-6">
            <div class="flex flex-col gap-4 rounded-[2rem] border border-amber-500/40 bg-amber-400/10 p-5 shadow-2xl shadow-slate-950/30 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-start gap-4">
                    <div class="rounded-2xl bg-amber-400/20 px-4 py-3">
                        <AppBrand />
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.32em] text-amber-200">Emergency Mode</p>
                        <h1 class="mt-2 text-2xl font-semibold text-white">Limited break-glass access</h1>
                        <p class="mt-2 max-w-3xl text-sm leading-7 text-slate-200">
                            {{ status.bannerMessage || 'SSO unavailable - limited emergency mode is active.' }}
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <Link href="/emergency/status" class="rounded-2xl border border-white/15 px-4 py-2 text-sm font-medium text-white/88 transition hover:bg-white/10">
                        Status
                    </Link>
                    <Link
                        v-if="account"
                        href="/emergency/dashboard"
                        class="rounded-2xl border border-white/15 px-4 py-2 text-sm font-medium text-white/88 transition hover:bg-white/10"
                    >
                        Dashboard
                    </Link>
                    <Button
                        v-if="logoutUrl"
                        type="button"
                        label="Emergency logout"
                        severity="contrast"
                        @click="logout"
                    />
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-[0.9fr_0.1fr_1fr]">
                <aside class="rounded-[2rem] border border-white/10 bg-slate-900/80 p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">State</p>
                    <dl class="mt-5 space-y-4 text-sm">
                        <div>
                            <dt class="text-slate-400">Mode</dt>
                            <dd class="mt-1 font-semibold text-white">{{ status.state }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">SSO reachable</dt>
                            <dd class="mt-1 font-semibold text-white">{{ status.ssoReachable ? 'Yes' : 'No' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Activation reference</dt>
                            <dd class="mt-1 break-all font-semibold text-white">{{ status.activationReference || 'Not active' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Activated by</dt>
                            <dd class="mt-1 font-semibold text-white">{{ status.activatedBy || 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Expires at</dt>
                            <dd class="mt-1 font-semibold text-white">{{ status.expiresAt || 'N/A' }}</dd>
                        </div>
                        <div v-if="account">
                            <dt class="text-slate-400">Emergency role</dt>
                            <dd class="mt-1 font-semibold text-white">{{ account.role }}</dd>
                        </div>
                    </dl>
                </aside>

                <div class="hidden lg:block" />

                <main class="min-h-[24rem] rounded-[2rem] border border-white/10 bg-slate-900/80 p-6">
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>
