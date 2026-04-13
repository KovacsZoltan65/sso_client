import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { setPageProps } from '../mocks/inertia';

describe('AuthenticatedLayout', () => {
    beforeEach(() => {
        setPageProps({
            auth: {
                isAuthenticated: true,
                isGuest: false,
                user: {
                    name: 'SSO User',
                    email: 'sso.user@example.test',
                    permissions: [],
                },
                loginUrl: '/login',
                reauthUrl: '/auth/sso/redirect',
                logoutUrl: '/auth/logout',
            },
            flash: {},
            sso: {
                status: {
                    message: 'Rendben',
                },
            },
        });
    });

    afterEach(() => {
        document.head.innerHTML = '';
        document.body.innerHTML = '';
    });

    it('submits logout as a real browser form post instead of an inertia xhr request', async () => {
        const meta = document.createElement('meta');
        meta.setAttribute('name', 'csrf-token');
        meta.setAttribute('content', 'csrf-test-token');
        document.head.appendChild(meta);

        const submitSpy = vi
            .spyOn(HTMLFormElement.prototype, 'submit')
            .mockImplementation(() => {});

        const wrapper = mount(AuthenticatedLayout, {
            global: {
                stubs: {
                    AppBrand: { template: '<div data-test="brand" />' },
                    AppTopbar: {
                        props: ['user'],
                        emits: ['logout', 'toggle-navigation'],
                        template: '<button data-test="logout" @click="$emit(\'logout\')">Logout</button>',
                    },
                    Toast: { template: '<div data-test="toast" />' },
                },
            },
            slots: {
                default: '<div>Page</div>',
            },
        });

        await wrapper.get('[data-test="logout"]').trigger('click');

        const form = document.body.querySelector('form');
        const tokenInput = form?.querySelector('input[name="_token"]');

        expect(form).not.toBeNull();
        expect(form?.getAttribute('method')).toBe('POST');
        expect(form?.getAttribute('action')).toBe('/auth/logout');
        expect(tokenInput?.getAttribute('value')).toBe('csrf-test-token');
        expect(submitSpy).toHaveBeenCalledTimes(1);

        submitSpy.mockRestore();
    });
});
