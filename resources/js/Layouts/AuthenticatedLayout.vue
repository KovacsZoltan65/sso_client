<script setup>
import AppBrand from '@/Components/AppBrand.vue';
import { useAuth } from '@/Composables/useAuth';
import { useNavigation } from '@/Composables/useNavigation';
import { Link, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import { computed, ref } from 'vue';

const page = usePage();
const drawerOpen = ref(false);
const { items } = useNavigation();
const { user, logoutUrl } = useAuth();

const ssoStatus = computed(() => page.props.sso.status);
</script>

<template>
    <div class="app-shell lg:flex">
        <aside
            :class="drawerOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
            class="shell-gradient fixed inset-y-0 left-0 z-40 flex w-80 flex-col px-6 py-6 text-white transition-transform duration-200 lg:sticky lg:top-0 lg:h-screen"
        >
            <AppBrand />

            <div class="glass-panel mt-8 rounded-3xl p-4 text-sm text-white/80">
                <p class="text-xs uppercase tracking-[0.25em] text-white/60">SSO allapot</p>
                <p class="mt-3 font-medium">{{ ssoStatus.message }}</p>
            </div>

            <nav class="mt-8 flex-1 space-y-2">
                <Link
                    v-for="item in items"
                    :key="item.route"
                    :href="route(item.route)"
                    class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium text-white/78 transition hover:bg-white/10 hover:text-white"
                    :class="{ 'menu-item-active': route().current(item.route) }"
                    @click="drawerOpen = false"
                >
                    <i :class="item.icon" class="text-base" />
                    <span>{{ item.label }}</span>
                </Link>
            </nav>

            <div class="glass-panel rounded-3xl p-4">
                <p class="text-xs uppercase tracking-[0.25em] text-white/60">Bejelentkezve</p>
                <p class="mt-3 text-lg font-semibold text-white">{{ user?.name }}</p>
                <p class="mt-1 text-sm text-white/70">{{ user?.email }}</p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <Tag
                        v-for="role in user?.roles || []"
                        :key="role"
                        :value="role"
                        severity="contrast"
                    />
                </div>

                <Link
                    :href="logoutUrl"
                    method="post"
                    as="button"
                    class="mt-5 inline-flex w-full items-center justify-center rounded-2xl bg-white px-4 py-3 text-sm font-semibold text-slate-900 transition hover:bg-slate-100"
                >
                    Kijelentkezes
                </Link>
            </div>
        </aside>

        <div class="min-h-screen flex-1 px-4 py-4 lg:px-6 lg:py-6">
            <div class="mx-auto max-w-7xl">
                <div class="shell-card mb-6 flex items-center justify-between px-5 py-4 lg:hidden">
                    <div>
                        <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Navigation</p>
                        <p class="text-lg font-semibold text-slate-950">sso_client</p>
                    </div>
                    <Button
                        icon="pi pi-bars"
                        rounded
                        text
                        severity="secondary"
                        @click="drawerOpen = !drawerOpen"
                    />
                </div>

                <div v-if="$slots.header" class="mb-6">
                    <slot name="header" />
                </div>

                <main>
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>
