import { defineComponent } from 'vue';
import { mount } from '@vue/test-utils';
import { useNavigation } from '@/Composables/useNavigation';
import { setPageProps } from '../mocks/inertia';
import hu from '../../../../lang/hu.json';

const NavigationProbe = defineComponent({
    setup() {
        const { items } = useNavigation();

        return {
            items,
        };
    },
    template: '<div><span v-for="item in items" :key="item.route">{{ item.label }}|</span></div>',
});

describe('useNavigation', () => {
    it('always includes base navigation items', () => {
        setPageProps({
            auth: {
                user: {
                    permissions: [],
                },
            },
            flash: {},
            sso: {
                status: {},
            },
        });

        const wrapper = mount(NavigationProbe);

        expect(wrapper.text()).toContain(hu['navigation.dashboard.label']);
        expect(wrapper.text()).toContain(hu['navigation.profile.label']);
        expect(wrapper.text()).toContain(hu['navigation.my_account.label']);
    });

    it('filters permission-gated items based on the authenticated user permissions', () => {
        setPageProps({
            auth: {
                user: {
                    permissions: ['companies.view', 'users.view', 'sso-status.view'],
                },
            },
            flash: {},
            sso: {
                status: {},
            },
        });

        const wrapper = mount(NavigationProbe);

        expect(wrapper.text()).toContain(hu['navigation.companies.label']);
        expect(wrapper.text()).toContain(hu['navigation.users.label']);
        expect(wrapper.text()).toContain(hu['navigation.connection_health.label']);
        expect(wrapper.text()).not.toContain(hu['navigation.roles.label']);
        expect(wrapper.text()).not.toContain(hu['navigation.permissions.label']);
        expect(wrapper.text()).not.toContain(hu['navigation.audit_logs.label']);
    });
});
