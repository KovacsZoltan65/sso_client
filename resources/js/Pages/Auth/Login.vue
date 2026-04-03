<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head } from '@inertiajs/vue3';
import Button from 'primevue/button';
import { ref } from 'vue';

const props = defineProps({
    loginUrl: { type: String, required: true },
    status: { type: String, default: null },
});

const loading = ref(false);

function startSsoLogin() {
    loading.value = true;
    window.location.assign(props.loginUrl);
}
</script>

<template>
    <Head title="Sign in" />

    <GuestLayout>
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-400">Bejelentkezes</p>
            <h1 class="mt-3 text-3xl font-semibold text-slate-950">Atiranyitas a bejelentkezeshez</h1>
            <p class="mt-3 text-sm leading-7 text-slate-600">
                A folytatashoz a rendszer a kozponti bejelentkezesre iranyitja.
            </p>
        </div>

        <div v-if="status" class="mt-6 rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ status }}</div>
        <div v-if="$page.props.flash.error" class="mt-6 rounded-2xl bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $page.props.flash.error }}
        </div>

        <div class="mt-8 space-y-4">
            <Button
                type="button"
                label="Folytatas"
                icon="pi pi-sign-in"
                class="w-full"
                :loading="loading"
                @click="startSsoLogin"
            />

            <p class="text-sm leading-7 text-slate-500">
                A sikeres bejelentkezes utan automatikusan visszairanyitjuk az alkalmazasba.
            </p>
        </div>
    </GuestLayout>
</template>
