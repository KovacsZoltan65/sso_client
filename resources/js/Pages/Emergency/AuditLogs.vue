<script setup>
import PageHeader from '@/Components/PageHeader.vue';
import EmergencyLayout from '@/Layouts/EmergencyLayout.vue';
import { Head } from '@inertiajs/vue3';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';

defineProps({
    status: { type: Object, required: true },
    entries: { type: Array, required: true },
});
</script>

<template>
    <Head title="Emergency Audit Logs" />

    <EmergencyLayout :status="status" logout-url="/emergency/logout">
        <PageHeader title="Emergency Audit Logs" description="Read-only audit trail view available during emergency operations." />

        <DataTable class="mt-8" :value="entries" data-key="id" striped-rows>
            <Column field="log_name" header="Log" />
            <Column field="event" header="Event" />
            <Column field="description" header="Description" />
            <Column field="subject_type" header="Subject" />
            <Column field="created_at" header="Created" />
        </DataTable>
    </EmergencyLayout>
</template>
