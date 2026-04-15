import { flushPromises, mount } from "@vue/test-utils";
import PermissionsIndex from "@/Pages/Permissions/Index.vue";
import CreatePermissionDialog from "@/Pages/Permissions/Partials/CreatePermissionDialog.vue";
import EditPermissionDialog from "@/Pages/Permissions/Partials/EditPermissionDialog.vue";
import DataTable from "primevue/datatable";
import { confirmRequireMock, toastAddMock } from "../setup";
import { setPageProps } from "../mocks/inertia";
import en from "../../../../lang/en.json";
import hu from "../../../../lang/hu.json";

const {
    listPermissionsMock,
    createPermissionMock,
    updatePermissionMock,
    deletePermissionMock,
} = vi.hoisted(() => ({
    listPermissionsMock: vi.fn(),
    createPermissionMock: vi.fn(),
    updatePermissionMock: vi.fn(),
    deletePermissionMock: vi.fn(),
}));

vi.mock("@/Services/permissionService", () => ({
    PermissionApiError: class PermissionApiError extends Error {
        constructor(message, { status = 500, errors = {}, meta = {} } = {}) {
            super(message);
            this.name = "PermissionApiError";
            this.status = status;
            this.errors = errors;
            this.meta = meta;
        }
    },
    listPermissions: (...args) => listPermissionsMock(...args),
    createPermission: (...args) => createPermissionMock(...args),
    updatePermission: (...args) => updatePermissionMock(...args),
    deletePermission: (...args) => deletePermissionMock(...args),
}));

const permissionsApi = { endpoints: { index: "/api/permissions" } };

