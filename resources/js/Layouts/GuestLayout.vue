<script setup>
import AppBrand from '@/Components/AppBrand.vue';
</script>

<template>
    <div class="auth-backdrop flex min-h-screen items-center justify-center px-4 py-10">
        <div class="grid w-full max-w-6xl gap-10 lg:grid-cols-[1.1fr_0.9fr]">
            <section class="shell-gradient hidden rounded-[2rem] p-10 lg:flex lg:flex-col lg:justify-between">
                <div>
                    <AppBrand />
                    <p class="mt-10 max-w-lg text-4xl font-semibold leading-tight text-white">
                        Bejelentkezes a folytatashoz.
                    </p>
                    <p class="mt-5 max-w-xl text-sm leading-7 text-white/72">
                        A kozponti hitelesites biztonsagosan visszairanyitja oda, ahol folytatni szeretne.
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="glass-panel rounded-3xl p-5 text-white/82">
                        <p class="text-xs uppercase tracking-[0.2em] text-white/60">Sign in</p>
                        <p class="mt-3 text-lg font-semibold text-white">Kozponti belepes</p>
                    </div>
                    <div class="glass-panel rounded-3xl p-5 text-white/82">
                        <p class="text-xs uppercase tracking-[0.2em] text-white/60">Continue</p>
                        <p class="mt-3 text-lg font-semibold text-white">Visszairanyitas az alkalmazasba</p>
                    </div>
                </div>
            </section>

            <section class="shell-card mx-auto w-full max-w-xl p-6 sm:p-8">
                <div class="mb-8 lg:hidden inline-flex items-center gap-3 rounded-2xl bg-slate-100 px-4 py-3 text-slate-700">
                    <AppBrand />
                    <span class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Secure sign in</span>
                </div>
                <slot />
            </section>
        </div>
    </div>
</template>
