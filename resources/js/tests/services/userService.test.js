import { axiosMock } from '../mocks/axios';
import { UserApiError, listUsers, showUser, updateUser } from '@/Services/userService';

describe('userService', () => {
    const usersApi = {
        endpoints: {
            index: '/api/users',
        },
    };

    it('loads the users envelope with query params', async () => {
        axiosMock.mockResolvedValueOnce({
            data: {
                message: 'Users retrieved successfully.',
                data: {
                    items: [{ id: 1, name: 'Alpha User' }],
                },
                meta: {
                    pagination: {
                        total: 1,
                    },
                },
                errors: {},
            },
        });

        const envelope = await listUsers(usersApi, { page: 2, global: 'alpha' });

        expect(axiosMock).toHaveBeenCalledWith(expect.objectContaining({
            method: 'get',
            url: '/api/users',
            params: { page: 2, global: 'alpha' },
        }));
        expect(envelope.data.items).toHaveLength(1);
    });

    it('loads and updates a single user on canonical endpoints', async () => {
        axiosMock
            .mockResolvedValueOnce({
                data: {
                    message: 'User retrieved successfully.',
                    data: { user: { id: 7, local_status: 'active' } },
                    meta: {},
                    errors: {},
                },
            })
            .mockResolvedValueOnce({
                data: {
                    message: 'User updated successfully.',
                    data: { user: { id: 7, local_status: 'inactive' } },
                    meta: {},
                    errors: {},
                },
            });

        await showUser(usersApi, 7);
        await updateUser(usersApi, 7, { local_status: 'inactive', notes: 'Flagged' });

        expect(axiosMock).toHaveBeenNthCalledWith(1, expect.objectContaining({
            method: 'get',
            url: '/api/users/7',
        }));
        expect(axiosMock).toHaveBeenNthCalledWith(2, expect.objectContaining({
            method: 'put',
            url: '/api/users/7',
            data: { local_status: 'inactive', notes: 'Flagged' },
        }));
    });

    it('normalizes envelope based api failures into a typed user error', async () => {
        axiosMock.mockRejectedValueOnce({
            response: {
                status: 422,
                data: {
                    message: 'Validation failed.',
                    data: {},
                    meta: {},
                    errors: {
                        local_status: ['The selected local status is invalid.'],
                    },
                },
            },
        });

        await expect(updateUser(usersApi, 5, { local_status: 'broken' }))
            .rejects
            .toEqual(expect.objectContaining({
                name: 'UserApiError',
                status: 422,
                errors: {
                    local_status: ['The selected local status is invalid.'],
                },
            }));

        expect(UserApiError).toBeTypeOf('function');
    });
});
