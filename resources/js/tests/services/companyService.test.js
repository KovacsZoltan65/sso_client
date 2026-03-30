import { axiosMock } from '../mocks/axios';
import { CompanyApiError, createCompany, deleteCompany, listCompanies, updateCompany } from '@/Services/companyService';

const companiesApi = {
    endpoints: {
        index: '/api/companies',
    },
};

describe('companyService', () => {
    it('loads the companies envelope with query params', async () => {
        axiosMock.mockResolvedValueOnce({
            data: {
                message: 'Companies retrieved successfully.',
                data: {
                    items: [{ id: 1, name: 'Acme' }],
                },
                meta: {
                    pagination: {
                        total: 1,
                    },
                },
                errors: {},
            },
        });

        const envelope = await listCompanies(companiesApi, { search: 'Acme', page: 2 });

        expect(envelope.data.items).toHaveLength(1);
        expect(axiosMock).toHaveBeenCalledWith(expect.objectContaining({
            method: 'get',
            url: '/api/companies',
            params: {
                search: 'Acme',
                page: 2,
            },
        }));
    });

    it('creates, updates and deletes companies on their canonical endpoints', async () => {
        await createCompany(companiesApi, { name: 'Acme', code: 'ACME' });
        await updateCompany(companiesApi, 15, { name: 'Acme 2', code: 'ACME2' });
        await deleteCompany(companiesApi, 15);

        expect(axiosMock).toHaveBeenNthCalledWith(1, expect.objectContaining({
            method: 'post',
            url: '/api/companies',
            data: { name: 'Acme', code: 'ACME' },
        }));
        expect(axiosMock).toHaveBeenNthCalledWith(2, expect.objectContaining({
            method: 'put',
            url: '/api/companies/15',
            data: { name: 'Acme 2', code: 'ACME2' },
        }));
        expect(axiosMock).toHaveBeenNthCalledWith(3, expect.objectContaining({
            method: 'delete',
            url: '/api/companies/15',
        }));
    });

    it('normalizes envelope based api failures into a typed company error', async () => {
        axiosMock.mockRejectedValueOnce({
            response: {
                status: 422,
                data: {
                    message: 'Validation failed.',
                    data: {},
                    meta: {},
                    errors: {
                        code: ['A kod mar foglalt.'],
                    },
                },
            },
        });

        await expect(createCompany(companiesApi, { name: 'Acme', code: 'ACME' })).rejects.toEqual(expect.objectContaining({
            name: 'CompanyApiError',
            message: 'Validation failed.',
            status: 422,
            errors: {
                code: ['A kod mar foglalt.'],
            },
        }));
    });

    it('exposes a dedicated error type for callers that need structured handling', () => {
        const error = new CompanyApiError('boom', {
            status: 403,
            errors: { company: ['Forbidden.'] },
        });

        expect(error.status).toBe(403);
        expect(error.errors.company[0]).toBe('Forbidden.');
    });
});
