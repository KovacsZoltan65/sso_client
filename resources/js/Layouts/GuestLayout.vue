<script setup>
import AppBrand from '@/Components/AppBrand.vue';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
import { trans } from 'laravel-vue-i18n';
</script>

<template>
    <div class="auth-backdrop relative flex min-h-screen items-center justify-center px-4 py-10">
        <div class="absolute right-4 top-4 z-10 sm:right-6 sm:top-6">
            <LanguageSwitcher />
        </div>

        <div class="grid w-full max-w-6xl gap-10 lg:grid-cols-[1.1fr_0.9fr]">
            <section class="shell-gradient hidden rounded-[2rem] p-10 lg:flex lg:flex-col lg:justify-between">
                <div>
                    <AppBrand />
                    <p class="mt-10 max-w-lg text-4xl font-semibold leading-tight text-white">
                        {{ trans('auth.guest_layout.title') }}
                    </p>
                    <p class="mt-5 max-w-xl text-sm leading-7 text-white/72">
                        {{ trans('auth.guest_layout.description') }}
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="glass-panel rounded-3xl p-5 text-white/82">
                        <p class="text-xs uppercase tracking-[0.2em] text-white/60">{{ trans('auth.guest_layout.step_sign_in_label') }}</p>
                        <p class="mt-3 text-lg font-semibold text-white">{{ trans('auth.guest_layout.step_sign_in_value') }}</p>
                    </div>
                    <div class="glass-panel rounded-3xl p-5 text-white/82">
                        <p class="text-xs uppercase tracking-[0.2em] text-white/60">{{ trans('auth.guest_layout.step_continue_label') }}</p>
                        <p class="mt-3 text-lg font-semibold text-white">{{ trans('auth.guest_layout.step_continue_value') }}</p>
                    </div>
                </div>
            </section>

            <section class="shell-card mx-auto w-full max-w-xl p-6 sm:p-8">
                <div class="mb-8 lg:hidden inline-flex items-center gap-3 rounded-2xl bg-slate-100 px-4 py-3 text-slate-700">
                    <AppBrand />
                    <span class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">{{ trans('auth.guest_layout.mobile_badge') }}</span>
                </div>
                <slot />
            </section>
        </div>
    </div>
</template>
