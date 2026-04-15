import { flushPromises, mount } from '@vue/test-utils';
import CompaniesIndex from '@/Pages/Companies/Index.vue';
import CreateCompanyDialog from '@/Pages/Companies/Partials/CreateCompanyDialog.vue';
import EditCompanyDialog from '@/Pages/Companies/Partials/EditCompanyDialog.vue';
import DataTable from 'primevue/datatable';
import { confirmRequireMock, toastAddMock } from '../setup';
import { setPageProps } from '../mocks/inertia';
import en from '../../../../lang/en.json';
import hu from '../../../../lang/hu.json';

const listCompaniesMock = vi.fn();
const createCompanyMock = vi.fn();
const updateCompanyMock = vi.fn();
const deleteCompanyMock = vi.fn();

vi.mock('@/Services/companyService', () => ({
    CompanyApiError: class CompanyApiError extends Error {
        constructor(message, { status = 500, errors = {}, meta = {} } = {}) {
            super(message);
            this.name = 'CompanyApiError';
            this.status = status;
            this.errors = errors;
            this.meta = meta;
        }
    },
    listCompanies: (...args) => listCompaniesMock(...args),
    createCompany: (...args) => createCompanyMock(...args),
    updateCompany: (...args) => updateCompanyMock(...args),
    deleteCompany: (...args) => deleteCompanyMock(...args),
}));

const companiesApi = {
    endpoints: {
        index: '/api/companies',
    },
};

