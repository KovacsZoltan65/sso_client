<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, required: true },
    tags: { type: Array, default: () => [] },
});
</script>

<template>
    <section class="shell-card p-8">
        <div class="mb-4 flex flex-wrap gap-2">
            <span
                v-for="tag in tags"
                :key="tag"
                class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-slate-500"
            >
                {{ tag }}
            </span>
        </div>

        <h2 class="text-2xl font-semibold text-slate-900">{{ title }}</h2>
        <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-600">{{ description }}</p>
    </section>
</template>
