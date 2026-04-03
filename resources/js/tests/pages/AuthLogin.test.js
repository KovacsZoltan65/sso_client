import { mount } from '@vue/test-utils';
import { vi } from 'vitest';
import LoginPage from '@/Pages/Auth/Login.vue';
import { setPageProps } from '../mocks/inertia';

describe('Auth/Login', () => {
    it('renders user-facing copy without technical diagnostics and starts the SSO redirect with a loading state', async () => {
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
        expect(wrapper.text()).toContain('Atiranyitas a bejelentkezeshez');
        expect(wrapper.text()).toContain('kozponti bejelentkezesre iranyitja');
        expect(wrapper.text()).toContain('Folytatas');
        expect(wrapper.text()).not.toContain('https://sso-server.test');
        expect(wrapper.text()).not.toContain('Redirect URI');
        expect(wrapper.text()).not.toContain('Scope-ok');
        expect(wrapper.text()).not.toContain('SSO CLIENT');

        await wrapper.get('button').trigger('click');

        expect(assignSpy).toHaveBeenCalledWith('/auth/sso/redirect');
        expect(wrapper.get('button').attributes('data-loading')).toBe('true');

        vi.unstubAllGlobals();
    });

    it('renders provider authorize refusal messages distinctly from generic internal failures', () => {
        setPageProps({
            auth: {},
            flash: {
                error: 'A bejelentkezes nem folytathato, mert ehhez az alkalmazashoz nincs hozzaferese.',
            },
            sso: {
                status: {},
            },
        });

        const wrapper = mount(LoginPage, {
            props: {
                loginUrl: '/auth/sso/redirect',
                status: null,
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
                                error: 'A bejelentkezes nem folytathato, mert ehhez az alkalmazashoz nincs hozzaferese.',
                            },
                        },
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('A bejelentkezes nem folytathato, mert ehhez az alkalmazashoz nincs hozzaferese.');
        expect(wrapper.text()).not.toContain('Valami hiba tortent');
    });
});
