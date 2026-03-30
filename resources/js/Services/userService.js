import axios from 'axios';

export class UserApiError extends Error {
    constructor(message, { status = 500, errors = {}, meta = {} } = {}) {
        super(message);
        this.name = 'UserApiError';
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

function toUserApiError(error) {
    const payload = normalizeEnvelope(error?.response?.data ?? {});

    return new UserApiError(
        payload.message || 'A felhasznalo muvelet sikertelen volt.',
        {
            status: error?.response?.status ?? 500,
            errors: payload.errors,
            meta: payload.meta,
        },
    );
}

function buildUserUrl(usersApi, userId = null) {
    const baseUrl = usersApi?.endpoints?.index ?? '/api/users';

    if (userId === null) {
        return baseUrl;
    }

    return `${baseUrl}/${userId}`;
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
        throw toUserApiError(error);
    }
}

export function listUsers(usersApi, params = {}) {
    return request('get', buildUserUrl(usersApi), { params });
}

export function showUser(usersApi, userId) {
    return request('get', buildUserUrl(usersApi, userId));
}

export function updateUser(usersApi, userId, payload) {
    return request('put', buildUserUrl(usersApi, userId), { payload });
}
