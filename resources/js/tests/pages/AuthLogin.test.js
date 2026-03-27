import { mount } from '@vue/test-utils';
import { vi } from 'vitest';
import LoginPage from '@/Pages/Auth/Login.vue';
import { setPageProps } from '../mocks/inertia';

describe('Auth/Login', () => {
    it('renders flash error and starts the SSO redirect with a loading state', async () => {
        setPageProps({
            auth: {},
            flash: {
                error: 'Session expired.',
            },
            sso: {
                status: {},
            },
        });

        const assignSpy = vi.fn();
        vi.stubGlobal('location', {
            ...window.location,
            assign: assignSpy,
        });

        const wrapper = mount(LoginPage, {
            props: {
                loginUrl: '/auth/sso/redirect',
                status: null,
                ssoStatus: {
                    serverBaseUrl: 'https://sso-server.test',
                    redirectUri: 'http://sso-client.test/auth/sso/callback',
                    scopes: ['openid', 'profile', 'email'],
                },
            },
            global: {
                stubs: {
                    GuestLayout: {
                        template: '<div><slot /></div>',
                    },
                },
                mocks: {
                    $page: {
                        props: {
                            flash: {
                                error: 'Session expired.',
                            },
                        },
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Session expired.');
        expect(wrapper.text()).toContain('https://sso-server.test');

        await wrapper.get('button').trigger('click');

        expect(assignSpy).toHaveBeenCalledWith('/auth/sso/redirect');
        expect(wrapper.get('button').attributes('data-loading')).toBe('true');

        vi.unstubAllGlobals();
    });
});
