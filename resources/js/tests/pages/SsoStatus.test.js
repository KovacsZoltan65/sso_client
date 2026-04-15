import { mount } from '@vue/test-utils';
import SsoStatusPage from '@/Pages/Sso/Status.vue';
import { setPageProps } from '../mocks/inertia';
import en from '../../../../lang/en.json';

function mountPage(
    statusOverrides = {},
    locale = { current: 'en', fallback: 'hu', available: ['hu', 'en'] },
) {
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
        locale,
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

        expect(wrapper.text()).toContain(en['navigation.connection_health.label']);
        expect(wrapper.text()).toContain(en['sso_status.health_ready']);
        expect(wrapper.text()).toContain(en['sso_status.what_you_can_do_next']);
        expect(wrapper.text()).toContain(en['sso_status.open_my_account']);
        expect(wrapper.text()).toContain(en['sso_status.review_profile_settings']);
        expect(wrapper.text()).toContain(en['sso_status.technical_details_title']);
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

        expect(wrapper.text()).toContain(en['sso_status.health_attention']);
        expect(wrapper.text()).toContain(en['sso_status.missing']);
        expect(wrapper.text()).toContain(en['sso_status.next_step_setup_admin']);
        expect(wrapper.text()).toContain(en['sso_status.next_step_setup_fallback']);
    });
});
