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
                localLoginUrl: '/local-login',
                status: null,
                ssoStatus: {
                    serverBaseUrl: 'https://sso-server.test',
                    redirectUri: 'http://sso-client.test/auth/sso/callback',
                    scopes: ['openid', 'profile', 'email'],
                },
                decision: {
                    featureEnabled: false,
                    currentlyAllowed: false,
                    warning: null,
                    reachability: {
                        reachable: true,
                    },
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

    it('shows the fallback decision card only when the fallback path is currently allowed', () => {
        setPageProps({
            auth: {},
            flash: {},
            sso: {
                status: {},
            },
        });

        const wrapper = mount(LoginPage, {
            props: {
                loginUrl: '/auth/sso/redirect',
                localLoginUrl: '/local-login',
                status: null,
                ssoStatus: {
                    serverBaseUrl: 'https://sso-server.test',
                    redirectUri: 'http://sso-client.test/auth/sso/callback',
                    scopes: ['openid', 'profile', 'email'],
                },
                decision: {
                    featureEnabled: true,
                    currentlyAllowed: true,
                    warning: 'Fallback active.',
                    incidentId: 'INC-99',
                    reachability: {
                        reachable: false,
                    },
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
                            flash: {},
                        },
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Fallback active.');
        expect(wrapper.text()).toContain('INC-99');
        expect(wrapper.html()).toContain('href="/local-login"');
    });

    it('renders a stronger degraded warning when fallback is allowed because the SSO is degraded', () => {
        const wrapper = mount(LoginPage, {
            props: {
                loginUrl: '/auth/sso/redirect',
                localLoginUrl: '/local-login',
                status: null,
                ssoStatus: {
                    serverBaseUrl: 'https://sso-server.test',
                    redirectUri: 'http://sso-client.test/auth/sso/callback',
                    scopes: ['openid', 'profile', 'email'],
                },
                decision: {
                    featureEnabled: true,
                    currentlyAllowed: true,
                    fallbackReason: 'degraded_allowed',
                    warning: 'Degraded fallback active.',
                    incidentId: 'INC-DEG-1',
                    reachability: {
                        status: 'degraded',
                    },
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
                            flash: {},
                        },
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('reszben hibas allapotban van');
        expect(wrapper.html()).toContain('href="/local-login"');
    });

    it('renders a distinct maintenance warning when the SSO server is in maintenance mode', () => {
        const wrapper = mount(LoginPage, {
            props: {
                loginUrl: '/auth/sso/redirect',
                localLoginUrl: '/local-login',
                status: null,
                ssoStatus: {
                    serverBaseUrl: 'https://sso-server.test',
                    redirectUri: 'http://sso-client.test/auth/sso/callback',
                    scopes: ['openid', 'profile', 'email'],
                },
                decision: {
                    featureEnabled: false,
                    currentlyAllowed: false,
                    warning: null,
                    reachability: {
                        status: 'maintenance',
                        retryAfter: '60',
                    },
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
                            flash: {},
                        },
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('karbantartas alatt van');
        expect(wrapper.text()).toContain('Retry-After: 60');
    });

    it('renders a distinct unreachable warning when the SSO server cannot be reached', () => {
        const wrapper = mount(LoginPage, {
            props: {
                loginUrl: '/auth/sso/redirect',
                localLoginUrl: '/local-login',
                status: null,
                ssoStatus: {
                    serverBaseUrl: 'https://sso-server.test',
                    redirectUri: 'http://sso-client.test/auth/sso/callback',
                    scopes: ['openid', 'profile', 'email'],
                },
                decision: {
                    featureEnabled: false,
                    currentlyAllowed: false,
                    warning: null,
                    reachability: {
                        status: 'unreachable',
                    },
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
                            flash: {},
                        },
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('jelenleg nem erheto el');
    });
});