function makeEnvelope(items = []) {
    return {
        message: "Permissions retrieved successfully.",
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
    return mount(PermissionsIndex, { props: { permissionsApi, permissions } });
}

function findButtonByText(wrapper, text) {
    return wrapper.findAll("button").find((node) => node.text() === text);
}

describe("Permissions/Index", () => {
    beforeEach(() => {
        vi.useFakeTimers();
        listPermissionsMock.mockReset();
        createPermissionMock.mockReset();
        updatePermissionMock.mockReset();
        deletePermissionMock.mockReset();
        confirmRequireMock.mockReset();
        toastAddMock.mockReset();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it("loads the permissions list on mount", async () => {
        listPermissionsMock.mockResolvedValueOnce(
            makeEnvelope([
                {
                    id: 1,
                    name: "companies.view",
                    guard_name: "web",
                    roles_count: 2,
                    created_at: "2026-04-09 08:00:00",
                    is_protected: true,
                    protection_label: "Rendszer",
                },
            ]),
        );

        const wrapper = mountPage();
        await flushPromises();

        expect(listPermissionsMock).toHaveBeenCalledWith(
            permissionsApi,
            expect.objectContaining({
                page: 1,
                per_page: 10,
                sort_field: "created_at",
                sort_order: "desc",
            }),
        );
        expect(wrapper.text()).toContain("companies.view");
    });

    it("creates a permission and refreshes the list after save", async () => {
        const submittedPayloads = [];
        listPermissionsMock
            .mockResolvedValueOnce(makeEnvelope())
            .mockResolvedValueOnce(
                makeEnvelope([
                    {
                        id: 7,
                        name: "roles.assign",
                        guard_name: "web",
                        roles_count: 0,
                        created_at: "2026-04-09 08:00:00",
                        is_protected: false,
                    },
                ]),
            );
        createPermissionMock.mockImplementationOnce(async (_api, payload) => {
            submittedPayloads.push({ ...payload });
            return {
                message: "Permission created successfully.",
                data: { permission: { id: 7, name: "roles.assign" } },
                meta: {},
                errors: {},
            };
        });

        const wrapper = mountPage();
        await flushPromises();

        await findButtonByText(wrapper, hu["permissions.new"]).trigger("click");
        const createDialog = wrapper.findComponent(CreatePermissionDialog);
        createDialog.props("form").name = "roles.assign";
        await wrapper.get("form").trigger("submit.prevent");
        await flushPromises();

        expect(submittedPayloads[0]).toEqual(
            expect.objectContaining({
                name: "roles.assign",
                guard_name: "web",
            }),
        );
        expect(listPermissionsMock).toHaveBeenCalledTimes(2);
    });

    it("updates a permission and refreshes the list after save", async () => {
        const submittedPayloads = [];
        listPermissionsMock
            .mockResolvedValueOnce(
                makeEnvelope([
                    {
                        id: 9,
                        name: "roles.assign",
                        guard_name: "web",
                        roles_count: 1,
                        created_at: "2026-04-09 08:00:00",
                        is_protected: false,
                        can: { update: true, delete: true },
                    },
                ]),
            )
            .mockResolvedValueOnce(
                makeEnvelope([
                    {
                        id: 9,
                        name: "roles.attach",
                        guard_name: "web",
                        roles_count: 1,
                        created_at: "2026-04-09 08:00:00",
                        is_protected: false,
                        can: { update: true, delete: true },
                    },
                ]),
            );
        updatePermissionMock.mockImplementationOnce(
            async (_api, _permissionId, payload) => {
                submittedPayloads.push({ ...payload });
                return {
                    message: "Permission updated successfully.",
                    data: { permission: { id: 9, name: "roles.attach" } },
                    meta: {},
                    errors: {},
                };
            },
        );

        const wrapper = mountPage();
        await flushPromises();

        await findButtonByText(wrapper, hu["actions.edit"]).trigger("click");
        const editDialog = wrapper.findComponent(EditPermissionDialog);
        editDialog.props("form").name = "roles.attach";
        await wrapper.get("form").trigger("submit.prevent");
        await flushPromises();

        expect(updatePermissionMock).toHaveBeenCalledWith(
            permissionsApi,
            9,
            expect.any(Object),
        );
        expect(submittedPayloads[0].name).toBe("roles.attach");
        expect(listPermissionsMock).toHaveBeenCalledTimes(2);
    });

    it("marks protected permissions and hides their delete action", async () => {
        listPermissionsMock.mockResolvedValueOnce(
            makeEnvelope([
                {
                    id: 1,
                    name: "roles.view",
                    guard_name: "web",
                    roles_count: 2,
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

    it("keeps protected permission fields read-only and the save action disabled", async () => {
        listPermissionsMock.mockResolvedValueOnce(
            makeEnvelope([
                {
                    id: 1,
                    name: "roles.view",
                    guard_name: "web",
                    roles_count: 2,
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
        const editDialog = wrapper.findComponent(EditPermissionDialog);

        expect(
            editDialog.find("input#permission-name").attributes("readonly"),
        ).toBeDefined();
        expect(editDialog.text()).toContain(
            hu["permissions.protected_identity_notice"],
        );
        expect(
            editDialog.find('button[type="submit"]').attributes("disabled"),
        ).toBeDefined();
    });

    it("shows an error toast and keeps the dialog open when the backend rejects a protected permission update", async () => {
        listPermissionsMock.mockResolvedValueOnce(
            makeEnvelope([
                {
                    id: 1,
                    name: "roles.view",
                    guard_name: "web",
                    roles_count: 2,
                    created_at: "2026-04-09 08:00:00",
                    is_protected: true,
                    protection_label: "Rendszer",
                    can: { update: true, delete: false },
                },
            ]),
        );
        updatePermissionMock.mockRejectedValueOnce(
            new Error(
                "A(z) roles.view vedett rendszer-jogosultsag neve vagy guardja nem modositheto.",
            ),
        );

        const wrapper = mountPage();
        await flushPromises();

        await findButtonByText(wrapper, hu["actions.edit"]).trigger("click");
        const editDialog = wrapper.findComponent(EditPermissionDialog);
        editDialog.props("form").name = "roles.manage";

        await wrapper.get("form").trigger("submit.prevent");
        await flushPromises();

        expect(toastAddMock).toHaveBeenCalled();
        expect(
            wrapper.findComponent(EditPermissionDialog).props("visible"),
        ).toBe(true);
    });

    it("resets the create dialog form when the dialog is closed", async () => {
        listPermissionsMock.mockResolvedValueOnce(makeEnvelope());

        const wrapper = mountPage();
        await flushPromises();

        await findButtonByText(wrapper, hu["permissions.new"]).trigger("click");
        const createDialog = wrapper.findComponent(CreatePermissionDialog);
        createDialog.props("form").name = "temp.permission";

        await createDialog.vm.$emit("update:visible", false);
        await flushPromises();

        expect(createDialog.props("form").name).toBe("");
        expect(createDialog.props("form").guard_name).toBe("web");
    });

    it("uses the shared full-height scrollable datatable layout on desktop", async () => {
        listPermissionsMock.mockResolvedValueOnce(
            makeEnvelope([
                {
                    id: 1,
                    name: "companies.view",
                    guard_name: "web",
                    roles_count: 2,
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

    it("renders localized english labels for the permissions page and form", async () => {
        listPermissionsMock.mockResolvedValueOnce(
            makeEnvelope([
                {
                    id: 1,
                    name: "companies.view",
                    guard_name: "web",
                    roles_count: 2,
                    created_at: "2026-04-09 08:00:00",
                    is_protected: true,
                    protection_label: en["permissions.system_badge"],
                    can: { update: true, delete: false },
                },
            ]),
        );

        const wrapper = mountPage(
            { view: true, create: true, update: true, delete: true },
            { current: "en", fallback: "hu", available: ["hu", "en"] },
        );
        await flushPromises();

        expect(wrapper.text()).toContain(en["navigation.permissions.label"]);
        expect(wrapper.text()).toContain(
            en["permissions.roles_count_badge"].replace(":count", "2"),
        );
        expect(wrapper.text()).toContain(en["table.columns.created_at"]);

        await findButtonByText(wrapper, en["actions.edit"]).trigger("click");
        await flushPromises();

        expect(wrapper.text()).toContain(
            en["permissions.protected_identity_notice"],
        );
        expect(wrapper.text()).toContain(en["permissions.guard_name"]);
        expect(wrapper.text()).toContain(en["common.save"]);
    });
});
