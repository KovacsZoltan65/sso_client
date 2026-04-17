import { flushPromises, mount } from "@vue/test-utils";
import RolesIndex from "@/Pages/Roles/Index.vue";
import CreateRoleDialog from "@/Pages/Roles/Partials/CreateRoleDialog.vue";
import EditRoleDialog from "@/Pages/Roles/Partials/EditRoleDialog.vue";
import DataTable from "primevue/datatable";
import { confirmRequireMock, toastAddMock } from "../setup";
import { setPageProps } from "../mocks/inertia";
import en from "../../../../lang/en.json";
import hu from "../../../../lang/hu.json";

const { listRolesMock, createRoleMock, updateRoleMock, deleteRoleMock } =
    vi.hoisted(() => ({
        listRolesMock: vi.fn(),
        createRoleMock: vi.fn(),
        updateRoleMock: vi.fn(),
        deleteRoleMock: vi.fn(),
    }));

vi.mock("@/Services/roleService", () => ({
    RoleApiError: class RoleApiError extends Error {
        constructor(message, { status = 500, errors = {}, meta = {} } = {}) {
            super(message);
            this.name = "RoleApiError";
            this.status = status;
            this.errors = errors;
            this.meta = meta;
        }
    },
    listRoles: (...args) => listRolesMock(...args),
    createRole: (...args) => createRoleMock(...args),
    updateRole: (...args) => updateRoleMock(...args),
    deleteRole: (...args) => deleteRoleMock(...args),
}));

const rolesApi = { endpoints: { index: "/api/roles" } };
const permissionOptions = [
    {
        value: 1,
        label: "View",
        helper: "companies.view",
        groupKey: "companies",
        groupLabel: "Companies",
        action: "view",
        itemLabel: "View",
    },
    {
        value: 2,
        label: "Create",
        helper: "companies.create",
        groupKey: "companies",
        groupLabel: "Companies",
        action: "create",
        itemLabel: "Create",
    },
];

function makeEnvelope(items = []) {
    return {
        message: "Roles retrieved successfully.",
        data: { items },
        meta: {
            pagination: { current_page: 1, per_page: 10, total: items.length },
        },
        errors: {},
    };
}

function mountPage(
    permissions = { view: true, create: true, update: true, delete: true },
    locale = { current: "hu", fallback: "en", available: ["hu", "en"] },
) {
    setPageProps({
        auth: { user: { permissions: [] } },
        flash: {},
        sso: { status: { message: "Rendben" } },
        locale,
    });
    return mount(RolesIndex, {
        props: { rolesApi, permissions, permissionOptions },
    });
}

function findButtonByText(wrapper, text) {
    return wrapper.findAll("button").find((node) => node.text() === text);
}

