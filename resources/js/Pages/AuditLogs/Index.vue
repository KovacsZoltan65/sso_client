<script setup>
import PageHeader from '@/Components/PageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    entries: { type: Array, required: true },
});
</script>

<template>
    <Head title="Audit Logs" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader
                title="Audit Logs"
                description="Activity logging is already configured. This page exposes a small live preview from spatie/laravel-activitylog."
            />
        </template>

        <section class="shell-card overflow-hidden">
            <div class="grid grid-cols-[1.1fr_0.7fr_0.7fr_0.8fr] gap-4 border-b border-slate-200/70 px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                <span>Description</span>
                <span>Event</span>
                <span>Subject</span>
                <span>When</span>
            </div>

            <div
                v-for="entry in entries"
                :key="entry.id"
                class="grid grid-cols-1 gap-3 border-b border-slate-100 px-6 py-5 text-sm text-slate-600 md:grid-cols-[1.1fr_0.7fr_0.7fr_0.8fr]"
            >
                <div>
                    <p class="font-semibold text-slate-900">{{ entry.description }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ entry.causer?.name || 'system' }}</p>
                </div>
                <span>{{ entry.event || 'event.pending' }}</span>
                <span>{{ entry.subject_type || 'n/a' }}</span>
                <span>{{ entry.created_at }}</span>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
