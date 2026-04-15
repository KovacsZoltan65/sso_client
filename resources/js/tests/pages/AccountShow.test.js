import { mount } from '@vue/test-utils';
import AccountShowPage from '@/Pages/Account/Show.vue';
import { setPageProps } from '../mocks/inertia';
import en from '../../../../lang/en.json';

function mountPage(locale = { current: 'en', fallback: 'hu', available: ['hu', 'en'] }) {
    setPageProps({
        auth: {
            user: {
                permissions: ['account.show'],
            },
        },
        flash: {},
        sso: {
            status: {},
        },
        locale,
    });

    return mount(AccountShowPage, {
        props: {
            account: {
                name: 'Jane Doe',
                email: 'jane@example.test',
                roles: ['Administrator', 'Auditor'],
                permissions: ['users.view', 'roles.view'],
            },
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
            },
        },
    });
}

describe('Account/Show', () => {
    it('renders localized english labels for the account summary page', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(en['account.title']);
        expect(wrapper.text()).toContain(en['account.description']);
        expect(wrapper.text()).toContain(en['account.identity']);
        expect(wrapper.text()).toContain(en['account.authorization_summary']);
        expect(wrapper.text()).toContain(en['account.granted_permissions']);
        expect(wrapper.text()).toContain('Jane Doe');
        expect(wrapper.text()).toContain('users.view');
    });
});
