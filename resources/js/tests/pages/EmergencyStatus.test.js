import { mount } from '@vue/test-utils';
import EmergencyStatusPage from '@/Pages/Emergency/Status.vue';

describe('Emergency/Status', () => {
    it('shows the emergency login action only when login is available', () => {
        const wrapper = mount(EmergencyStatusPage, {
            props: {
                status: {
                    state: 'emergency_active',
                    emergencyLoginAvailable: true,
                    capabilities: ['Emergency status', 'Read-only audit logs'],
                    bannerMessage: 'Emergency mode is active.',
                },
            },
            global: {
                stubs: {
                    Head: { template: '<div />' },
                    PageHeader: { template: '<div><slot /></div>' },
                    EmergencyLayout: { template: '<div><slot /></div>' },
                    Link: { template: '<a><slot /></a>' },
                },
            },
        });

        expect(wrapper.text()).toContain('Emergency login');
        expect(wrapper.text()).toContain('Read-only audit logs');
        expect(wrapper.text()).toContain('Open emergency login');
    });
});
