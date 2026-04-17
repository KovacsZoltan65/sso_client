import { mount } from '@vue/test-utils';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import AdminTableSummary from '@/Components/Admin/AdminTableSummary.vue';
import { setPageProps } from '../mocks/inertia';
import en from '../../../../lang/en.json';

describe('GuestLayout and AdminTableSummary localization', () => {
    it('renders localized english guest layout helper copy', () => {
        setPageProps({
            auth: {},
            flash: {},
            sso: { status: {} },
            locale: { current: 'en', fallback: 'hu', available: ['hu', 'en'] },
        });

        const wrapper = mount(GuestLayout, {
            slots: {
                default: '<div>Auth slot</div>',
            },
            global: {
                stubs: {
                    AppBrand: {
                        template: '<div>Brand</div>',
                    },
                },
            },
        });

        expect(wrapper.text()).toContain(en['auth.guest_layout.title']);
        expect(wrapper.text()).toContain(en['auth.guest_layout.description']);
        expect(wrapper.text()).toContain(en['auth.guest_layout.step_sign_in_label']);
        expect(wrapper.text()).toContain(en['auth.guest_layout.step_continue_value']);
        expect(wrapper.text()).toContain(en['auth.guest_layout.mobile_badge']);
    });

    it('uses localized fallback labels in admin table summary', () => {
        setPageProps({
            auth: {},
            flash: {},
            sso: { status: {} },
            locale: { current: 'en', fallback: 'hu', available: ['hu', 'en'] },
        });

        const populated = mount(AdminTableSummary, {
            props: {
                page: 1,
                perPage: 10,
                total: 3,
            },
        });

        const empty = mount(AdminTableSummary, {
            props: {
                page: 1,
                perPage: 10,
                total: 0,
            },
        });

        expect(populated.text()).toContain(en['common.published']);
        expect(populated.text()).toContain(en['common.record']);
        expect(empty.text()).toContain(en['common.no_displayable_record']);
    });
});
