import axios from "axios";

export class RoleApiError extends Error {
    constructor(message, { status = 500, errors = {}, meta = {} } = {}) {
        super(message);
        this.name = "RoleApiError";
        this.status = status;
        this.errors = errors;
        this.meta = meta;
    }
}

function normalizeEnvelope(payload = {}) {
    return {
        message: payload.message ?? "A keres teljesult.",
        data: payload.data ?? {},
        meta: payload.meta ?? {},
        errors: payload.errors ?? {},
    };
}

function toRoleApiError(error) {
    const payload = normalizeEnvelope(error?.response?.data ?? {});

    return new RoleApiError(payload.message || "A role muvelet sikertelen volt.", {
        status: error?.response?.status ?? 500,
        errors: payload.errors,
        meta: payload.meta,
    });
}

function buildRoleUrl(rolesApi, roleId = null) {
    const baseUrl = rolesApi?.endpoints?.index ?? "/api/roles";

    if (roleId === null) {
        return baseUrl;
    }

    return `${baseUrl}/${roleId}`;
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
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
        });

        return normalizeEnvelope(response.data);
    } catch (error) {
        throw toRoleApiError(error);
    }
}

export function listRoles(rolesApi, params = {}) {
    return request("get", buildRoleUrl(rolesApi), { params });
}

export function createRole(rolesApi, payload) {
    return request("post", buildRoleUrl(rolesApi), { payload });
}

export function updateRole(rolesApi, roleId, payload) {
    return request("put", buildRoleUrl(rolesApi, roleId), { payload });
}

export function deleteRole(rolesApi, roleId) {
    return request("delete", buildRoleUrl(rolesApi, roleId));
}
