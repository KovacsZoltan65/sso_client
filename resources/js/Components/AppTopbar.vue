<script setup>
import { router } from '@inertiajs/vue3';
import Avatar from 'primevue/avatar';
import Button from 'primevue/button';

defineProps({
    user: {
        type: Object,
        default: null,
    },
    navigationOpen: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['logout', 'toggle-navigation']);

const goToProfile = () => {
    router.get(route('profile.edit'));
};
</script>

<template>
    <div class="surface-card mb-6 flex flex-wrap items-center justify-between gap-4 px-5 py-4 sm:flex-nowrap">
        <div class="flex min-w-0 items-center gap-3">
            <Button
                class="lg:hidden"
                icon="pi pi-bars"
                severity="contrast"
                rounded
                text
                aria-label="Navigáció megnyitása"
                aria-controls="app-mobile-navigation"
                :aria-expanded="String(navigationOpen)"
                @click="emit('toggle-navigation')"
            />
            <div class="min-w-0">
                <div class="eyebrow">Application Shell</div>
                <div class="truncate text-lg font-semibold">Connected SSO workspace</div>
            </div>
        </div>

        <div class="ml-auto flex items-center gap-3">
            <div class="hidden text-right sm:block">
                <div class="text-sm font-semibold">{{ user?.name }}</div>
                <div class="text-xs text-slate-500">{{ user?.email }}</div>
            </div>
            <Avatar :label="user?.name?.charAt(0) ?? 'U'" shape="circle" class="bg-sky-100 text-sky-700" />
            <Button
                icon="pi pi-user"
                severity="secondary"
                text
                rounded
                aria-label="Profile"
                @click="goToProfile"
            />
            <Button icon="pi pi-sign-out" severity="secondary" text rounded aria-label="Logout" @click="emit('logout')" />
        </div>
    </div>
</template>
