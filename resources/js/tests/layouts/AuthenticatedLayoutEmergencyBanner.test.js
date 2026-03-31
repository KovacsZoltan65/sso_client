import { mount } from '@vue/test-utils';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { setPageProps } from '../mocks/inertia';

describe('AuthenticatedLayout emergency banner', () => {
    it('renders the emergency banner when emergency mode is active', () => {
        setPageProps({
            auth: {
                user: { name: 'Jane Doe' },
                logoutUrl: '/auth/logout',
            },
            sso: {
                status: {
                    message: 'SSO is healthy.',
                },
            },
            emergency: {
                status: {
                    state: 'emergency_active',
                    bannerMessage: 'Limited emergency mode is active.',
                },
            },
        });

        const wrapper = mount(AuthenticatedLayout, {
            global: {
                stubs: {
                    AppBrand: { template: '<div>Brand</div>' },
                    AppTopbar: { template: '<div>Topbar</div>' },
                    Link: { template: '<a><slot /></a>' },
                    Toast: { template: '<div />' },
                },
            },
            slots: {
                default: '<div>Main content</div>',
            },
        });

        expect(wrapper.text()).toContain('Emergency mode active');
        expect(wrapper.text()).toContain('Limited emergency mode is active.');
    });
});
