<script setup>
import Dialog from "primevue/dialog";
import { trans } from "laravel-vue-i18n";
import CompanyForm from "./CompanyForm.vue";

const props = defineProps({
    visible: { type: Boolean, default: false },
    form: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    submitting: { type: Boolean, default: false },
});

const emit = defineEmits(["update:visible", "submit"]);

function closeDialog() {
    emit("update:visible", false);
}
</script>

<template>
    <Dialog
        :visible="props.visible"
        modal
        dismissable-mask
        :style="{ width: 'min(42rem, 95vw)' }"
        :header="trans('companies.create_dialog_title')"
        @update:visible="emit('update:visible', $event)"
        @hide="closeDialog"
    >
        <CompanyForm
            :form="form"
            :errors="errors"
            :submitting="submitting"
            :submit-label="trans('common.create')"
            @submit="emit('submit')"
            @cancel="closeDialog"
        />
    </Dialog>
</template>
