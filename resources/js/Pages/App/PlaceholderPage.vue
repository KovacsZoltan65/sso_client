<script setup>
import EmptyStatePanel from '@/Components/EmptyStatePanel.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    title: { type: String, required: true },
    description: { type: String, required: true },
    tags: { type: Array, default: () => [] },
});
</script>

<template>
    <Head :title="title" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader :title="title" :description="description" />
        </template>

        <EmptyStatePanel :title="title" :description="description" :tags="tags" />
    </AuthenticatedLayout>
</template>
