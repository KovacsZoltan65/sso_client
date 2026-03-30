import { flushPromises, mount } from '@vue/test-utils';
import UsersIndex from '@/Pages/Users/Index.vue';
import UserEditDialog from '@/Pages/Users/Partials/UserEditDialog.vue';
import { toastAddMock } from '../setup';
import { setPageProps } from '../mocks/inertia';

const listUsersMock = vi.fn();
const showUserMock = vi.fn();
const updateUserMock = vi.fn();

vi.mock('@/Services/userService', () => ({
    UserApiError: class UserApiError extends Error {
        constructor(message, { status = 500, errors = {}, meta = {} } = {}) {
            super(message);
            this.name = 'UserApiError';
            this.status = status;
            this.errors = errors;
            this.meta = meta;
        }
    },
    listUsers: (...args) => listUsersMock(...args),
    showUser: (...args) => showUserMock(...args),
    updateUser: (...args) => updateUserMock(...args),
}));

vi.mock('@/Components/Admin/RowActionMenu.vue', async () => {
    const { defineComponent, h } = await import('vue');

    return {
        default: defineComponent({
            props: {
                items: {
                    type: Array,
                    default: () => [],
                },
            },
            setup(props) {
                return () => h('div', {}, (props.items ?? []).filter(Boolean).map((item) => h('button', {
                    type: 'button',
                    onClick: () => item.command?.(),
                }, item.label)));
            },
        }),
    };
});

const usersApi = {
    endpoints: {
        index: '/api/users',
    },
};

function makeEnvelope(items = []) {
    return {
        message: 'Users retrieved successfully.',
        data: { items },
        meta: {
            pagination: {
                current_page: 1,
                per_page: 10,
                total: items.length,
            },
        },
        errors: {},
    };
}

function makeUser(overrides = {}) {
    return {
        id: 7,
        sso_user_id: 'server-7',
        name: 'Remote User',
        email: 'remote@example.test',
        local_status: 'active',
        notes: null,
        last_authenticated_at: '2026-03-30 09:00:00',
        created_at: '2026-03-30 08:00:00',
        updated_at: '2026-03-30 08:30:00',
        can: {
            view: true,
            update: true,
        },
        ...overrides,
    };
}

function mountPage(permissions = { view: true, manage: true }) {
    setPageProps({
        auth: {
            user: {
                permissions: [],
            },
        },
        flash: {},
        sso: {
            status: {
                message: 'Rendben',
            },
        },
    });

    return mount(UsersIndex, {
        props: {
            usersApi,
            permissions,
        },
    });
}

function findButtonByText(wrapper, text) {
    return wrapper.findAll('button').find((node) => node.text() === text);
}

describe('Users/Index', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        listUsersMock.mockReset();
        showUserMock.mockReset();
        updateUserMock.mockReset();
        toastAddMock.mockReset();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('loads the users list on mount', async () => {
        listUsersMock.mockResolvedValueOnce(makeEnvelope([makeUser()]));

        const wrapper = mountPage();
        await flushPromises();

        expect(listUsersMock).toHaveBeenCalledWith(usersApi, expect.objectContaining({
            page: 1,
            per_page: 10,
            sort_field: 'created_at',
            sort_order: 'desc',
        }));
        expect(wrapper.text()).toContain('Remote User');
    });

    it('triggers a debounced refresh when the global search changes', async () => {
        listUsersMock.mockResolvedValue(makeEnvelope());

        const wrapper = mountPage();
        await flushPromises();

        await wrapper.get('input[placeholder="Kereses ID, SSO ID, nev vagy e-mail alapjan"]').setValue('server-7');

        vi.advanceTimersByTime(349);
        expect(listUsersMock).toHaveBeenCalledTimes(1);

        vi.advanceTimersByTime(1);
        await flushPromises();

        expect(listUsersMock).toHaveBeenCalledTimes(2);
        expect(listUsersMock).toHaveBeenLastCalledWith(usersApi, expect.objectContaining({
            global: 'server-7',
        }));
    });

    it('opens the view dialog after loading fresh user details', async () => {
        listUsersMock.mockResolvedValueOnce(makeEnvelope([makeUser()]));
        showUserMock.mockResolvedValueOnce({
            message: 'User retrieved successfully.',
            data: {
                user: makeUser({ notes: 'Fresh details' }),
            },
            meta: {},
            errors: {},
        });

        const wrapper = mountPage();
        await flushPromises();

        await findButtonByText(wrapper, 'Megtekintes').trigger('click');
        await flushPromises();

        expect(showUserMock).toHaveBeenCalledWith(usersApi, 7);
        expect(wrapper.text()).toContain('Fresh details');
    });

    it('updates local metadata and refreshes the list after save', async () => {
        listUsersMock
            .mockResolvedValueOnce(makeEnvelope([makeUser()]))
            .mockResolvedValueOnce(makeEnvelope([makeUser({ local_status: 'inactive', notes: 'Needs review' })]));
        showUserMock.mockResolvedValueOnce({
            message: 'User retrieved successfully.',
            data: {
                user: makeUser(),
            },
            meta: {},
            errors: {},
        });
        updateUserMock.mockResolvedValueOnce({
            message: 'User updated successfully.',
            data: {
                user: makeUser({ local_status: 'inactive', notes: 'Needs review' }),
            },
            meta: {},
            errors: {},
        });

        const wrapper = mountPage();
        await flushPromises();

        await findButtonByText(wrapper, 'Szerkesztes').trigger('click');
        await flushPromises();

        const editDialog = wrapper.findComponent(UserEditDialog);
        editDialog.props('form').local_status = 'inactive';
        editDialog.props('form').notes = 'Needs review';
        await wrapper.get('form').trigger('submit.prevent');
        await flushPromises();

        expect(updateUserMock).toHaveBeenCalledWith(usersApi, 7, {
            local_status: 'inactive',
            notes: 'Needs review',
        });
        expect(listUsersMock).toHaveBeenCalledTimes(2);
        expect(toastAddMock).toHaveBeenCalledWith(expect.objectContaining({
            severity: 'success',
        }));
    });

    it('hides the edit action when manage permission is missing', async () => {
        listUsersMock.mockResolvedValueOnce(makeEnvelope([makeUser({
            can: {
                view: true,
                update: false,
            },
        })]));

        const wrapper = mountPage({ view: true, manage: false });
        await flushPromises();

        expect(wrapper.text()).toContain('Megtekintes');
        expect(wrapper.text()).not.toContain('Szerkesztes');
    });
});
