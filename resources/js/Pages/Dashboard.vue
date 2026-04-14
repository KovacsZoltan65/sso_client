<script setup>
import PageHeader from "@/Components/PageHeader.vue";
import StatCard from "@/Components/StatCard.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head } from "@inertiajs/vue3";
import { trans } from "laravel-vue-i18n";

defineProps({
    stats: { type: Object, required: true },
    recentUsers: { type: Array, required: true },
    recentActivity: { type: Array, required: true },
    ssoStatus: { type: Object, required: true },
    userContext: { type: Object, required: true },
});
</script>

<template>
    <Head :title="trans('navigation.dashboard_label')" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader
                :title="trans('navigation.dashboard_label')"
                :description="trans('navigation.dashboard_description')"
            />
        </template>

        <div class="space-y-6">
            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    :label="trans('navigation.users.label')"
                    :value="stats.users"
                    icon="pi pi-users"
                />
                <StatCard
                    :label="trans('navigation.roles.label')"
                    :value="stats.roles"
                    icon="pi pi-shield"
                />
                <StatCard
                    :label="trans('navigation.permissions.label')"
                    :value="stats.permissions"
                    icon="pi pi-lock"
                />
                <StatCard
                    :label="trans('navigation.activity_entities_title')"
                    :value="stats.activityEntries"
                    icon="pi pi-history"
                />
            </section>

            <section class="grid gap-6 xl:grid-cols-[1.3fr_0.7fr]">
                <div class="shell-card p-6">
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400"
                    >
                        {{ trans("common.current_access_context") }}
                    </p>
                    <h2 class="mt-3 text-2xl font-semibold text-slate-950">
                        {{ trans("common.welcome_back") }}, {{ userContext.name }}
                    </h2>
                    <p class="mt-2 text-sm leading-7 text-slate-600">
                        {{ trans("common.welcome_description") }}
                    </p>

                    <div class="mt-5 flex flex-wrap gap-2">
                        <span
                            v-for="role in userContext.roles"
                            :key="role"
                            class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-blue-700"
                        >
                            {{ role }}
                        </span>
                    </div>

                    <div class="mt-8 grid gap-4 lg:grid-cols-2">
                        <div class="rounded-3xl bg-slate-50 p-5">
                            <p class="text-sm font-semibold text-slate-900">SSO mod</p>
                            <p class="mt-2 text-sm text-slate-600">
                                {{ ssoStatus.mode }}
                            </p>
                        </div>
                        <div class="rounded-3xl bg-slate-50 p-5">
                            <p class="text-sm font-semibold text-slate-900">
                                Lokalis auth fallback
                            </p>
                            <p class="mt-2 text-sm text-slate-600">
                                {{
                                    ssoStatus.localAuthEnabled
                                        ? trans("common.allowed")
                                        : trans("common.forbidden")
                                }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="shell-card p-6">
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400"
                    >
                        {{ trans("common.recent_users") }}
                    </p>
                    <div class="mt-5 space-y-4">
                        <div
                            v-for="recentUser in recentUsers"
                            :key="recentUser.id"
                            class="rounded-2xl border border-slate-200/70 px-4 py-4"
                        >
                            <p class="font-semibold text-slate-900">
                                {{ recentUser.name }}
                            </p>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ recentUser.email }}
                            </p>
                            <p
                                class="mt-2 text-xs uppercase tracking-[0.18em] text-slate-400"
                            >
                                {{
                                    recentUser.roles.join(", ") ||
                                    trans("common.no_assigned_roled")
                                }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="shell-card p-6">
                <p
                    class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400"
                >
                    {{ trans("common.audit_preview") }}
                </p>
                <h2 class="mt-2 text-xl font-semibold text-slate-950">Recent activity</h2>

                <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <div
                        v-for="entry in recentActivity"
                        :key="entry.id"
                        class="rounded-3xl border border-slate-200/70 bg-slate-50 px-5 py-4"
                    >
                        <p class="text-sm font-semibold text-slate-900">
                            {{ entry.description }}
                        </p>
                        <p
                            class="mt-2 text-xs uppercase tracking-[0.18em] text-slate-400"
                        >
                            {{ entry.event || "event.pending" }}
                        </p>
                        <p class="mt-3 text-sm text-slate-500">{{ entry.created_at }}</p>
                    </div>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
