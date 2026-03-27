import { defineComponent } from 'vue';
import { mount } from '@vue/test-utils';
import { useAuth } from '@/Composables/useAuth';
import { setPageProps } from '../mocks/inertia';

const AuthProbe = defineComponent({
    setup() {
        return useAuth();
    },
    template: `
        <div>
            <span data-test="is-authenticated">{{ String(isAuthenticated) }}</span>
            <span data-test="is-guest">{{ String(isGuest) }}</span>
            <span data-test="user-email">{{ user?.email ?? '' }}</span>
            <span data-test="login-url">{{ loginUrl }}</span>
            <span data-test="reauth-url">{{ reauthUrl }}</span>
            <span data-test="logout-url">{{ logoutUrl }}</span>
        </div>
    `,
});

describe('useAuth', () => {
    it('exposes guest auth state and auth endpoints from shared page props', () => {
        setPageProps({
            auth: {
                isAuthenticated: false,
                isGuest: true,
                user: null,
                loginUrl: '/login',
                reauthUrl: '/auth/sso/redirect',
                logoutUrl: '/auth/logout',
            },
            flash: {},
            sso: {
                status: {},
            },
        });

        const wrapper = mount(AuthProbe);

        expect(wrapper.get('[data-test="is-authenticated"]').text()).toBe('false');
        expect(wrapper.get('[data-test="is-guest"]').text()).toBe('true');
        expect(wrapper.get('[data-test="user-email"]').text()).toBe('');
        expect(wrapper.get('[data-test="login-url"]').text()).toBe('/login');
        expect(wrapper.get('[data-test="reauth-url"]').text()).toBe('/auth/sso/redirect');
        expect(wrapper.get('[data-test="logout-url"]').text()).toBe('/auth/logout');
    });

    it('exposes authenticated auth state only when the shared user payload is present', () => {
        setPageProps({
            auth: {
                isAuthenticated: true,
                isGuest: false,
                user: {
                    name: 'SSO User',
                    email: 'sso.user@example.test',
                },
                loginUrl: '/login',
                reauthUrl: '/auth/sso/redirect',
                logoutUrl: '/auth/logout',
            },
            flash: {},
            sso: {
                status: {},
            },
        });

        const wrapper = mount(AuthProbe);

        expect(wrapper.get('[data-test="is-authenticated"]').text()).toBe('true');
        expect(wrapper.get('[data-test="is-guest"]').text()).toBe('false');
        expect(wrapper.get('[data-test="user-email"]').text()).toBe('sso.user@example.test');
    });
});
