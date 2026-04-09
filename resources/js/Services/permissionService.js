import axios from "axios";

export class PermissionApiError extends Error {
    constructor(message, { status = 500, errors = {}, meta = {} } = {}) {
        super(message);
        this.name = "PermissionApiError";
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

function toPermissionApiError(error) {
    const payload = normalizeEnvelope(error?.response?.data ?? {});

    return new PermissionApiError(payload.message || "A permission muvelet sikertelen volt.", {
        status: error?.response?.status ?? 500,
        errors: payload.errors,
        meta: payload.meta,
    });
}

function buildPermissionUrl(permissionsApi, permissionId = null) {
    const baseUrl = permissionsApi?.endpoints?.index ?? "/api/permissions";

    if (permissionId === null) {
        return baseUrl;
    }

    return `${baseUrl}/${permissionId}`;
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
        throw toPermissionApiError(error);
    }
}

export function listPermissions(permissionsApi, params = {}) {
    return request("get", buildPermissionUrl(permissionsApi), { params });
}

export function createPermission(permissionsApi, payload) {
    return request("post", buildPermissionUrl(permissionsApi), { payload });
}

export function updatePermission(permissionsApi, permissionId, payload) {
    return request("put", buildPermissionUrl(permissionsApi, permissionId), { payload });
}

export function deletePermission(permissionsApi, permissionId) {
    return request("delete", buildPermissionUrl(permissionsApi, permissionId));
}
