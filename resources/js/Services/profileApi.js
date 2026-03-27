import axios from 'axios';

export class ProfileApiError extends Error {
    constructor(message, { status = 500, errors = {}, meta = {} } = {}) {
        super(message);
        this.name = 'ProfileApiError';
        this.status = status;
        this.errors = errors;
        this.meta = meta;
    }
}

function normalizeEnvelope(payload = {}) {
    return {
        message: payload.message ?? 'Request completed.',
        data: payload.data ?? {},
        meta: payload.meta ?? {},
        errors: payload.errors ?? {},
    };
}

function toProfileApiError(error) {
    const payload = normalizeEnvelope(error?.response?.data ?? {});

    return new ProfileApiError(
        payload.message || 'Profile request failed.',
        {
            status: error?.response?.status ?? 500,
            errors: payload.errors,
            meta: payload.meta,
        },
    );
}

async function request(method, url, { payload = null, csrfToken = null } = {}) {
    try {
        const response = await axios({
            method,
            url,
            data: payload,
            withCredentials: true,
            headers: {
                Accept: 'application/json',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            },
        });

        return normalizeEnvelope(response.data);
    } catch (error) {
        throw toProfileApiError(error);
    }
}

export function getProfile(profileApi) {
    return request('get', profileApi.endpoints.show);
}

export function updateProfile(profileApi, payload, csrfToken) {
    return request('patch', profileApi.endpoints.update, { payload, csrfToken });
}

export function updatePassword(profileApi, payload, csrfToken) {
    return request('patch', profileApi.endpoints.updatePassword, { payload, csrfToken });
}
