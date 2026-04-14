import { flushPromises, mount } from '@vue/test-utils';
import EmployeesIndex from '@/Pages/Employees/Index.vue';
import CreateEmployeeDialog from '@/Pages/Employees/Partials/CreateEmployeeDialog.vue';
import EditEmployeeDialog from '@/Pages/Employees/Partials/EditEmployeeDialog.vue';
import { confirmRequireMock, toastAddMock } from '../setup';
import { setPageProps } from '../mocks/inertia';
import en from '../../../../lang/en.json';
import hu from '../../../../lang/hu.json';

const listEmployeesMock = vi.fn();
const createEmployeeMock = vi.fn();
const updateEmployeeMock = vi.fn();
const deleteEmployeeMock = vi.fn();

vi.mock('@/Services/employeeService', () => ({
    EmployeeApiError: class EmployeeApiError extends Error {
        constructor(message, { status = 500, errors = {}, meta = {} } = {}) {
            super(message);
            this.name = 'EmployeeApiError';
            this.status = status;
            this.errors = errors;
            this.meta = meta;
        }
    },
    listEmployees: (...args) => listEmployeesMock(...args),
    createEmployee: (...args) => createEmployeeMock(...args),
    updateEmployee: (...args) => updateEmployeeMock(...args),
    deleteEmployee: (...args) => deleteEmployeeMock(...args),
}));

vi.mock('@/Components/Admin/RowActionMenu.vue', async () => {
    const { defineComponent, h } = await import('vue');

    return {
        default: defineComponent({
            props: {
                items: {
                    type: Array,
                    default: () => [],
                },
            },
            setup(props) {
                return () => h('div', {}, (props.items ?? []).filter(Boolean).map((item) => h('button', {
                    type: 'button',
                    onClick: () => item.command?.(),
                }, item.label)));
            },
        }),
    };
});

const employeesApi = {
    endpoints: {
        index: '/api/employees',
        store: '/api/employees',
    },
};

const companies = [
    { id: 1, name: 'Acme Kft.' },
    { id: 2, name: 'Nova Zrt.' },
];

function makeEnvelope(items = []) {
    return {
        message: 'Employees retrieved successfully.',
        data: { items },
        meta: {
            pagination: {
                current_page: 1,
                per_page: 10,
                total: items.length,
            },
        },
        errors: {},
    };
}

function makeEmployee(overrides = {}) {
    return {
        id: 7,
        company_id: 1,
        company_name: 'Acme Kft.',
        employee_number: 'EMP-7',
        name: 'Jane Doe',
        email: 'jane@example.com',
        phone: '+361234567',
        position: 'HR',
        is_active: true,
        created_at: '2026-03-29 12:00:00',
        ...overrides,
    };
}

function mountPage(
    permissions = { view: true, create: true, update: true, delete: true },
    locale = { current: 'hu', fallback: 'en', available: ['hu', 'en'] },
) {
    setPageProps({
        auth: {
            user: {
                permissions: [],
            },
        },
        flash: {},
        sso: {
            status: {
                message: 'Rendben',
            },
        },
        locale,
    });

    return mount(EmployeesIndex, {
        props: {
            employeesApi,
            permissions,
            companies,
        },
    });
}

function findButtonByText(wrapper, text) {
    return wrapper.findAll('button').find((node) => node.text() === text);
}

