import { mount } from '@vue/test-utils';
import ConfirmPasswordPage from '@/Pages/Auth/ConfirmPassword.vue';
import ForgotPasswordPage from '@/Pages/Auth/ForgotPassword.vue';
import RegisterPage from '@/Pages/Auth/Register.vue';
import ResetPasswordPage from '@/Pages/Auth/ResetPassword.vue';
import VerifyEmailPage from '@/Pages/Auth/VerifyEmail.vue';
import { setPageProps } from '../mocks/inertia';
import en from '../../../../lang/en.json';

function mountWithGuestLayout(component, props = {}) {
    setPageProps({
        auth: {},
        flash: {},
        sso: { status: {} },
        locale: { current: 'en', fallback: 'hu', available: ['hu', 'en'] },
    });

    return mount(component, {
        props,
        global: {
            stubs: {
                GuestLayout: {
                    template: '<div><slot /></div>',
                },
            },
        },
    });
}

describe('Auth page localization', () => {
    it('renders localized english copy on the verify email page', () => {
        const wrapper = mountWithGuestLayout(VerifyEmailPage, { status: 'verification-link-sent' });

        expect(wrapper.text()).toContain(en['auth.verify_email.title']);
        expect(wrapper.text()).toContain(en['auth.verify_email.description']);
        expect(wrapper.text()).toContain(en['auth.verify_email.link_sent']);
        expect(wrapper.text()).toContain(en['auth.verify_email.resend_cta']);
        expect(wrapper.text()).toContain(en['common.logout']);
    });

    it('renders localized english copy on the forgot/reset/register/confirm pages', () => {
        const forgot = mountWithGuestLayout(ForgotPasswordPage);
        const reset = mountWithGuestLayout(ResetPasswordPage, { email: 'jane@example.test', token: 'reset-token' });
        const confirm = mountWithGuestLayout(ConfirmPasswordPage);
        const register = mountWithGuestLayout(RegisterPage);

        expect(forgot.text()).toContain(en['auth.forgot_password_page.title']);
        expect(forgot.text()).toContain(en['auth.forgot_password_page.submit']);

        expect(reset.text()).toContain(en['auth.reset_password_page.title']);
        expect(reset.text()).toContain(en['profile.confirm_password']);
        expect(reset.text()).toContain(en['auth.reset_password_page.submit']);

        expect(confirm.text()).toContain(en['auth.confirm_password_page.title']);
        expect(confirm.text()).toContain(en['auth.confirm_password_page.submit']);

        expect(register.text()).toContain(en['auth.register_page.title']);
        expect(register.text()).toContain(en['auth.register_page.submit']);
        expect(register.text()).toContain(en['auth.register_page.already_have_account']);
        expect(register.text()).toContain(en['auth.sign_in']);
    });
});
