<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    icon: { type: String, required: true },
});
</script>

<template>
    <div class="metric-card">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-slate-500">{{ label }}</p>
                <p class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ value }}</p>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-lg text-blue-600">
                <i :class="icon" />
            </div>
        </div>
    </div>
</template>
