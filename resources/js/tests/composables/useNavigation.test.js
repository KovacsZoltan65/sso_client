import { defineComponent } from 'vue';
import { mount } from '@vue/test-utils';
import { useNavigation } from '@/Composables/useNavigation';
import { setPageProps } from '../mocks/inertia';

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

        expect(wrapper.text()).toContain('Dashboard');
        expect(wrapper.text()).toContain('Profile');
        expect(wrapper.text()).toContain('My Account');
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

        expect(wrapper.text()).toContain('Companies');
        expect(wrapper.text()).toContain('Users');
        expect(wrapper.text()).toContain('Connection Health');
        expect(wrapper.text()).not.toContain('Roles');
        expect(wrapper.text()).not.toContain('Permissions');
        expect(wrapper.text()).not.toContain('Audit Logs');
    });
});