describe('Employees/Index', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        listEmployeesMock.mockReset();
        createEmployeeMock.mockReset();
        updateEmployeeMock.mockReset();
        deleteEmployeeMock.mockReset();
        confirmRequireMock.mockReset();
        toastAddMock.mockReset();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('renders the EmployeeForm inside the create dialog', async () => {
        listEmployeesMock.mockResolvedValueOnce(makeEnvelope());

        const wrapper = mountPage();
        await flushPromises();

        await findButtonByText(wrapper, hu['employees.new']).trigger('click');
        await flushPromises();

        const createDialog = wrapper.findComponent(CreateEmployeeDialog);

        expect(createDialog.exists()).toBe(true);
        expect(createDialog.props('visible')).toBe(true);
        expect(wrapper.find('#employee-company-id').exists()).toBe(true);
        expect(wrapper.find('#employee-number').exists()).toBe(true);
        expect(wrapper.find('#employee-name').exists()).toBe(true);
        expect(wrapper.find('#employee-position').exists()).toBe(true);
    });

    it('uses the numeric employee sort_order contract on initial load', async () => {
        listEmployeesMock.mockResolvedValueOnce(makeEnvelope([makeEmployee()]));

        mountPage();
        await flushPromises();

        expect(listEmployeesMock).toHaveBeenCalledWith(
            employeesApi,
            expect.objectContaining({
                page: 1,
                per_page: 10,
                sort_field: 'created_at',
                sort_order: -1,
            }),
        );
    });

    it('renders the EmployeeForm inside the edit dialog', async () => {
        listEmployeesMock.mockResolvedValueOnce(makeEnvelope([makeEmployee()]));

        const wrapper = mountPage();
        await flushPromises();

        await findButtonByText(wrapper, hu['common.edit']).trigger('click');
        await flushPromises();

        const editDialog = wrapper.findComponent(EditEmployeeDialog);

        expect(editDialog.exists()).toBe(true);
        expect(editDialog.props('visible')).toBe(true);
        expect(wrapper.find('#employee-company-id').exists()).toBe(true);
        expect(wrapper.find('#employee-number').element.value).toBe('EMP-7');
        expect(wrapper.find('#employee-name').element.value).toBe('Jane Doe');
        expect(wrapper.find('#employee-position').element.value).toBe('HR');
    });

    it('keeps the create flow working after the dialog fix', async () => {
        listEmployeesMock
            .mockResolvedValueOnce(makeEnvelope())
            .mockResolvedValueOnce(makeEnvelope([makeEmployee({ id: 9, employee_number: 'EMP-9', name: 'New Hire', email: 'new@example.com', position: 'Support' })]));

        const submittedCreatePayloads = [];
        createEmployeeMock.mockImplementationOnce(async (_api, payload) => {
            submittedCreatePayloads.push({ ...payload });

            return {
                message: 'Employee created successfully.',
                data: { employee: { id: 9 } },
                meta: {},
                errors: {},
            };
        });

        const wrapper = mountPage();
        await flushPromises();

        await findButtonByText(wrapper, hu['employees.new']).trigger('click');
        await flushPromises();

        await wrapper.find('#employee-company-id').setValue('1');
        await wrapper.find('#employee-number').setValue('EMP-9');
        await wrapper.find('#employee-name').setValue('New Hire');
        await wrapper.find('#employee-email').setValue('new@example.com');
        await wrapper.find('#employee-position').setValue('Support');
        await wrapper.get('form').trigger('submit.prevent');
        await flushPromises();

        expect(submittedCreatePayloads[0]).toEqual(expect.objectContaining({
            company_id: 1,
            employee_number: 'EMP-9',
            name: 'New Hire',
            email: 'new@example.com',
            position: 'Support',
        }));
        expect(toastAddMock).toHaveBeenCalledWith(expect.objectContaining({
            severity: 'success',
        }));
    });

    it('keeps the edit flow working after the dialog fix', async () => {
        listEmployeesMock
            .mockResolvedValueOnce(makeEnvelope([makeEmployee()]))
            .mockResolvedValueOnce(makeEnvelope([makeEmployee({ position: 'Lead HR' })]));

        const submittedUpdatePayloads = [];
        updateEmployeeMock.mockImplementationOnce(async (_api, _employeeId, payload) => {
            submittedUpdatePayloads.push({ ...payload });

            return {
                message: 'Employee updated successfully.',
                data: { employee: { id: 7 } },
                meta: {},
                errors: {},
            };
        });

        const wrapper = mountPage();
        await flushPromises();

        await findButtonByText(wrapper, hu['common.edit']).trigger('click');
        await flushPromises();

        await wrapper.find('#employee-position').setValue('Lead HR');
        await wrapper.get('form').trigger('submit.prevent');
        await flushPromises();

        expect(updateEmployeeMock).toHaveBeenCalledWith(employeesApi, 7, expect.any(Object));
        expect(submittedUpdatePayloads[0]).toEqual(expect.objectContaining({
            position: 'Lead HR',
        }));
    });

    it('renders localized english labels for the employee page and form', async () => {
        listEmployeesMock.mockResolvedValueOnce(makeEnvelope([makeEmployee()]));

        const wrapper = mountPage(
            { view: true, create: true, update: true, delete: true },
            { current: 'en', fallback: 'hu', available: ['hu', 'en'] },
        );
        await flushPromises();

        expect(wrapper.text()).toContain(en['navigation.employees.label']);
        expect(wrapper.text()).toContain(en['employees.new']);
        expect(wrapper.text()).toContain(en['table.position']);
        expect(wrapper.text()).toContain(en['table.company']);

        await findButtonByText(wrapper, en['employees.new']).trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain(en['table.employee_number']);
        expect(wrapper.text()).toContain(en['table.name']);
        expect(wrapper.text()).toContain(en['table.email']);
        expect(wrapper.text()).toContain(en['employees.form.is_active']);
        expect(wrapper.text()).toContain(en['common.cancel']);
        expect(wrapper.text()).toContain(en['common.create']);
    });
});
