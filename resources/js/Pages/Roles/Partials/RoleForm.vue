<script setup>
import GroupedCheckboxSelector from "@/Components/Admin/GroupedCheckboxSelector.vue";
import InputError from "@/Components/InputError.vue";
import Button from "primevue/button";
import InputText from "primevue/inputtext";
import Message from "primevue/message";
import Tag from "primevue/tag";

defineProps({
    form: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    submitting: { type: Boolean, default: false },
    submitLabel: { type: String, required: true },
    permissionOptions: { type: Array, default: () => [] },
    isProtected: { type: Boolean, default: false },
});

defineEmits(["submit", "cancel"]);
</script>

<template>
    <form class="space-y-5" @submit.prevent="$emit('submit')">
        <Message v-if="isProtected" severity="warn" :closable="false">
            <div class="flex flex-wrap items-center gap-2">
                <Tag value="Rendszer" severity="warn" />
                <span>A nev es a guard vedett. A jogosultsagok tovabbra is szerkeszthetok.</span>
            </div>
        </Message>

        <div class="grid gap-5 md:grid-cols-2">
            <div class="space-y-2">
                <label for="role-name" class="text-sm font-semibold text-slate-900">Role name</label>
                <InputText
                    id="role-name"
                    v-model="form.name"
                    fluid
                    autocomplete="off"
                    :readonly="isProtected"
                    :invalid="Boolean(errors.name?.length)"
                />
                <InputError :message="errors.name?.[0]" />
            </div>

            <div class="space-y-2">
                <label for="role-guard" class="text-sm font-semibold text-slate-900">Guard</label>
                <InputText
                    id="role-guard"
                    v-model="form.guard_name"
                    fluid
                    readonly
                    :invalid="Boolean(errors.guard_name?.length)"
                />
                <InputError :message="errors.guard_name?.[0]" />
            </div>
        </div>

        <div class="space-y-2">
            <GroupedCheckboxSelector
                v-model="form.permission_ids"
                :options="permissionOptions"
                fieldLabel="Permissions"
                searchPlaceholder="Kereses permission eroforras vagy muvelet szerint"
                emptyMessage="Nincs a keresesnek megfelelo permission."
                groupCountLabel="permission"
                :disabled="submitting"
            />
            <InputError :message="errors.permission_ids?.[0]" />
            <InputError :message="errors['permission_ids.0']?.[0]" />
        </div>

        <div class="flex justify-end gap-3 border-t border-slate-200 pt-4">
            <Button type="button" label="Megse" severity="secondary" text @click="$emit('cancel')" />
            <Button type="submit" :label="submitLabel" :loading="submitting" />
        </div>
    </form>
</template>
