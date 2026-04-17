import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, vi } from 'vitest';
import { nextTick } from 'vue';
import LoginPage from '@/Pages/Auth/Login.vue';
import { setPageProps } from '../mocks/inertia';
import { toastAddMock } from '../setup';
import hu from '../../../../lang/hu.json';

beforeEach(() => {
    vi.useFakeTimers();
});

afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
});

describe('Auth/Login', () => {
    it('renders user-facing copy without technical diagnostics and starts the SSO redirect automatically after a short delay', async () => {
        setPageProps({
            auth: {},
            flash: {
                success: null,
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
        expect(wrapper.text()).toContain(hu['auth.login.redirecting_title']);
        expect(wrapper.text()).toContain(hu['auth.login.description']);
        expect(wrapper.text()).toContain(hu['auth.login.redirecting_progress']);
        expect(wrapper.text()).toContain(hu['auth.login.redirect_cta']);
        expect(wrapper.text()).not.toContain('https://sso-server.test');
        expect(wrapper.text()).not.toContain('Redirect URI');
        expect(wrapper.text()).not.toContain('Scope-ok');
        expect(wrapper.text()).not.toContain('SSO CLIENT');
        expect(wrapper.find('[data-spinner="true"]').exists()).toBe(false);
        expect(wrapper.get('button').attributes('disabled')).toBeUndefined();
        expect(assignSpy).not.toHaveBeenCalled();
    });

    it('starts the automatic redirect only when the page is in a clean login state', async () => {
        setPageProps({
            auth: {},
            flash: {
                success: null,
                error: null,
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
            },
        });

        await nextTick();

        expect(wrapper.find('[data-spinner="true"]').exists()).toBe(true);
        expect(wrapper.get('button').attributes('disabled')).toBeDefined();

        await vi.advanceTimersByTimeAsync(600);

        expect(assignSpy).toHaveBeenCalledWith('/auth/sso/redirect');
    });

    it('does not auto redirect after a successful logout message is shown', async () => {
        setPageProps({
            auth: {},
            flash: {
                success: 'Sikeres kijelentkezes.',
                error: null,
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
            },
        });

        await vi.advanceTimersByTimeAsync(600);

        expect(wrapper.text()).toContain('Sikeres kijelentkezes.');
        expect(wrapper.find('[data-spinner="true"]').exists()).toBe(false);
        expect(assignSpy).not.toHaveBeenCalled();
    });

    it('renders provider authorize refusal messages distinctly from generic internal failures', () => {
        setPageProps({
            auth: {},
            flash: {
                success: null,
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

    it('keeps the user on the page and shows retry feedback when the redirect attempt fails', async () => {
        setPageProps({
            auth: {},
            flash: {
                success: null,
                error: null,
            },
            sso: {
                status: {},
            },
        });

        const assignSpy = vi.fn(() => {
            throw new Error('navigation blocked');
        });

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
                                success: null,
                                error: null,
                            },
                        },
                    },
                },
            },
        });

        await vi.advanceTimersByTimeAsync(600);

        expect(assignSpy).toHaveBeenCalledTimes(1);
        expect(wrapper.text()).toContain(hu['auth.login.redirect_failed']);
        expect(wrapper.text()).toContain(hu['common.retry']);
        expect(wrapper.get('button').attributes('disabled')).toBeUndefined();
        expect(toastAddMock).toHaveBeenCalledWith(expect.objectContaining({
            severity: 'error',
            summary: hu['auth.login.redirect_failed_summary'],
        }));

        assignSpy.mockImplementation(() => undefined);

        await wrapper.get('button:last-of-type').trigger('click');

        expect(assignSpy).toHaveBeenCalledTimes(2);
    });
});
