import { mount } from '@vue/test-utils';
import SsoStatusPage from '@/Pages/Sso/Status.vue';
import { setPageProps } from '../mocks/inertia';

function mountPage(statusOverrides = {}) {
    setPageProps({
        auth: {
            user: {
                permissions: ['sso-status.view'],
            },
        },
        flash: {},
        sso: {
            status: {
                message: 'Central login is configured.',
            },
        },
    });

    return mount(SsoStatusPage, {
        props: {
            status: {
                mode: 'Connected',
                message: 'Central login is configured.',
                configured: true,
                serverBaseUrl: 'https://sso.example.test',
                authorizeEndpoint: 'https://sso.example.test/oauth/authorize',
                tokenEndpoint: 'https://sso.example.test/oauth/token',
                userinfoEndpoint: 'https://sso.example.test/api/userinfo',
                redirectUri: 'https://client.example.test/auth/callback',
                scopes: ['openid', 'profile'],
                localAuthEnabled: true,
                ...statusOverrides,
            },
            capabilities: [
                'Redirect users to the SSO server',
                'Handle signed callback state',
            ],
        },
        global: {
            stubs: {
                AuthenticatedLayout: {
                    template: '<div><slot name="header" /><slot /></div>',
                },
                PageHeader: {
                    props: ['title', 'description'],
                    template: '<section><h1>{{ title }}</h1><p>{{ description }}</p></section>',
                },
                Link: {
                    props: ['href'],
                    template: '<a :href="href"><slot /></a>',
                },
            },
        },
    });
}

describe('Sso/Status', () => {
    it('renders a human-readable connection health summary with next steps', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain('Connection Health');
        expect(wrapper.text()).toContain('Ready to use');
        expect(wrapper.text()).toContain('What you can do next');
        expect(wrapper.text()).toContain('Open my account');
        expect(wrapper.text()).toContain('Review profile settings');
        expect(wrapper.text()).toContain('Technical details for support and integration review');
        expect(wrapper.find('div.min-h-0.flex-1.space-y-6.overflow-y-auto.pr-1').exists()).toBe(true);
    });

    it('shows guidance when key SSO configuration is missing', () => {
        const wrapper = mountPage({
            configured: false,
            mode: 'Setup required',
            authorizeEndpoint: null,
            tokenEndpoint: null,
            redirectUri: null,
            localAuthEnabled: false,
            message: 'SSO setup is incomplete.',
        });

        expect(wrapper.text()).toContain('Needs attention');
        expect(wrapper.text()).toContain('Missing');
        expect(wrapper.text()).toContain('Ask an administrator to verify the SSO server base URL and endpoints shown below.');
        expect(wrapper.text()).toContain('Keep local access available until the redirect and token exchange are confirmed.');
    });
});
