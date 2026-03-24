<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
});
</script>

<template>
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-400">Application shell</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ title }}</h1>
            <p v-if="description" class="mt-3 max-w-3xl text-sm leading-7 text-slate-600">{{ description }}</p>
        </div>
        <slot />
    </div>
</template>
