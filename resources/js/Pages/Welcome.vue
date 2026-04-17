<script setup>
import { useAuth } from "@/Composables/useAuth";
import { Head, Link } from "@inertiajs/vue3";
import Button from "primevue/button";
import { trans } from "laravel-vue-i18n";

defineProps({
    appName: { type: String, required: true },
    canLogin: { type: Boolean, default: false },
    canRegister: { type: Boolean, default: false },
});

const { isAuthenticated, user, loginUrl, logoutUrl } = useAuth();
</script>

<template>
    <Head :title="trans('common.welcome')" />

    <div class="auth-backdrop min-h-screen px-4 py-6 lg:px-8">
        <div class="mx-auto flex max-w-7xl flex-col gap-8">
            <header
                class="flex items-center justify-between rounded-[2rem] border border-white/80 bg-white/80 px-6 py-4 shadow-sm backdrop-blur"
            >
                <div>
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-400"
                    >
                        {{ trans("common.foundation_ready_title") }}
                    </p>
                    <h1 class="mt-2 text-xl font-semibold text-slate-950">
                        {{ appName }}
                    </h1>
                </div>
                <div class="flex items-center gap-3">
                    <Link v-if="isAuthenticated" :href="route('dashboard')">
                        <Button
                            :label="trans('navigation.dashboard.label')"
                            severity="secondary"
                            outlined
                        />
                    </Link>
                    <Link
                        v-if="isAuthenticated"
                        :href="logoutUrl"
                        method="post"
                        as="button"
                    >
                        <Button
                            :label="trans('common.logout')"
                            severity="secondary"
                            text
                        />
                    </Link>
                    <Link v-else-if="canLogin" :href="loginUrl">
                        <Button
                            :label="trans('common.sso_login')"
                            severity="secondary"
                            outlined
                        />
                    </Link>
                </div>
            </header>

            <main class="grid gap-6 lg:grid-cols-[1.15fr_0.85fr]">
                <section class="shell-gradient rounded-[2.25rem] p-8 text-white md:p-10">
                    <p
                        class="text-sm font-semibold uppercase tracking-[0.32em] text-white/65"
                    >
                        {{ trans("common.components") }}
                    </p>
                    <h2 class="mt-6 max-w-3xl text-4xl font-semibold leading-tight">
                        {{ trans("common.welcome_description_2") }}
                    </h2>
                    <p class="mt-6 max-w-2xl text-sm leading-8 text-white/78">
                        {{ trans("common.welcome_description_3") }}
                    </p>
                </section>

                <section class="grid gap-6">
                    <div class="shell-card p-6">
                        <p
                            class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400"
                        >
                            {{ trans("common.included_base_modules") }}
                        </p>
                        <div class="mt-5 grid gap-3">
                            <div
                                class="rounded-3xl bg-slate-50 px-4 py-4 text-sm text-slate-700"
                            >
                                {{ trans("included.dashboard_and_shell") }}
                            </div>
                            <div
                                class="rounded-3xl bg-slate-50 px-4 py-4 text-sm text-slate-700"
                            >
                                {{ trans("included.profile_and_account") }}
                            </div>
                            <div
                                class="rounded-3xl bg-slate-50 px-4 py-4 text-sm text-slate-700"
                            >
                                {{ trans("included.user_roles_permissions") }}
                            </div>
                            <div
                                class="rounded-3xl bg-slate-50 px-4 py-4 text-sm text-slate-700"
                            >
                                {{ trans("included.sso_status_audit_preview") }}
                            </div>
                        </div>
                    </div>

                    <div class="shell-card p-6">
                        <p
                            class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400"
                        >
                            {{ trans("common.auth_behavior.title") }}
                        </p>
                        <p class="mt-3 text-sm leading-7 text-slate-600">
                            {{ trans("common.auth_behavior.description") }}
                        </p>
                        <p
                            v-if="isAuthenticated"
                            class="mt-4 rounded-3xl bg-emerald-50 px-4 py-4 text-sm text-emerald-700"
                        >
                            {{ trans("common.active_session") }}: {{ user?.name }} ({{
                                user?.email
                            }})
                        </p>
                    </div>
                </section>
            </main>
        </div>
    </div>
</template>
