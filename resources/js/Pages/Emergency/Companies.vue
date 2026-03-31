<script setup>
import PageHeader from '@/Components/PageHeader.vue';
import EmergencyLayout from '@/Layouts/EmergencyLayout.vue';
import { Head } from '@inertiajs/vue3';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';

defineProps({
    status: { type: Object, required: true },
    companies: { type: Array, required: true },
});
</script>

<template>
    <Head title="Emergency Companies" />

    <EmergencyLayout :status="status" logout-url="/emergency/logout">
        <PageHeader title="Emergency Companies" description="Read-only company index available while normal SSO access is unavailable." />

        <DataTable class="mt-8" :value="companies" data-key="id" striped-rows>
            <Column field="id" header="ID" />
            <Column field="name" header="Name" />
            <Column field="code" header="Code" />
            <Column field="email" header="Email" />
            <Column field="phone" header="Phone" />
            <Column field="is_active" header="Active" />
        </DataTable>
    </EmergencyLayout>
</template>
