import axios from 'axios';

export class CompanyApiError extends Error {
    constructor(message, { status = 500, errors = {}, meta = {} } = {}) {
        super(message);
        this.name = 'CompanyApiError';
        this.status = status;
        this.errors = errors;
        this.meta = meta;
    }
}

function normalizeEnvelope(payload = {}) {
    return {
        message: payload.message ?? 'A keres teljesult.',
        data: payload.data ?? {},
        meta: payload.meta ?? {},
        errors: payload.errors ?? {},
    };
}

function toCompanyApiError(error) {
    const payload = normalizeEnvelope(error?.response?.data ?? {});

    return new CompanyApiError(
        payload.message || 'A ceg muvelet sikertelen volt.',
        {
            status: error?.response?.status ?? 500,
            errors: payload.errors,
            meta: payload.meta,
        },
    );
}

function buildCompanyUrl(companiesApi, companyId = null) {
    const baseUrl = companiesApi?.endpoints?.index ?? '/api/companies';

    if (companyId === null) {
        return baseUrl;
    }

    return `${baseUrl}/${companyId}`;
}

async function request(method, url, { payload = null, params = null } = {}) {
    try {
        const response = await axios({
            method,
            url,
            data: payload,
            params,
            withCredentials: true,
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        return normalizeEnvelope(response.data);
    } catch (error) {
        throw toCompanyApiError(error);
    }
}

export function listCompanies(companiesApi, params = {}) {
    return request('get', buildCompanyUrl(companiesApi), { params });
}

export function createCompany(companiesApi, payload) {
    return request('post', buildCompanyUrl(companiesApi), { payload });
}

export function updateCompany(companiesApi, companyId, payload) {
    return request('put', buildCompanyUrl(companiesApi, companyId), { payload });
}

export function deleteCompany(companiesApi, companyId) {
    return request('delete', buildCompanyUrl(companiesApi, companyId));
}
