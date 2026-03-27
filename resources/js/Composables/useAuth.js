import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useAuth() {
    const page = usePage();

    const auth = computed(() => page.props.auth ?? {});
    const user = computed(() => auth.value.user ?? null);
    const isAuthenticated = computed(() => Boolean(auth.value.isAuthenticated && user.value));
    const isGuest = computed(() => Boolean(auth.value.isGuest ?? !isAuthenticated.value));
    const loginUrl = computed(() => auth.value.loginUrl ?? route('login'));
    const reauthUrl = computed(() => auth.value.reauthUrl ?? route('auth.sso.redirect'));
    const logoutUrl = computed(() => auth.value.logoutUrl ?? route('logout'));
    const updateUserProfile = (profile) => {
        if (!user.value || !profile) {
            return;
        }

        page.props.auth = {
            ...auth.value,
            user: {
                ...user.value,
                name: profile.name ?? user.value.name,
                email: profile.email ?? user.value.email,
            },
        };
    };

    return {
        auth,
        user,
        isAuthenticated,
        isGuest,
        loginUrl,
        reauthUrl,
        logoutUrl,
        updateUserProfile,
    };
}
