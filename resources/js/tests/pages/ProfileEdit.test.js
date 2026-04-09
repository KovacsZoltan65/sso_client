import { flushPromises, mount } from '@vue/test-utils';
import ProfileEditPage from '@/Pages/Profile/Edit.vue';
import { axiosMock } from '../mocks/axios';
import { getPage, setPageProps } from '../mocks/inertia';
import { toastAddMock } from '../setup';

function profilePageProps() {
    return {
        auth: {
            isAuthenticated: true,
            isGuest: false,
            user: {
                name: 'Local Session Name',
                email: 'session@example.test',
                roles: ['admin'],
                permissions: [],
            },
            loginUrl: '/login',
            reauthUrl: '/auth/sso/redirect',
            logoutUrl: '/auth/logout',
        },
        flash: {},
        sso: {
            status: {},
        },
    };
}

describe('Profile/Edit', () => {
    it('loads the canonical profile from the sso server and syncs the shared auth state', async () => {
        setPageProps(profilePageProps());
        axiosMock.mockResolvedValueOnce({
            data: {
                message: 'Profile retrieved successfully.',
                data: {
                    name: 'Canonical User',
                    email: 'canonical@example.test',
                },
                meta: {
                    csrf_token: 'csrf-token',
                },
                errors: {},
            },
        });

        const wrapper = mount(ProfileEditPage, {
            props: {
                authUser: {
                    name: 'Local Session Name',
                    email: 'session@example.test',
                },
                profileApi: {
                    enabled: true,
                    endpoints: {
                        show: 'https://sso-server.test/api/profile',
                        update: 'https://sso-server.test/api/profile',
                        updatePassword: 'https://sso-server.test/api/profile/password',
                    },
                },
            },
            global: {
                stubs: {
                    AuthenticatedLayout: { template: '<div><slot name="header" /><slot /></div>' },
                    PageHeader: { template: '<div><slot /></div>' },
                },
            },
        });

        await flushPromises();

        expect(wrapper.text()).toContain('Profile details');
        expect(wrapper.get('#profile-name').element.value).toBe('Canonical User');
        expect(getPage().props.auth.user.name).toBe('Canonical User');
        expect(getPage().props.auth.user.email).toBe('canonical@example.test');
    });

    it('shows loading state on save and pushes success feedback when the remote update succeeds', async () => {
        setPageProps(profilePageProps());
        let resolveProfileUpdate;
        const pendingProfileUpdate = new Promise((resolve) => {
            resolveProfileUpdate = resolve;
        });

        axiosMock
            .mockResolvedValueOnce({
                data: {
                    message: 'Profile retrieved successfully.',
                    data: {
                        name: 'Canonical User',
                        email: 'canonical@example.test',
                    },
                    meta: {
                        csrf_token: 'csrf-token',
                    },
                    errors: {},
                },
            })
            .mockImplementationOnce(() => pendingProfileUpdate);

        const wrapper = mount(ProfileEditPage, {
            props: {
                authUser: {
                    name: 'Local Session Name',
                    email: 'session@example.test',
                },
                profileApi: {
                    enabled: true,
                    endpoints: {
                        show: 'https://sso-server.test/api/profile',
                        update: 'https://sso-server.test/api/profile',
                        updatePassword: 'https://sso-server.test/api/profile/password',
                    },
                },
            },
            global: {
                stubs: {
                    AuthenticatedLayout: { template: '<div><slot name="header" /><slot /></div>' },
                    PageHeader: { template: '<div><slot /></div>' },
                },
            },
        });

        await flushPromises();
        await wrapper.get('#profile-name').setValue('Updated Canonical User');
        await wrapper.get('form').trigger('submit');

        expect(wrapper.get('button').attributes('data-loading')).toBe('true');

        resolveProfileUpdate({
            data: {
                message: 'Profile updated successfully.',
                data: {
                    name: 'Updated Canonical User',
                    email: 'canonical@example.test',
                },
                meta: {
                    csrf_token: 'next-token',
                },
                errors: {},
            },
        });

        await flushPromises();

        expect(toastAddMock).toHaveBeenCalledWith(expect.objectContaining({
            severity: 'success',
            detail: 'Profile updated successfully.',
        }));
    });

    it('keeps password confirmation handling on the client before the remote request is sent', async () => {
        setPageProps(profilePageProps());
        axiosMock.mockResolvedValueOnce({
            data: {
                message: 'Profile retrieved successfully.',
                data: {
                    name: 'Canonical User',
                    email: 'canonical@example.test',
                },
                meta: {
                    csrf_token: 'csrf-token',
                },
                errors: {},
            },
        });

        const wrapper = mount(ProfileEditPage, {
            props: {
                authUser: {
                    name: 'Local Session Name',
                    email: 'session@example.test',
                },
                profileApi: {
                    enabled: true,
                    endpoints: {
                        show: 'https://sso-server.test/api/profile',
                        update: 'https://sso-server.test/api/profile',
                        updatePassword: 'https://sso-server.test/api/profile/password',
                    },
                },
            },
            global: {
                stubs: {
                    AuthenticatedLayout: { template: '<div><slot name="header" /><slot /></div>' },
                    PageHeader: { template: '<div><slot /></div>' },
                },
            },
        });

        await flushPromises();

        const passwordInputs = wrapper.findAll('input[type="password"]');
        await passwordInputs[0].setValue('password');
        await passwordInputs[1].setValue('new-password');
        await passwordInputs[2].setValue('different-password');

        const forms = wrapper.findAll('form');
        await forms[1].trigger('submit');

        expect(wrapper.text()).toContain('Password confirmation must match.');
        expect(axiosMock).toHaveBeenCalledTimes(1);
    });

    it('renders a safe toast when the remote profile update returns a forbidden response', async () => {
        setPageProps(profilePageProps());
        axiosMock
            .mockResolvedValueOnce({
                data: {
                    message: 'Profile retrieved successfully.',
                    data: {
                        name: 'Canonical User',
                        email: 'canonical@example.test',
                    },
                    meta: {
                        csrf_token: 'csrf-token',
                    },
                    errors: {},
                },
            })
            .mockRejectedValueOnce({
                response: {
                    status: 403,
                    data: {
                        message: 'Forbidden.',
                        data: [],
                        meta: {},
                        errors: {},
                    },
                },
            });

        const wrapper = mount(ProfileEditPage, {
            props: {
                authUser: {
                    name: 'Local Session Name',
                    email: 'session@example.test',
                },
                profileApi: {
                    enabled: true,
                    endpoints: {
                        show: 'https://sso-server.test/api/profile',
                        update: 'https://sso-server.test/api/profile',
                        updatePassword: 'https://sso-server.test/api/profile/password',
                    },
                },
            },
            global: {
                stubs: {
                    AuthenticatedLayout: { template: '<div><slot name="header" /><slot /></div>' },
                    PageHeader: { template: '<div><slot /></div>' },
                },
            },
        });

        await flushPromises();
        await wrapper.get('#profile-name').setValue('Blocked User');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(toastAddMock).toHaveBeenCalledWith(expect.objectContaining({
            severity: 'error',
            detail: 'Forbidden.',
        }));
    });
});
