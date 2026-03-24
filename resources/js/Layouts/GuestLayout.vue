<script setup>
import AppBrand from '@/Components/AppBrand.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="auth-backdrop flex min-h-screen items-center justify-center px-4 py-10">
        <div class="grid w-full max-w-6xl gap-10 lg:grid-cols-[1.1fr_0.9fr]">
            <section class="shell-gradient hidden rounded-[2rem] p-10 lg:flex lg:flex-col lg:justify-between">
                <div>
                    <AppBrand />
                    <p class="mt-10 max-w-lg text-4xl font-semibold leading-tight text-white">
                        Production-oriented client foundation for a future central SSO login flow.
                    </p>
                    <p class="mt-5 max-w-xl text-sm leading-7 text-white/72">
                        Local Breeze authentication stays available for bootstrap and testing, while the application structure remains ready for redirect and callback based SSO.
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="glass-panel rounded-3xl p-5 text-white/82">
                        <p class="text-xs uppercase tracking-[0.2em] text-white/60">Authorization</p>
                        <p class="mt-3 text-lg font-semibold text-white">Spatie permission ready</p>
                    </div>
                    <div class="glass-panel rounded-3xl p-5 text-white/82">
                        <p class="text-xs uppercase tracking-[0.2em] text-white/60">Observability</p>
                        <p class="mt-3 text-lg font-semibold text-white">Activity logging enabled</p>
                    </div>
                </div>
            </section>

            <section class="shell-card mx-auto w-full max-w-xl p-6 sm:p-8">
                <div class="mb-8 lg:hidden">
                    <Link href="/" class="inline-flex">
                        <div class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold tracking-[0.25em] text-white">
                            SSO CLIENT
                        </div>
                    </Link>
                </div>
                <slot />
            </section>
        </div>
    </div>
</template>
