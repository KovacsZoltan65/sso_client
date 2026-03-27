import { vi } from 'vitest';

export const axiosMock = vi.fn(async () => ({
    data: {
        message: 'ok',
        data: {},
        meta: {},
        errors: {},
    },
}));

export function resetAxiosMock() {
    axiosMock.mockReset();
    axiosMock.mockResolvedValue({
        data: {
            message: 'ok',
            data: {},
            meta: {},
            errors: {},
        },
    });
}
