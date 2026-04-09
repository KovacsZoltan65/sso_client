import { redirectToReauthTarget, resolveReauthTarget } from '@/Services/reauthContract';
import { vi } from 'vitest';

describe('reauthContract', () => {
    it('prefers meta.reauth_to when the server provides the self-service reauth contract', () => {
        expect(resolveReauthTarget({
            meta: {
                reauth_to: '/login',
                redirect_to: '/fallback-login',
            },
        })).toBe('/login');
    });

    it('returns false when no redirect target is available', () => {
        expect(redirectToReauthTarget({}, vi.fn())).toBe(false);
    });
});
