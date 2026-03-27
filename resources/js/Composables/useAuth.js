import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useAuth() {
    const page = usePage();

    const auth = computed(() => page.props.auth ?? {});
    const user = computed(() => auth.value.user ?? null);
    const isAuthenticated = computed(() => Boolean(auth.value.isAuthenticated && user.value));
    const loginUrl = computed(() => auth.value.loginUrl ?? route('login'));
    const logoutUrl = computed(() => auth.value.logoutUrl ?? route('logout'));

    return {
        auth,
        user,
        isAuthenticated,
        loginUrl,
        logoutUrl,
    };
}
