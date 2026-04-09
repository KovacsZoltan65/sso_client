<script setup>
import Dialog from "primevue/dialog";
import RoleForm from "./RoleForm.vue";

const props = defineProps({
    visible: { type: Boolean, default: false },
    form: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    submitting: { type: Boolean, default: false },
    permissionOptions: { type: Array, default: () => [] },
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
        :style="{ width: 'min(60rem, 96vw)' }"
        header="Role szerkesztese"
        @update:visible="emit('update:visible', $event)"
        @hide="closeDialog"
    >
        <RoleForm
            :form="form"
            :errors="errors"
            :submitting="submitting"
            :permissionOptions="permissionOptions"
            submit-label="Mentes"
            @submit="emit('submit')"
            @cancel="closeDialog"
        />
    </Dialog>
</template>
