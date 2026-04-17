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
        expect(wrapper.text()).toContain('Profil');
        expect(wrapper.text()).toContain('Fiókom');
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

        expect(wrapper.text()).toContain('Cégek');
        expect(wrapper.text()).toContain('Felhasználók');
        expect(wrapper.text()).toContain('Kapcsolat állapota');
        expect(wrapper.text()).not.toContain('Szerepkörök');
        expect(wrapper.text()).not.toContain('Jogosultságok');
        expect(wrapper.text()).not.toContain('Audit naplók');
    });
});
