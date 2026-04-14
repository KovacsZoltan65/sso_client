<script setup>
import Dialog from "primevue/dialog";
import { trans } from "laravel-vue-i18n";
import PermissionForm from "./PermissionForm.vue";

const props = defineProps({
    visible: { type: Boolean, default: false },
    form: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    submitting: { type: Boolean, default: false },
    permission: { type: Object, default: null },
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
        :header="trans('permissions.edit_dialog_title')"
        @update:visible="emit('update:visible', $event)"
        @hide="closeDialog"
    >
        <PermissionForm
            :form="form"
            :errors="errors"
            :submitting="submitting"
            :isProtected="Boolean(permission?.is_protected)"
            :submit-label="trans('common.save')"
            @submit="emit('submit')"
            @cancel="closeDialog"
        />
    </Dialog>
</template>
