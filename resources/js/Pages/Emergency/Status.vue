<script setup>
import PageHeader from '@/Components/PageHeader.vue';
import EmergencyLayout from '@/Layouts/EmergencyLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import Button from 'primevue/button';

defineProps({
    status: { type: Object, required: true },
});
</script>

<template>
    <Head title="Emergency Status" />

    <EmergencyLayout :status="status">
        <PageHeader
            title="Emergency Status"
            description="This page shows the break-glass state, SSO reachability and the limited capabilities available during degraded operations."
        />

        <div class="mt-8 space-y-6">
            <div class="rounded-3xl border border-white/10 bg-white/5 p-5">
                <p class="text-sm font-semibold text-white">Current capability set</p>
                <ul class="mt-4 space-y-2 text-sm text-slate-300">
                    <li v-for="capability in status.capabilities" :key="capability">{{ capability }}</li>
                </ul>
            </div>

            <div class="rounded-3xl border border-white/10 bg-white/5 p-5">
                <p class="text-sm font-semibold text-white">Emergency login</p>
                <p class="mt-3 text-sm leading-7 text-slate-300">
                    Emergency login is available only when the feature is enabled and emergency mode has been explicitly activated.
                </p>

                <div class="mt-5">
                    <Link v-if="status.emergencyLoginAvailable" href="/emergency/login">
                        <Button label="Open emergency login" />
                    </Link>
                    <p v-else class="text-sm text-amber-200">
                        Emergency login is currently unavailable. Activation must be performed explicitly by an operator.
                    </p>
                </div>
            </div>
        </div>
    </EmergencyLayout>
</template>
