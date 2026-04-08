<script setup>
import { computed } from "vue";
import Button from "primevue/button";
import Checkbox from "primevue/checkbox";
import Dialog from "primevue/dialog";
import InputText from "primevue/inputtext";
import Select from "primevue/select";

const props = defineProps({
    visible: {
        type: Boolean,
        default: false,
    },
    mode: {
        type: String,
        default: "create",
    },
    employee: {
        type: Object,
        default: null,
    },
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
});

const emit = defineEmits(["update:visible", "submit", "close"]);

const isEdit = computed(() => props.mode === "edit");

const title = computed(() =>
    isEdit.value ? "Alkalmazott szerkesztése" : "Új alkalmazott"
);

function closeDialog() {
    emit("update:visible", false);
    emit("close");
}

function submitForm() {
    emit("submit");
}
</script>

<template>
    <Dialog
        :visible="visible"
        modal
        :header="title"
        :style="{ width: '42rem' }"
        @update:visible="emit('update:visible', $event)"
        @hide="closeDialog"
    >
        <div class="grid">
            <div class="col-12 md:col-6">
                <label class="mb-2 block">Cég</label>
                <Select
                    v-model="form.company_id"
                    :options="companies"
                    optionLabel="name"
                    optionValue="id"
                    placeholder="Válassz céget"
                    class="w-full"
                />
                <small v-if="errors.company_id" class="p-error">
                    {{ errors.company_id[0] }}
                </small>
            </div>

            <div class="col-12 md:col-6">
                <label class="mb-2 block">Azonosító</label>
                <InputText v-model="form.employee_number" class="w-full" />
                <small v-if="errors.employee_number" class="p-error">
                    {{ errors.employee_number[0] }}
                </small>
            </div>

            <div class="col-12 md:col-6">
                <label class="mb-2 block">Név</label>
                <InputText v-model="form.name" class="w-full" />
                <small v-if="errors.name" class="p-error">
                    {{ errors.name[0] }}
                </small>
            </div>

            <div class="col-12 md:col-6">
                <label class="mb-2 block">E-mail</label>
                <InputText v-model="form.email" class="w-full" />
                <small v-if="errors.email" class="p-error">
                    {{ errors.email[0] }}
                </small>
            </div>

            <div class="col-12 md:col-6">
                <label class="mb-2 block">Telefonszám</label>
                <InputText v-model="form.phone" class="w-full" />
                <small v-if="errors.phone" class="p-error">
                    {{ errors.phone[0] }}
                </small>
            </div>

            <div class="col-12 md:col-6">
                <label class="mb-2 block">Pozíció</label>
                <InputText v-model="form.position" class="w-full" />
                <small v-if="errors.position" class="p-error">
                    {{ errors.position[0] }}
                </small>
            </div>

            <div class="col-12">
                <div class="flex items-center gap-2">
                    <Checkbox
                        v-model="form.is_active"
                        binary
                        inputId="employee_is_active"
                    />
                    <label for="employee_is_active">Aktív</label>
                </div>
                <small v-if="errors.is_active" class="p-error">
                    {{ errors.is_active[0] }}
                </small>
            </div>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <Button
                    label="Mégse"
                    severity="secondary"
                    text
                    :disabled="submitting"
                    @click="closeDialog"
                />
                <Button
                    :label="isEdit ? 'Mentés' : 'Létrehozás'"
                    :loading="submitting"
                    @click="submitForm"
                />
            </div>
        </template>
    </Dialog>
</template>
