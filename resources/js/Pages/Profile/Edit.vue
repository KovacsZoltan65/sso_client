<script setup>
import { getProfile, ProfileApiError, updatePassword, updateProfile } from '@/Services/profileApi';
import { useAuth } from '@/Composables/useAuth';
import { trans } from 'laravel-vue-i18n';
import PageHeader from '@/Components/PageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ProfileIdentitySection from './Partials/ProfileIdentitySection.vue';
import ProfilePasswordSection from './Partials/ProfilePasswordSection.vue';
import { Head } from '@inertiajs/vue3';
import Message from 'primevue/message';
import ProgressSpinner from 'primevue/progressspinner';
import { useToast } from 'primevue/usetoast';
import { onMounted, reactive, ref } from 'vue';

const props = defineProps({
    authUser: { type: Object, required: true },
    profileApi: { type: Object, required: true },
});

const toast = useToast();
const { updateUserProfile } = useAuth();

const loading = ref(true);
const loadingError = ref('');
const csrfToken = ref(null);

const profileForm = reactive({
    name: props.authUser.name ?? '',
    email: props.authUser.email ?? '',
    errors: {},
});

const passwordForm = reactive({
    current_password: '',
    password: '',
    password_confirmation: '',
    errors: {},
});

const savingProfile = ref(false);
const savingPassword = ref(false);

const apiAvailable = props.profileApi?.enabled === true
    && Boolean(props.profileApi?.endpoints?.show)
    && Boolean(props.profileApi?.endpoints?.update)
    && Boolean(props.profileApi?.endpoints?.updatePassword);

const loadProfile = async () => {
    if (!apiAvailable) {
        loading.value = false;
        return;
    }

    loading.value = true;
    loadingError.value = '';

    try {
        const envelope = await getProfile(props.profileApi);
        profileForm.name = envelope.data.name ?? '';
        profileForm.email = envelope.data.email ?? '';
        profileForm.errors = {};
        csrfToken.value = envelope.meta.csrf_token ?? null;
        updateUserProfile(envelope.data);
    } catch (error) {
        loadingError.value = error instanceof ProfileApiError
            ? error.message
            : trans('profile.load_failed');
    } finally {
        loading.value = false;
    }
};

const validateProfile = () => {
    const errors = {};

    if (!profileForm.name.trim()) {
        errors.name = 'Name is required.';
    }

    profileForm.errors = errors;

    return Object.keys(errors).length === 0;
};

const validatePassword = () => {
    const errors = {};

    if (!passwordForm.current_password) {
        errors.current_password = 'Current password is required.';
    }

    if (!passwordForm.password) {
        errors.password = 'New password is required.';
    } else if (passwordForm.password.length < 8) {
        errors.password = 'New password must be at least 8 characters.';
    }

    if (!passwordForm.password_confirmation) {
        errors.password_confirmation = 'Password confirmation is required.';
    } else if (passwordForm.password_confirmation !== passwordForm.password) {
        errors.password_confirmation = 'Password confirmation must match.';
    }

    passwordForm.errors = errors;

    return Object.keys(errors).length === 0;
};

const submitProfile = async () => {
    if (!apiAvailable || !validateProfile()) {
        return;
    }

    savingProfile.value = true;
    profileForm.errors = {};

    try {
        const envelope = await updateProfile(
            props.profileApi,
            { name: profileForm.name.trim() },
            csrfToken.value,
        );

        profileForm.name = envelope.data.name ?? profileForm.name;
        profileForm.email = envelope.data.email ?? profileForm.email;
        csrfToken.value = envelope.meta.csrf_token ?? csrfToken.value;
        updateUserProfile(envelope.data);

        toast.add({
            severity: 'success',
            summary: trans('profile.updated_summary'),
            detail: envelope.message,
            life: 3000,
        });
    } catch (error) {
        profileForm.errors = error instanceof ProfileApiError ? error.errors : {};

        toast.add({
            severity: 'error',
            summary: trans('profile.update_failed_summary'),
            detail: error instanceof ProfileApiError ? error.message : trans('profile.load_failed'),
            life: 4000,
        });
    } finally {
        savingProfile.value = false;
    }
};

const submitPassword = async () => {
    if (!apiAvailable || !validatePassword()) {
        return;
    }

    savingPassword.value = true;
    passwordForm.errors = {};

    try {
        const envelope = await updatePassword(
            props.profileApi,
            {
                current_password: passwordForm.current_password,
                password: passwordForm.password,
                password_confirmation: passwordForm.password_confirmation,
            },
            csrfToken.value,
        );

        csrfToken.value = envelope.meta.csrf_token ?? csrfToken.value;
        passwordForm.current_password = '';
        passwordForm.password = '';
        passwordForm.password_confirmation = '';

        toast.add({
            severity: 'success',
            summary: trans('profile.password_updated_summary'),
            detail: envelope.message,
            life: 3000,
        });
    } catch (error) {
        passwordForm.errors = error instanceof ProfileApiError ? error.errors : {};

        toast.add({
            severity: 'error',
            summary: trans('profile.password_update_failed_summary'),
            detail: error instanceof ProfileApiError ? error.message : trans('profile.password_update_failed_summary'),
            life: 4000,
        });
    } finally {
        savingPassword.value = false;
    }
};

onMounted(() => {
    loadProfile();
});
</script>

<template>
    <Head :title="trans('profile.title')" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader
                :title="trans('profile.title')"
                :description="trans('profile.description')"
            />
        </template>

        <div class="space-y-6">
            <Message severity="info">
                {{ trans('profile.info_banner') }}
            </Message>

            <section v-if="loading" class="shell-card flex items-center gap-4 p-6">
                <ProgressSpinner style="width: 2rem; height: 2rem" stroke-width="6" />
                <div>
                    <h2 class="text-base font-semibold text-slate-950">Loading profile</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ trans('profile.loading_description') }}</p>
                </div>
            </section>

            <Message v-else-if="loadingError" severity="error">
                {{ loadingError }}
            </Message>

            <template v-else>
                <ProfileIdentitySection
                    :form="profileForm"
                    :loading="savingProfile"
                    :disabled="savingPassword"
                    :api-available="apiAvailable"
                    @submit="submitProfile"
                />

                <ProfilePasswordSection
                    :form="passwordForm"
                    :loading="savingPassword"
                    :disabled="savingProfile"
                    :api-available="apiAvailable"
                    @submit="submitPassword"
                />
            </template>
        </div>
    </AuthenticatedLayout>
</template>
