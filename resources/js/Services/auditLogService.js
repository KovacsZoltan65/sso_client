import axios from 'axios';

export class AuditLogApiError extends Error {
    constructor(message, { status = 500, errors = {}, meta = {} } = {}) {
        super(message);
        this.name = 'AuditLogApiError';
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

function toAuditLogApiError(error) {
    const payload = normalizeEnvelope(error?.response?.data ?? {});

    return new AuditLogApiError(
        payload.message || 'Az audit logok betoltese sikertelen volt.',
        {
            status: error?.response?.status ?? 500,
            errors: payload.errors,
            meta: payload.meta,
        },
    );
}

function buildAuditLogUrl(auditLogsApi) {
    return auditLogsApi?.endpoints?.index ?? '/api/audit-logs';
}

async function request(url, params = {}) {
    try {
        const response = await axios({
            method: 'get',
            url,
            params,
            withCredentials: true,
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        return normalizeEnvelope(response.data);
    } catch (error) {
        throw toAuditLogApiError(error);
    }
}

export function fetchAuditLogs(auditLogsApi, params = {}) {
    return request(buildAuditLogUrl(auditLogsApi), params);
}
