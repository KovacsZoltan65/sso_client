import axios from 'axios';
import { redirectToReauthTarget } from '@/Services/reauthContract';

export class ProfileApiError extends Error {
    constructor(message, { status = 500, errors = {}, meta = {} } = {}) {
        super(message);
        this.name = 'ProfileApiError';
        this.status = status;
        this.errors = errors;
        this.meta = meta;
    }
}

// Normalizes the server envelope so the page can treat profile read/update and
// password flows as one contract, even when an upstream failure omits optional
// keys.
function normalizeEnvelope(payload = {}) {
    return {
        message: payload.message ?? 'Request completed.',
        data: payload.data ?? {},
        meta: payload.meta ?? {},
        errors: payload.errors ?? {},
    };
}

// 401 handling is intentionally centralized here so page components do not have
// to guess reauthentication behavior. The server remains the source of truth
// for where the user should be redirected next.
function toProfileApiError(error) {
    const payload = normalizeEnvelope(error?.response?.data ?? {});
    const status = error?.response?.status ?? 500;
    if (status === 401 && typeof window !== 'undefined') {
        redirectToReauthTarget(payload);
    }

    return new ProfileApiError(
        payload.message || 'Profile request failed.',
        {
            status,
            errors: payload.errors,
            meta: payload.meta,
        },
    );
}

// Shared request wrapper for the thin self-service client. Persistence,
// validation, and audit logging stay on the server; this layer only forwards
// the contract and turns failures into a UI-friendly error object.
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
