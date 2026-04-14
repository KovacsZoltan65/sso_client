<script setup>
import Dialog from 'primevue/dialog';
import { trans } from 'laravel-vue-i18n';
import Button from 'primevue/button';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';

const props = defineProps({
    visible: { type: Boolean, default: false },
    form: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    submitting: { type: Boolean, default: false },
});

const emit = defineEmits(['update:visible', 'submit']);

const statusOptions = [
    { label: trans('common.active'), value: 'active' },
    { label: trans('common.inactive'), value: 'inactive' },
];

function closeDialog() {
    emit('update:visible', false);
}
</script>

<template>
    <Dialog
        :visible="visible"
        modal
        dismissable-mask
        :style="{ width: 'min(46rem, 95vw)' }"
        :header="trans('users.edit_dialog_title')"
        @update:visible="emit('update:visible', $event)"
        @hide="closeDialog"
    >
        <form class="space-y-6" @submit.prevent="emit('submit')">
            <section class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">
                    {{ trans('users.readonly_identity_fields') }}
                </p>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-slate-700">{{ trans('users.sso_user_id') }}</label>
                        <div class="mt-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-600">
                            {{ form.sso_user_id || '-' }}
                        </div>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">{{ trans('table.name') }}</label>
                        <div class="mt-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-600">
                            {{ form.name || '-' }}
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-sm font-medium text-slate-700">{{ trans('table.email') }}</label>
                        <div class="mt-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-600">
                            {{ form.email || '-' }}
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">
                    {{ trans('users.editable_local_fields') }}
                </p>
                <div class="mt-4 space-y-4">
                    <div>
                        <label class="text-sm font-medium text-slate-700" for="user-local-status">{{ trans('users.local_status') }}</label>
                        <Select
                            id="user-local-status"
                            v-model="form.local_status"
                            class="mt-2 w-full"
                            :options="statusOptions"
                            option-label="label"
                            option-value="value"
                        />
                        <small v-if="errors.local_status" class="mt-2 block text-red-600">{{ errors.local_status[0] }}</small>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-slate-700" for="user-notes">{{ trans('users.notes') }}</label>
                        <Textarea
                            id="user-notes"
                            v-model="form.notes"
                            rows="5"
                            class="mt-2 w-full"
                            auto-resize
                            :placeholder="trans('users.notes_placeholder')"
                        />
                        <small v-if="errors.notes" class="mt-2 block text-red-600">{{ errors.notes[0] }}</small>
                    </div>
                </div>
            </section>

            <div class="flex justify-end gap-3">
                <Button type="button" :label="trans('common.cancel')" severity="secondary" text @click="closeDialog" />
                <Button type="submit" :label="trans('common.save')" :loading="submitting" />
            </div>
        </form>
    </Dialog>
</template>