describe("Roles/Index", () => {
    beforeEach(() => {
        vi.useFakeTimers();
        listRolesMock.mockReset();
        createRoleMock.mockReset();
        updateRoleMock.mockReset();
        deleteRoleMock.mockReset();
        confirmRequireMock.mockReset();
        toastAddMock.mockReset();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it("loads the roles list on mount", async () => {
        listRolesMock.mockResolvedValueOnce(
            makeEnvelope([
                {
                    id: 1,
                    name: "admin",
                    guard_name: "web",
                    permissions_count: 2,
                    permission_ids: [1, 2],
                    permission_names: ["companies.view", "companies.create"],
                    created_at: "2026-04-09 08:00:00",
                    is_protected: true,
                    protection_label: "Rendszer",
                },
            ]),
        );

        const wrapper = mountPage();
        await flushPromises();

        expect(listRolesMock).toHaveBeenCalledWith(
            rolesApi,
            expect.objectContaining({
                page: 1,
                per_page: 10,
                sort_field: "created_at",
                sort_order: "desc",
            }),
        );
        expect(wrapper.text()).toContain("admin");
    });

    it("renders the create dialog permission selector and submits the selected payload", async () => {
        const submittedPayloads = [];
        listRolesMock
            .mockResolvedValueOnce(makeEnvelope())
            .mockResolvedValueOnce(
                makeEnvelope([
                    {
                        id: 7,
                        name: "editor",
                        guard_name: "web",
                        permissions_count: 1,
                        permission_ids: [1],
                        permission_names: ["companies.view"],
                        created_at: "2026-04-09 08:00:00",
                        is_protected: false,
                    },
                ]),
            );
        createRoleMock.mockImplementationOnce(async (_api, payload) => {
            submittedPayloads.push({
                ...payload,
                permission_ids: [...payload.permission_ids],
            });
            return {
                message: "Role created successfully.",
                data: { role: { id: 7, name: "editor" } },
                meta: {},
                errors: {},
            };
        });

        const wrapper = mountPage();
        await flushPromises();

        await findButtonByText(wrapper, hu["roles.new"]).trigger("click");
        const createDialog = wrapper.findComponent(CreateRoleDialog);

        expect(createDialog.exists()).toBe(true);
        expect(
            wrapper
                .find(
                    `input[placeholder="${hu["roles.permissions_search_placeholder"]}"]`,
                )
                .exists(),
        ).toBe(true);
        expect(
            wrapper.findAll('input[type="checkbox"]').length,
        ).toBeGreaterThan(0);

        createDialog.props("form").name = "editor";
        createDialog.props("form").permission_ids = [1, 2];
        await wrapper.get("form").trigger("submit.prevent");
        await flushPromises();

        expect(submittedPayloads[0]).toEqual(
            expect.objectContaining({
                name: "editor",
                guard_name: "web",
                permission_ids: [1, 2],
            }),
        );
        expect(listRolesMock).toHaveBeenCalledTimes(2);
    });

    it("hydrates the edit dialog and submits updated permission assignments", async () => {
        const submittedPayloads = [];
        listRolesMock
            .mockResolvedValueOnce(
                makeEnvelope([
                    {
                        id: 9,
                        name: "admin",
                        guard_name: "web",
                        permissions_count: 1,
                        permission_ids: [1],
                        permission_names: ["companies.view"],
                        created_at: "2026-04-09 08:00:00",
                        is_protected: true,
                        protection_label: "Rendszer",
                        can: { update: true, delete: false },
                    },
                ]),
            )
            .mockResolvedValueOnce(
                makeEnvelope([
                    {
                        id: 9,
                        name: "admin",
                        guard_name: "web",
                        permissions_count: 2,
                        permission_ids: [1, 2],
                        permission_names: [
                            "companies.view",
                            "companies.create",
                        ],
                        created_at: "2026-04-09 08:00:00",
                        is_protected: true,
                        protection_label: "Rendszer",
                        can: { update: true, delete: false },
                    },
                ]),
            );
        updateRoleMock.mockImplementationOnce(
            async (_api, _roleId, payload) => {
                submittedPayloads.push({
                    ...payload,
                    permission_ids: [...payload.permission_ids],
                });
                return {
                    message: "Role updated successfully.",
                    data: { role: { id: 9, name: "admin" } },
                    meta: {},
                    errors: {},
                };
            },
        );

        const wrapper = mountPage();
        await flushPromises();

        await findButtonByText(wrapper, hu["actions.edit"]).trigger("click");
        const editDialog = wrapper.findComponent(EditRoleDialog);
        editDialog.props("form").permission_ids = [1, 2];
        await wrapper.get("form").trigger("submit.prevent");
        await flushPromises();

        expect(updateRoleMock).toHaveBeenCalledWith(
            rolesApi,
            9,
            expect.any(Object),
        );
        expect(submittedPayloads[0].permission_ids).toEqual([1, 2]);
        expect(listRolesMock).toHaveBeenCalledTimes(2);
    });

    it("marks protected roles and hides their delete action", async () => {
        listRolesMock.mockResolvedValueOnce(
            makeEnvelope([
                {
                    id: 1,
                    name: "admin",
                    guard_name: "web",
                    permissions_count: 2,
                    permission_ids: [1],
                    permission_names: ["companies.view"],
                    created_at: "2026-04-09 08:00:00",
                    is_protected: true,
                    protection_label: "Rendszer",
                    can: { update: true, delete: false },
                },
            ]),
        );

        const wrapper = mountPage();
        await flushPromises();

        expect(wrapper.text()).toContain("Rendszer");
        expect(wrapper.text()).not.toContain(hu["actions.delete"]);
    });

    it("keeps protected role identity fields read-only in the edit dialog", async () => {
        listRolesMock.mockResolvedValueOnce(
            makeEnvelope([
                {
                    id: 1,
                    name: "admin",
                    guard_name: "web",
                    permissions_count: 2,
                    permission_ids: [1],
                    permission_names: ["companies.view"],
                    created_at: "2026-04-09 08:00:00",
                    is_protected: true,
                    protection_label: "Rendszer",
                    can: { update: true, delete: false },
                },
            ]),
        );

        const wrapper = mountPage();
        await flushPromises();

        await findButtonByText(wrapper, hu["actions.edit"]).trigger("click");
        const editDialog = wrapper.findComponent(EditRoleDialog);

        expect(
            editDialog.find("input#role-name").attributes("readonly"),
        ).toBeDefined();
        expect(editDialog.text()).toContain(
            hu["roles.protected_identity_notice"],
        );
    });

    it("shows an error toast and keeps the dialog open when protected role identity update is rejected", async () => {
        listRolesMock.mockResolvedValueOnce(
            makeEnvelope([
                {
                    id: 1,
                    name: "admin",
                    guard_name: "web",
                    permissions_count: 2,
                    permission_ids: [1],
                    permission_names: ["companies.view"],
                    created_at: "2026-04-09 08:00:00",
                    is_protected: true,
                    protection_label: "Rendszer",
                    can: { update: true, delete: false },
                },
            ]),
        );
        updateRoleMock.mockRejectedValueOnce(
            new Error(
                "A(z) admin vedett rendszer-szerepkor neve vagy guardja nem modositheto.",
            ),
        );

        const wrapper = mountPage();
        await flushPromises();

        await findButtonByText(wrapper, hu["actions.edit"]).trigger("click");
        const editDialog = wrapper.findComponent(EditRoleDialog);
        editDialog.props("form").name = "admin-hacked";

        await wrapper.get("form").trigger("submit.prevent");
        await flushPromises();

        expect(toastAddMock).toHaveBeenCalled();
        expect(wrapper.findComponent(EditRoleDialog).props("visible")).toBe(
            true,
        );
    });

    it("resets the create dialog form when the dialog is closed", async () => {
        listRolesMock.mockResolvedValueOnce(makeEnvelope());

        const wrapper = mountPage();
        await flushPromises();

        await findButtonByText(wrapper, hu["roles.new"]).trigger("click");
        const createDialog = wrapper.findComponent(CreateRoleDialog);
        createDialog.props("form").name = "temp-role";
        createDialog.props("form").permission_ids = [1];

        await createDialog.vm.$emit("update:visible", false);
        await flushPromises();

        expect(createDialog.props("form").name).toBe("");
        expect(createDialog.props("form").permission_ids).toEqual([]);
    });

    it("uses the shared full-height scrollable datatable layout on desktop", async () => {
        listRolesMock.mockResolvedValueOnce(
            makeEnvelope([
                {
                    id: 1,
                    name: "admin",
                    guard_name: "web",
                    permissions_count: 2,
                    permission_ids: [1, 2],
                    permission_names: ["companies.view", "companies.create"],
                    created_at: "2026-04-09 08:00:00",
                    is_protected: true,
                    protection_label: "Rendszer",
                },
            ]),
        );

        const wrapper = mountPage();
        await flushPromises();

        const dataTable = wrapper.findComponent(DataTable);

        expect(wrapper.find(".admin-table-page").exists()).toBe(true);
        expect(wrapper.find(".admin-table-shell").exists()).toBe(true);
        expect(dataTable.exists()).toBe(true);
        expect(dataTable.props("scrollable")).toBe(true);
        expect(dataTable.props("scrollHeight")).toBe("flex");
        expect(dataTable.classes()).toContain("admin-datatable");
    });

    it("renders localized english labels for the roles page and form", async () => {
        listRolesMock.mockResolvedValueOnce(
            makeEnvelope([
                {
                    id: 1,
                    name: "admin",
                    guard_name: "web",
                    permissions_count: 2,
                    permission_ids: [1, 2],
                    permission_names: ["companies.view", "companies.create"],
                    created_at: "2026-04-09 08:00:00",
                    is_protected: true,
                    protection_label: en["roles.system_badge"],
                    can: { update: true, delete: false },
                },
            ]),
        );

        const wrapper = mountPage(
            { view: true, create: true, update: true, delete: true },
            { current: "en", fallback: "hu", available: ["hu", "en"] },
        );
        await flushPromises();

        expect(wrapper.text()).toContain(en["navigation.roles.label"]);
        expect(wrapper.text()).toContain(en["roles.permissions"]);
        expect(wrapper.text()).toContain(en["table.columns.created_at"]);

        await findButtonByText(wrapper, en["actions.edit"]).trigger("click");
        await flushPromises();

        expect(wrapper.text()).toContain(en["roles.protected_identity_notice"]);
        expect(wrapper.text()).toContain(en["roles.guard_name"]);
        expect(wrapper.text()).toContain(en["common.save"]);
    });
});
