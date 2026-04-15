import { vi } from 'vitest';

const defaultResponse = {
    data: {
        message: 'ok',
        data: {},
        meta: {},
        errors: {},
    },
};

export const axiosMock = vi.fn(async () => ({
    ...defaultResponse,
}));

export const axiosPost = vi.fn(async () => ({
    ...defaultResponse,
}));

axiosMock.post = axiosPost;

export function resetAxiosMock() {
    axiosMock.mockReset();
    axiosPost.mockReset();

    axiosMock.mockResolvedValue({
        ...defaultResponse,
    });
    axiosPost.mockResolvedValue({
        ...defaultResponse,
    });
    axiosMock.post = axiosPost;
}
