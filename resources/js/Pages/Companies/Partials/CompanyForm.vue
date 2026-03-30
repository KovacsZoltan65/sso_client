<script setup>
import InputError from '@/Components/InputError.vue';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';

defineProps({
    form: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    submitting: { type: Boolean, default: false },
    submitLabel: { type: String, required: true },
});

defineEmits(['submit', 'cancel']);
</script>

<template>
    <form class="space-y-5" @submit.prevent="$emit('submit')">
        <div class="grid gap-5 md:grid-cols-2">
            <div class="space-y-2">
                <label for="company-name" class="text-sm font-semibold text-slate-900">Cegnev</label>
                <InputText id="company-name" v-model="form.name" fluid autocomplete="off" :invalid="Boolean(errors.name?.length)" />
                <InputError :message="errors.name?.[0]" />
            </div>

            <div class="space-y-2">
                <label for="company-code" class="text-sm font-semibold text-slate-900">Kod</label>
                <InputText id="company-code" v-model="form.code" fluid autocomplete="off" :invalid="Boolean(errors.code?.length)" />
                <InputError :message="errors.code?.[0]" />
            </div>

            <div class="space-y-2">
                <label for="company-email" class="text-sm font-semibold text-slate-900">E-mail</label>
                <InputText id="company-email" v-model="form.email" fluid autocomplete="off" :invalid="Boolean(errors.email?.length)" />
                <InputError :message="errors.email?.[0]" />
            </div>

            <div class="space-y-2">
                <label for="company-phone" class="text-sm font-semibold text-slate-900">Telefonszam</label>
                <InputText id="company-phone" v-model="form.phone" fluid autocomplete="off" :invalid="Boolean(errors.phone?.length)" />
                <InputError :message="errors.phone?.[0]" />
            </div>
        </div>

        <div class="space-y-2">
            <label for="company-address" class="text-sm font-semibold text-slate-900">Cim</label>
            <Textarea id="company-address" v-model="form.address" fluid auto-resize rows="4" :invalid="Boolean(errors.address?.length)" />
            <InputError :message="errors.address?.[0]" />
        </div>

        <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
            <Checkbox v-model="form.is_active" input-id="company-is-active" binary />
            <label for="company-is-active" class="text-sm font-medium text-slate-800">A ceg aktiv</label>
        </div>
        <InputError :message="errors.is_active?.[0]" />

        <div class="flex justify-end gap-3 border-t border-slate-200 pt-4">
            <Button type="button" label="Megse" severity="secondary" text @click="$emit('cancel')" />
            <Button type="submit" :label="submitLabel" :loading="submitting" />
        </div>
    </form>
</template>
