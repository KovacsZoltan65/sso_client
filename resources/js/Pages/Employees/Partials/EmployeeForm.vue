<script setup>
import InputError from "@/Components/InputError.vue";
import Button from "primevue/button";
import Checkbox from "primevue/checkbox";
import InputText from "primevue/inputtext";
import Select from "primevue/select";

defineProps({
    companies: {
        type: Array,
        default: () => [],
    },
    form: {
        type: Object,
        required: true,
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
    submitting: {
        type: Boolean,
        default: false,
    },
    submitLabel: {
        type: String,
        required: true,
    },
});

defineEmits(["submit", "cancel"]);
</script>

<template>
    <form class="space-y-5" @submit.prevent="$emit('submit')">
        <div class="grid gap-5 md:grid-cols-2">
            <div class="space-y-2">
                <label class="text-sm font-semibold text-slate-900" for="employee-company-id">Ceg</label>
                <Select
                    id="employee-company-id"
                    v-model="form.company_id"
                    :options="companies"
                    optionLabel="name"
                    optionValue="id"
                    placeholder="Valassz ceget"
                    class="w-full"
                    :invalid="Boolean(errors.company_id?.length)"
                />
                <InputError :message="errors.company_id?.[0]" />
            </div>

            <div class="space-y-2">
                <label class="text-sm font-semibold text-slate-900" for="employee-number">Azonosito</label>
                <InputText
                    id="employee-number"
                    v-model="form.employee_number"
                    fluid
                    autocomplete="off"
                    :invalid="Boolean(errors.employee_number?.length)"
                />
                <InputError :message="errors.employee_number?.[0]" />
            </div>

            <div class="space-y-2">
                <label class="text-sm font-semibold text-slate-900" for="employee-name">Nev</label>
                <InputText
                    id="employee-name"
                    v-model="form.name"
                    fluid
                    autocomplete="off"
                    :invalid="Boolean(errors.name?.length)"
                />
                <InputError :message="errors.name?.[0]" />
            </div>

            <div class="space-y-2">
                <label class="text-sm font-semibold text-slate-900" for="employee-email">E-mail</label>
                <InputText
                    id="employee-email"
                    v-model="form.email"
                    fluid
                    autocomplete="off"
                    :invalid="Boolean(errors.email?.length)"
                />
                <InputError :message="errors.email?.[0]" />
            </div>

            <div class="space-y-2">
                <label class="text-sm font-semibold text-slate-900" for="employee-phone">Telefonszam</label>
                <InputText
                    id="employee-phone"
                    v-model="form.phone"
                    fluid
                    autocomplete="off"
                    :invalid="Boolean(errors.phone?.length)"
                />
                <InputError :message="errors.phone?.[0]" />
            </div>

            <div class="space-y-2">
                <label class="text-sm font-semibold text-slate-900" for="employee-position">Pozicio</label>
                <InputText
                    id="employee-position"
                    v-model="form.position"
                    fluid
                    autocomplete="off"
                    :invalid="Boolean(errors.position?.length)"
                />
                <InputError :message="errors.position?.[0]" />
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
            <div class="flex items-center gap-3">
                <Checkbox
                    v-model="form.is_active"
                    binary
                    inputId="employee-is-active"
                />
                <label for="employee-is-active" class="text-sm font-medium text-slate-800">Aktiv</label>
            </div>
        </div>
        <InputError :message="errors.is_active?.[0]" />

        <div class="flex justify-end gap-3 border-t border-slate-200 pt-4">
            <Button
                type="button"
                label="Megse"
                severity="secondary"
                text
                :disabled="submitting"
                @click="$emit('cancel')"
            />
            <Button type="submit" :label="submitLabel" :loading="submitting" />
        </div>
    </form>
</template>
