<script setup>
import PageHeader from '@/Components/PageHeader.vue';
import EmergencyLayout from '@/Layouts/EmergencyLayout.vue';
import { Head } from '@inertiajs/vue3';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';

defineProps({
    status: { type: Object, required: true },
    users: { type: Array, required: true },
});
</script>

<template>
    <Head title="Emergency Users" />

    <EmergencyLayout :status="status" logout-url="/emergency/logout">
        <PageHeader title="Emergency Users" description="Read-only local user projection view for emergency diagnostics." />

        <DataTable class="mt-8" :value="users" data-key="id" striped-rows>
            <Column field="id" header="ID" />
            <Column field="sso_user_id" header="SSO User ID" />
            <Column field="name" header="Name" />
            <Column field="email" header="Email" />
            <Column field="local_status" header="Local Status" />
            <Column field="last_authenticated_at" header="Last Authenticated" />
        </DataTable>
    </EmergencyLayout>
</template>
