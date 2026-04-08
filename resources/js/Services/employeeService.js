import axios from "axios";

export class EmployeeApiError extends Error {
    constructor(message, status = 500, errors = {}, meta = {}) {
        super(message);
        this.name = "EmployeeApiError";
        this.status = status;
        this.errors = errors;
        this.meta = meta;
    }
}

function normalizeError(error, fallbackMessage) {
    const response = error?.response;
    const payload = response?.data ?? {};

    return new EmployeeApiError(
        payload.message ?? fallbackMessage,
        response?.status ?? 500,
        payload.errors ?? {},
        payload.meta ?? {},
    );
}

export async function listEmployees(api, params = {}) {
    try {
        const response = await axios.get(api.endpoints.index, { params });
        const payload = response.data ?? {};

        return {
            message: payload.message ?? "Employees fetched successfully.",
            data: {
                items: payload.data ?? [],
            },
            meta: {
                pagination: {
                    current_page: payload.meta?.current_page ?? 1,
                    per_page: payload.meta?.per_page ?? 10,
                    total: payload.meta?.total ?? 0,
                    last_page: payload.meta?.last_page ?? 1,
                },
            },
            errors: payload.errors ?? null,
        };
    } catch (error) {
        throw normalizeError(
            error,
            "Az alkalmazottak betoltese sikertelen volt.",
        );
    }
}

export async function createEmployee(api, payload) {
    try {
        const response = await axios.post(api.endpoints.store, payload);
        return response.data ?? {};
    } catch (error) {
        throw normalizeError(
            error,
            "Az alkalmazott letrehozasa sikertelen volt.",
        );
    }
}

export async function updateEmployee(api, employeeId, payload) {
    try {
        const response = await axios.put(
            route("api.employees.update", employeeId),
            payload,
        );
        return response.data ?? {};
    } catch (error) {
        throw normalizeError(
            error,
            "Az alkalmazott modositasa sikertelen volt.",
        );
    }
}

export async function deleteEmployee(api, employeeId) {
    try {
        const response = await axios.delete(
            route("api.employees.destroy", employeeId),
        );
        return response.data ?? {};
    } catch (error) {
        throw normalizeError(error, "Az alkalmazott torlese sikertelen volt.");
    }
}
