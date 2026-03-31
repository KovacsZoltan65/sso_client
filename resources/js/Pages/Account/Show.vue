<script setup>
import PageHeader from '@/Components/PageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    account: { type: Object, required: true },
});
</script>

<template>
    <Head title="My Account" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader
                title="My Account"
                description="This page is the client-facing account summary layer that can later blend SSO identity data with local authorization state."
            />
        </template>

        <div class="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
            <section class="shell-card p-6">
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">Identity</p>
                <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ account.name }}</h2>
                <p class="mt-2 text-sm text-slate-600">{{ account.email }}</p>
            </section>

            <section class="shell-card p-6">
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">Authorization summary</p>
                <div
                    v-if="account.sessionMode === 'local_fallback'"
                    class="mt-4 rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-900"
                >
                    Ez fallback session. A normal RBAC helyett csak minimalis capability keszlet aktiv.
                </div>
                <div class="mt-5 flex flex-wrap gap-2">
                    <span
                        v-for="role in account.roles"
                        :key="role"
                        class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-blue-700"
                    >
                        {{ role }}
                    </span>
                </div>

                <div class="mt-8">
                    <p class="text-sm font-semibold text-slate-900">
                        {{ account.sessionMode === 'local_fallback' ? 'Granted capabilities' : 'Granted permissions' }}
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <span
                            v-for="permission in (account.sessionMode === 'local_fallback' ? account.capabilities : account.permissions)"
                            :key="permission"
                            class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500"
                        >
                            {{ permission }}
                        </span>
                    </div>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
