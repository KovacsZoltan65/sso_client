<script setup>
import AppBrand from '@/Components/AppBrand.vue';
import AppTopbar from '@/Components/AppTopbar.vue';
import { useAuth } from '@/Composables/useAuth';
import { useNavigation } from '@/Composables/useNavigation';
import { Link, router, usePage } from '@inertiajs/vue3';
import Toast from 'primevue/toast';
import { computed, ref, watch } from 'vue';

const page = usePage();
const drawerOpen = ref(false);
const { items } = useNavigation();
const { user, logoutUrl, sessionMode, fallback } = useAuth();

const ssoStatus = computed(() => page.props.sso.details);
const ssoReachability = computed(() => page.props.sso ?? {});
const fallbackBanner = computed(() => fallback.value?.banner ?? null);

const logout = () => {
    router.post(logoutUrl.value);
};

watch(
    () => page.url,
    () => {
        drawerOpen.value = false;
    },
);
</script>

<template>
    <div class="shell-grid">
        <Toast position="top-right" />

        <aside
            :class="drawerOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
            class="shell-gradient fixed inset-y-0 left-0 z-50 flex w-[18rem] flex-col px-6 py-6 text-white transition-transform duration-200 lg:sticky lg:top-0 lg:z-auto lg:h-screen"
        >
            <AppBrand />

            <div class="glass-panel mt-8 rounded-3xl p-4 text-sm text-white/80">
                <p class="text-xs uppercase tracking-[0.25em] text-white/60">SSO allapot</p>
                <p class="mt-3 font-medium">
                    <span v-if="ssoReachability.isMaintenance">Az SSO szerver karbantartas alatt van.</span>
                    <span v-else-if="!ssoReachability.isReachable">Az SSO szerver jelenleg nem erheto el.</span>
                    <span v-else>{{ ssoStatus.message }}</span>
                </p>
                <p v-if="ssoReachability.retryAfter" class="mt-2 text-xs uppercase tracking-[0.18em] text-white/60">
                    Retry-After: {{ ssoReachability.retryAfter }}
                </p>
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
        </aside>

        <div class="relative flex min-h-0 h-screen flex-col overflow-hidden bg-transparent px-4 py-4 sm:px-6 lg:px-8">
            <div
                v-if="drawerOpen"
                class="fixed inset-0 z-40 bg-slate-950/50 lg:hidden"
                @click="drawerOpen = false"
            />

            <AppTopbar
                class="flex-none"
                :user="user"
                :session-mode="sessionMode"
                @logout="logout"
                @toggle-navigation="drawerOpen = !drawerOpen"
            />

            <div
                v-if="fallbackBanner?.visible"
                class="mb-4 flex-none rounded-3xl border border-amber-300 bg-amber-50 px-5 py-4 text-amber-900"
            >
                <p class="text-sm font-semibold">{{ fallbackBanner.title }}</p>
                <p class="mt-1 text-sm">{{ fallbackBanner.message }}</p>
                <p v-if="fallbackBanner.fallbackReason" class="mt-2 text-xs uppercase tracking-[0.18em] text-amber-700">
                    Fallback reason: {{ fallbackBanner.fallbackReason }}
                </p>
                <p v-if="fallbackBanner.incidentId" class="mt-2 text-xs uppercase tracking-[0.18em] text-amber-700">
                    Incident: {{ fallbackBanner.incidentId }}
                </p>
            </div>

            <div v-if="$slots.header" class="mb-6 flex-none">
                <slot name="header" />
            </div>

            <main class="flex min-h-0 flex-1 flex-col">
                <slot />
            </main>
        </div>
    </div>
</template>
