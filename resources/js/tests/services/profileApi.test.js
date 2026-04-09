import { axiosMock } from '../mocks/axios';
import { getProfile, ProfileApiError, updatePassword, updateProfile } from '@/Services/profileApi';

const profileApi = {
    endpoints: {
        show: 'https://sso-server.test/api/profile',
        update: 'https://sso-server.test/api/profile',
        updatePassword: 'https://sso-server.test/api/profile/password',
    },
};

describe('profileApi', () => {
    it('loads the remote profile envelope from the sso server', async () => {
        axiosMock.mockResolvedValueOnce({
            data: {
                message: 'Profile retrieved successfully.',
                data: {
                    name: 'Remote User',
                    email: 'remote@example.test',
                },
                meta: {
                    csrf_token: 'csrf-token',
                },
                errors: {},
            },
        });

        const envelope = await getProfile(profileApi);

        expect(envelope.message).toBe('Profile retrieved successfully.');
        expect(envelope.data.name).toBe('Remote User');
        expect(envelope.meta.csrf_token).toBe('csrf-token');
        expect(axiosMock).toHaveBeenCalledWith(expect.objectContaining({
            method: 'get',
            url: 'https://sso-server.test/api/profile',
            withCredentials: true,
        }));
    });

    it('sends profile updates with the remote csrf token and preserves the response envelope', async () => {
        axiosMock.mockResolvedValueOnce({
            data: {
                message: 'Profile updated successfully.',
                data: {
                    name: 'Updated Remote User',
                    email: 'remote@example.test',
                },
                meta: {
                    csrf_token: 'next-token',
                },
                errors: {},
            },
        });

        const envelope = await updateProfile(profileApi, { name: 'Updated Remote User' }, 'csrf-token');

        expect(envelope.data.name).toBe('Updated Remote User');
        expect(axiosMock).toHaveBeenCalledWith(expect.objectContaining({
            method: 'patch',
            data: { name: 'Updated Remote User' },
            headers: expect.objectContaining({
                'X-CSRF-TOKEN': 'csrf-token',
            }),
        }));
    });

    it('normalizes validation failures for password updates into a typed api error', async () => {
        axiosMock.mockRejectedValueOnce({
            response: {
                status: 422,
                data: {
                    message: 'Validation failed.',
                    data: [],
                    meta: {},
                    errors: {
                        current_password: ['The password is incorrect.'],
                    },
                },
            },
        });

        await expect(updatePassword(profileApi, {
            current_password: 'wrong-password',
            password: 'new-password',
            password_confirmation: 'new-password',
        }, 'csrf-token')).rejects.toEqual(expect.objectContaining({
            name: 'ProfileApiError',
            message: 'Validation failed.',
            status: 422,
            errors: {
                current_password: ['The password is incorrect.'],
            },
        }));
    });

    it('preserves forbidden responses for callers that render safe authorization feedback', async () => {
        axiosMock.mockRejectedValueOnce({
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

        await expect(updateProfile(profileApi, { name: 'Blocked User' }, 'csrf-token')).rejects.toEqual(expect.objectContaining({
            name: 'ProfileApiError',
            message: 'Forbidden.',
            status: 403,
        }));
    });

    it('preserves generic server failures for callers that need a safe fallback message', async () => {
        axiosMock.mockRejectedValueOnce({
            response: {
                status: 500,
                data: {
                    message: 'Server error.',
                    data: [],
                    meta: {},
                    errors: {},
                },
            },
        });

        await expect(updatePassword(profileApi, {
            current_password: 'password',
            password: 'new-password',
            password_confirmation: 'new-password',
        }, 'csrf-token')).rejects.toEqual(expect.objectContaining({
            name: 'ProfileApiError',
            message: 'Server error.',
            status: 500,
        }));
    });

    it('exposes a dedicated error type for callers that want structured handling', () => {
        const error = new ProfileApiError('boom', {
            status: 403,
            errors: { profile: ['Forbidden.'] },
        });

        expect(error.status).toBe(403);
        expect(error.errors.profile[0]).toBe('Forbidden.');
    });
});
