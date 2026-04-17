import { mount } from "@vue/test-utils";
import WelcomePage from "@/Pages/Welcome.vue";
import { setPageProps } from "../mocks/inertia";

describe("Welcome", () => {
    it("shows the SSO login entry point for guests", () => {
        setPageProps({
            auth: {
                isAuthenticated: false,
                isGuest: true,
                user: null,
                loginUrl: "/login",
                logoutUrl: "/auth/logout",
            },
            flash: {},
            sso: {
                status: {},
            },
        });

        const wrapper = mount(WelcomePage, {
            props: {
                appName: "SSO Client",
                canLogin: true,
                canRegister: false,
            },
        });

        expect(wrapper.text()).toContain("SSO bejelentkezés");
        expect(wrapper.text()).not.toContain("Aktiv session:");
        expect(wrapper.html()).toContain('href="/login"');
    });

    it("shows dashboard and logout actions plus the active session summary for authenticated users", () => {
        setPageProps({
            auth: {
                isAuthenticated: true,
                isGuest: false,
                user: {
                    name: "SSO User",
                    email: "sso.user@example.test",
                },
                loginUrl: "/login",
                logoutUrl: "/auth/logout",
            },
            flash: {},
            sso: {
                status: {},
            },
        });

        const wrapper = mount(WelcomePage, {
            props: {
                appName: "SSO Client",
                canLogin: true,
                canRegister: false,
            },
        });

        expect(wrapper.text()).toContain("Dashboard");
        expect(wrapper.text()).toContain("Kijelentkezés");
        expect(wrapper.text()).toContain(
            "Aktiv session: SSO User (sso.user@example.test)",
        );
        expect(wrapper.html()).toContain('data-method="post"');
    });
});
