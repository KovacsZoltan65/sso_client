import { mount } from "@vue/test-utils";
import DashboardPage from "@/Pages/Dashboard.vue";
import { setPageProps } from "../mocks/inertia";
import en from "../../../../lang/en.json";

function mountPage(
    locale = { current: "hu", fallback: "en", available: ["hu", "en"] },
    overrides = {},
) {
    setPageProps({
        auth: {
            user: {
                permissions: ["dashboard.view"],
            },
        },
        flash: {},
        sso: {
            status: {
                message: "Central login is configured.",
            },
        },
        locale,
    });

    return mount(DashboardPage, {
        props: {
            stats: {
                users: 12,
                roles: 4,
                permissions: 18,
                activityEntries: 6,
            },
            recentUsers: [
                {
                    id: 1,
                    name: "Jane Doe",
                    email: "jane@example.test",
                    roles: [],
                },
            ],
            recentActivity: [
                {
                    id: 1,
                    description: "User record created",
                    event: "",
                    created_at: "2026-04-15 08:30:00",
                },
            ],
            ssoStatus: {
                mode: "Connected",
                localAuthEnabled: true,
            },
            userContext: {
                name: "Admin User",
                roles: ["Administrator"],
            },
            ...overrides,
        },
        global: {
            stubs: {
                AuthenticatedLayout: {
                    template: '<div><slot name="header" /><slot /></div>',
                },
                PageHeader: {
                    props: ["title", "description"],
                    template:
                        "<section><h1>{{ title }}</h1><p>{{ description }}</p></section>",
                },
                StatCard: {
                    props: ["label", "value"],
                    template:
                        "<article><span>{{ label }}</span><strong>{{ value }}</strong></article>",
                },
            },
        },
    });
}

describe("Dashboard", () => {
    it("renders localized english labels for dashboard sections and fallbacks", () => {
        const wrapper = mountPage({
            current: "en",
            fallback: "hu",
            available: ["hu", "en"],
        });

        expect(wrapper.text()).toContain(en["navigation.dashboard.label"]);
        expect(wrapper.text()).toContain(
            en["navigation.dashboard.description"],
        );
        expect(wrapper.text()).toContain(en["dashboard.sso_mode"]);
        expect(wrapper.text()).toContain(en["dashboard.local_auth_fallback"]);
        expect(wrapper.text()).toContain(en["dashboard.recent_activity"]);
        expect(wrapper.text()).toContain(en["dashboard.event_pending"]);
        expect(wrapper.text()).toContain(en["common.allowed"]);
        expect(wrapper.text()).toContain(en["common.no_assigned_roled"]);
    });
});
