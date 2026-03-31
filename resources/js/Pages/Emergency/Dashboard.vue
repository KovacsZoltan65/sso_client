<script setup>
import PageHeader from '@/Components/PageHeader.vue';
import EmergencyLayout from '@/Layouts/EmergencyLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    status: { type: Object, required: true },
    account: { type: Object, required: true },
    summary: { type: Object, required: true },
    recentActivity: { type: Array, required: true },
});
</script>

<template>
    <Head title="Emergency Dashboard" />

    <EmergencyLayout :status="status" :account="account" logout-url="/emergency/logout">
        <PageHeader
            title="Emergency Dashboard"
            description="Read-only break-glass overview. Business writes remain blocked while emergency mode is active."
        />

        <div class="mt-8 grid gap-4 md:grid-cols-3">
            <div class="rounded-3xl border border-white/10 bg-white/5 p-5">
                <p class="text-sm text-slate-300">Users</p>
                <p class="mt-2 text-3xl font-semibold text-white">{{ summary.users }}</p>
            </div>
            <div class="rounded-3xl border border-white/10 bg-white/5 p-5">
                <p class="text-sm text-slate-300">Companies</p>
                <p class="mt-2 text-3xl font-semibold text-white">{{ summary.companies }}</p>
            </div>
            <div class="rounded-3xl border border-white/10 bg-white/5 p-5">
                <p class="text-sm text-slate-300">Activity entries</p>
                <p class="mt-2 text-3xl font-semibold text-white">{{ summary.activityEntries }}</p>
            </div>
        </div>

        <div class="mt-8 flex flex-wrap gap-3">
            <Link href="/emergency/audit-logs" class="rounded-2xl border border-white/10 px-4 py-2 text-sm font-medium text-white/88 transition hover:bg-white/10">Audit logs</Link>
            <Link href="/emergency/users" class="rounded-2xl border border-white/10 px-4 py-2 text-sm font-medium text-white/88 transition hover:bg-white/10">Users</Link>
            <Link href="/emergency/companies" class="rounded-2xl border border-white/10 px-4 py-2 text-sm font-medium text-white/88 transition hover:bg-white/10">Companies</Link>
        </div>

        <div class="mt-8 rounded-3xl border border-white/10 bg-white/5 p-5">
            <p class="text-sm font-semibold text-white">Recent activity</p>
            <div class="mt-4 space-y-3">
                <div v-for="entry in recentActivity" :key="entry.id" class="rounded-2xl border border-white/10 px-4 py-4">
                    <p class="font-medium text-white">{{ entry.description }}</p>
                    <p class="mt-2 text-xs uppercase tracking-[0.18em] text-slate-400">{{ entry.event }}</p>
                    <p class="mt-2 text-sm text-slate-300">{{ entry.created_at }}</p>
                </div>
            </div>
        </div>
    </EmergencyLayout>
</template>