function makeEnvelope(items = []) {
    return {
        message: 'Companies retrieved successfully.',
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

    return mount(CompaniesIndex, {
        props: {
            companiesApi,
            permissions,
        },
    });
}

function findButtonByText(wrapper, text) {
    return wrapper.findAll('button').find((node) => node.text() === text);
}

describe('Companies/Index', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        listCompaniesMock.mockReset();
        createCompanyMock.mockReset();
        updateCompanyMock.mockReset();
        deleteCompanyMock.mockReset();
        confirmRequireMock.mockReset();
        toastAddMock.mockReset();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('loads the company list on mount', async () => {
        listCompaniesMock.mockResolvedValueOnce(makeEnvelope([
            { id: 1, name: 'Acme Kft.', code: 'ACME', is_active: true, created_at: '2026-03-29 12:00:00' },
        ]));

        const wrapper = mountPage();
        await flushPromises();

        expect(listCompaniesMock).toHaveBeenCalledWith(companiesApi, expect.objectContaining({
            page: 1,
            per_page: 10,
            sort_field: 'created_at',
            sort_order: 'desc',
        }));
        expect(wrapper.text()).toContain('Acme Kft.');
    });

    it('triggers a debounced api refresh when the search term changes', async () => {
        listCompaniesMock.mockResolvedValue(makeEnvelope());

        const wrapper = mountPage();
        await flushPromises();

        await wrapper.get(`input[placeholder="${hu['companies.search_placeholder']}"]`).setValue('Beta');

        vi.advanceTimersByTime(349);
        expect(listCompaniesMock).toHaveBeenCalledTimes(1);

        vi.advanceTimersByTime(1);
        await flushPromises();

        expect(listCompaniesMock).toHaveBeenCalledTimes(2);
        expect(listCompaniesMock).toHaveBeenLastCalledWith(companiesApi, expect.objectContaining({
            search: 'Beta',
        }));
    });

    it('creates a company and refreshes the list after save', async () => {
        const submittedPayloads = [];
        listCompaniesMock
            .mockResolvedValueOnce(makeEnvelope())
            .mockResolvedValueOnce(makeEnvelope([
                { id: 10, name: 'Nova Kft.', code: 'NOVA', is_active: true, created_at: '2026-03-29 12:00:00' },
            ]));
        createCompanyMock.mockImplementationOnce(async (_api, payload) => {
            submittedPayloads.push({ ...payload });

            return {
                message: 'Company created successfully.',
                data: { company: { id: 10, name: 'Nova Kft.' } },
                meta: {},
                errors: {},
            };
        });

        const wrapper = mountPage();
        await flushPromises();

        await findButtonByText(wrapper, hu['companies.new']).trigger('click');
        const createDialog = wrapper.findComponent(CreateCompanyDialog);
        createDialog.props('form').name = 'Nova Kft.';
        createDialog.props('form').code = 'NOVA';
        await wrapper.get('form').trigger('submit.prevent');
        await flushPromises();

        expect(submittedPayloads[0]).toEqual(expect.objectContaining({
            name: 'Nova Kft.',
            code: 'NOVA',
        }));
        expect(listCompaniesMock).toHaveBeenCalledTimes(2);
        expect(toastAddMock).toHaveBeenCalledWith(expect.objectContaining({
            severity: 'success',
        }));
    });

    it('updates a company and refreshes the list after save', async () => {
        const submittedPayloads = [];
        listCompaniesMock
            .mockResolvedValueOnce(makeEnvelope([
                { id: 7, name: 'Acme Kft.', code: 'ACME', is_active: true, email: null, phone: null, address: null, created_at: '2026-03-29 12:00:00' },
            ]))
            .mockResolvedValueOnce(makeEnvelope([
                { id: 7, name: 'Acme Zrt.', code: 'ACME', is_active: true, email: null, phone: null, address: null, created_at: '2026-03-29 12:00:00' },
            ]));
        updateCompanyMock.mockImplementationOnce(async (_api, _companyId, payload) => {
            submittedPayloads.push({ ...payload });

            return {
                message: 'Company updated successfully.',
                data: { company: { id: 7, name: 'Acme Zrt.' } },
                meta: {},
                errors: {},
            };
        });

        const wrapper = mountPage();
        await flushPromises();

        const editButton = findButtonByText(wrapper, hu['actions.edit']);
        await editButton.trigger('click');
        const editDialog = wrapper.findComponent(EditCompanyDialog);
        editDialog.props('form').name = 'Acme Zrt.';
        await wrapper.get('form').trigger('submit.prevent');
        await flushPromises();

        expect(updateCompanyMock).toHaveBeenCalledWith(companiesApi, 7, expect.any(Object));
        expect(submittedPayloads[0]).toEqual(expect.objectContaining({
            name: 'Acme Zrt.',
        }));
        expect(listCompaniesMock).toHaveBeenCalledTimes(2);
    });

    it('deletes a company after confirmation and refreshes the list', async () => {
        listCompaniesMock
            .mockResolvedValueOnce(makeEnvelope([
                { id: 11, name: 'Gamma Kft.', code: 'GAMMA', is_active: true, created_at: '2026-03-29 12:00:00' },
            ]))
            .mockResolvedValueOnce(makeEnvelope());
        deleteCompanyMock.mockResolvedValueOnce({
            message: 'Company deleted successfully.',
            data: {},
            meta: {},
            errors: {},
        });

        const wrapper = mountPage();
        await flushPromises();

        const deleteButton = findButtonByText(wrapper, hu['actions.delete']);
        await deleteButton.trigger('click');
        expect(confirmRequireMock).toHaveBeenCalledTimes(1);

        await confirmRequireMock.mock.calls[0][0].accept();
        await flushPromises();

        expect(deleteCompanyMock).toHaveBeenCalledWith(companiesApi, 11);
        expect(listCompaniesMock).toHaveBeenCalledTimes(2);
    });

    it('hides action buttons when the related permissions are missing', async () => {
        listCompaniesMock.mockResolvedValueOnce(makeEnvelope([
            { id: 5, name: 'Silent Kft.', code: 'SILENT', is_active: false, created_at: '2026-03-29 12:00:00' },
        ]));

        const wrapper = mountPage({ view: true, create: false, update: false, delete: false });
        await flushPromises();

        expect(wrapper.text()).not.toContain(hu['companies.new']);
        expect(wrapper.text()).not.toContain(hu['actions.edit']);
        expect(wrapper.text()).not.toContain(hu['actions.delete']);
    });

    it('renders localized english labels for the mobile card and company form', async () => {
        listCompaniesMock.mockResolvedValueOnce(makeEnvelope([
            { id: 1, name: 'Acme Ltd.', code: 'ACME', is_active: true, email: 'hello@acme.test', phone: '+36 1 555 0101', address: 'Budapest', created_at: '2026-03-29 12:00:00' },
        ]));

        const wrapper = mountPage(
            { view: true, create: true, update: true, delete: true },
            { current: 'en', fallback: 'hu', available: ['hu', 'en'] },
        );
        await flushPromises();

        expect(wrapper.text()).toContain(en['companies.new']);
        expect(wrapper.text()).toContain(en['table.created_at']);
        expect(wrapper.text()).toContain(en['table.phone']);

        await findButtonByText(wrapper, en['companies.new']).trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain(en['table.name']);
        expect(wrapper.text()).toContain(en['table.code']);
        expect(wrapper.text()).toContain(en['table.email']);
        expect(wrapper.text()).toContain(en['table.address']);
        expect(wrapper.text()).toContain(en['companies.form.is_active']);
        expect(wrapper.text()).toContain(en['common.cancel']);
        expect(wrapper.text()).toContain(en['actions.create']);
    });

    it('uses the shared full-height scrollable datatable layout on desktop', async () => {
        listCompaniesMock.mockResolvedValueOnce(makeEnvelope([
            { id: 1, name: 'Acme Kft.', code: 'ACME', is_active: true, created_at: '2026-03-29 12:00:00' },
        ]));

        const wrapper = mountPage();
        await flushPromises();

        const dataTable = wrapper.findComponent(DataTable);

        expect(wrapper.find('.admin-table-page').exists()).toBe(true);
        expect(wrapper.find('.admin-table-shell').exists()).toBe(true);
        expect(dataTable.exists()).toBe(true);
        expect(dataTable.props('scrollable')).toBe(true);
        expect(dataTable.props('scrollHeight')).toBe('flex');
        expect(dataTable.classes()).toContain('admin-datatable');
    });
});
