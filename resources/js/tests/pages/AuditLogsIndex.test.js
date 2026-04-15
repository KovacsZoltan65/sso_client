import { flushPromises, mount } from '@vue/test-utils';
import AuditLogsIndex from '@/Pages/AuditLogs/Index.vue';
import DataTable from 'primevue/datatable';
import { toastAddMock } from '../setup';
import { setPageProps } from '../mocks/inertia';
import en from '../../../../lang/en.json';

const fetchAuditLogsMock = vi.fn();
const showAuditLogMock = vi.fn();

vi.mock('@/Services/auditLogService', () => ({
    AuditLogApiError: class AuditLogApiError extends Error {
        constructor(message, { status = 500, errors = {}, meta = {} } = {}) {
            super(message);
            this.name = 'AuditLogApiError';
            this.status = status;
            this.errors = errors;
            this.meta = meta;
        }
    },
    fetchAuditLogs: (...args) => fetchAuditLogsMock(...args),
    showAuditLog: (...args) => showAuditLogMock(...args),
}));

const auditLogsApi = {
    endpoints: {
        index: '/api/audit-logs',
    },
};

function makeEnvelope(items = [], pagination = {}) {
    return {
        message: 'Audit logs retrieved successfully.',
        data: { items },
        meta: {
            pagination: {
                current_page: pagination.current_page ?? 1,
                per_page: pagination.per_page ?? 10,
                total: pagination.total ?? items.length,
            },
        },
        errors: {},
    };
}

function makeDetailEnvelope(overrides = {}) {
    return {
        message: 'Audit log retrieved successfully.',
        data: {
            audit_log: {
                id: 11,
                event: 'client_admin.company.updated',
                description: 'Company updated.',
                log_name: 'client.admin.company',
                subject_type: 'Company',
                subject_type_fqn: 'App\\Models\\Company',
                subject_id: 5,
                subject: { id: 5, type: 'Company', display: 'Acme Kft.' },
                causer: { id: 2, name: 'Alice', email: 'alice@example.test', type: 'User' },
                properties: {
                    ip_address: '127.0.0.1',
                    updated_fields: ['name'],
                },
                context: {
                    ip_address: '127.0.0.1',
                    user_agent: 'Vitest Browser',
                    route: 'api.audit-logs.show',
                    reason: null,
                    status: null,
                    result: 'success',
                },
                created_at: '2026-04-09 10:00:00',
                updated_at: '2026-04-09 10:05:00',
                ...overrides,
            },
        },
        meta: {},
        errors: {},
    };
}

function mountPage(locale = { current: 'en', fallback: 'hu', available: ['hu', 'en'] }) {
    setPageProps({
        auth: {
            user: {
                permissions: ['audit-logs.view'],
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

    return mount(AuditLogsIndex, {
        props: {
            auditLogsApi,
            permissions: { view: true },
        },
    });
}

describe('AuditLogs/Index', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        fetchAuditLogsMock.mockReset();
        showAuditLogMock.mockReset();
        toastAddMock.mockReset();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('fetches audit log rows on mount', async () => {
        fetchAuditLogsMock.mockResolvedValueOnce(makeEnvelope([
            {
                id: 11,
                event: 'client_admin.company.created',
                description: 'Company created.',
                subject_type: 'Company',
                subject_id: 5,
                causer: { id: 2, name: 'Alice' },
                created_at: '2026-04-09 10:00:00',
            },
        ]));

        const wrapper = mountPage();
        await flushPromises();

        expect(fetchAuditLogsMock).toHaveBeenCalledWith(auditLogsApi, expect.objectContaining({
            page: 1,
            per_page: 10,
            sort_field: 'created_at',
            sort_order: 'desc',
        }));
        expect(wrapper.text()).toContain('Company created.');
        expect(wrapper.text()).toContain('Alice');
    });

    it('triggers a debounced reload when the search term changes', async () => {
        fetchAuditLogsMock.mockResolvedValue(makeEnvelope());

        const wrapper = mountPage();
        await flushPromises();

        await wrapper.get(`input[placeholder="${en['audit_logs.mobile_search_placeholder']}"]`).setValue('login');

        vi.advanceTimersByTime(349);
        expect(fetchAuditLogsMock).toHaveBeenCalledTimes(1);

        vi.advanceTimersByTime(1);
        await flushPromises();

        expect(fetchAuditLogsMock).toHaveBeenCalledTimes(2);
        expect(fetchAuditLogsMock).toHaveBeenLastCalledWith(auditLogsApi, expect.objectContaining({
            global: 'login',
        }));
    });

    it('requests the next page when the datatable emits a page event', async () => {
        fetchAuditLogsMock
            .mockResolvedValueOnce(makeEnvelope())
            .mockResolvedValueOnce(makeEnvelope([], {
                current_page: 2,
                per_page: 25,
                total: 40,
            }));

        const wrapper = mountPage();
        await flushPromises();

        wrapper.findComponent(DataTable).vm.$emit('page', { page: 1, rows: 25 });
        await flushPromises();

        expect(fetchAuditLogsMock).toHaveBeenLastCalledWith(auditLogsApi, expect.objectContaining({
            page: 2,
            per_page: 25,
        }));
    });

    it('requests sorted data when the datatable emits a sort event', async () => {
        fetchAuditLogsMock
            .mockResolvedValueOnce(makeEnvelope())
            .mockResolvedValueOnce(makeEnvelope());

        const wrapper = mountPage();
        await flushPromises();

        wrapper.findComponent(DataTable).vm.$emit('sort', { sortField: 'id', sortOrder: 1 });
        await flushPromises();

        expect(fetchAuditLogsMock).toHaveBeenLastCalledWith(auditLogsApi, expect.objectContaining({
            sort_field: 'id',
            sort_order: 'asc',
            page: 1,
        }));
    });

    it('shows the loading state while rows are being fetched', async () => {
        let resolveRequest;
        fetchAuditLogsMock.mockImplementationOnce(() => new Promise((resolve) => {
            resolveRequest = resolve;
        }));

        const wrapper = mountPage();
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain(en['audit_logs.loading_short']);

        resolveRequest(makeEnvelope());
        await flushPromises();
    });

    it('opens the detail dialog and loads the selected audit log', async () => {
        fetchAuditLogsMock.mockResolvedValueOnce(makeEnvelope([
            {
                id: 11,
                event: 'client_admin.company.updated',
                description: 'Company updated.',
                subject_type: 'Company',
                subject_id: 5,
                causer: { id: 2, name: 'Alice' },
                created_at: '2026-04-09 10:00:00',
            },
        ]));
        showAuditLogMock.mockResolvedValueOnce(makeDetailEnvelope());

        const wrapper = mountPage();
        await flushPromises();

        const detailsButton = wrapper.findAll('button').find((node) => node.text() === en['common.details']);
        await detailsButton.trigger('click');
        await flushPromises();

        expect(showAuditLogMock).toHaveBeenCalledWith(auditLogsApi, 11);
        expect(wrapper.text()).toContain('Company updated.');
        expect(wrapper.text()).toContain('Vitest Browser');
    });

    it('renders structured property sections for common audit payloads', async () => {
        fetchAuditLogsMock.mockResolvedValueOnce(makeEnvelope([
            {
                id: 11,
                event: 'client_admin.company.updated',
                description: 'Company updated.',
                subject_type: 'Company',
                subject_id: 5,
                causer: { id: 2, name: 'Alice' },
                created_at: '2026-04-09 10:00:00',
            },
        ]));
        showAuditLogMock.mockResolvedValueOnce(makeDetailEnvelope({
            properties: {
                old: {
                    name: 'Acme Kft.',
                    is_active: false,
                },
                attributes: {
                    name: 'Acme Zrt.',
                    is_active: true,
                },
            },
        }));

        const wrapper = mountPage();
        await flushPromises();

        const detailsButton = wrapper.findAll('button').find((node) => node.text() === en['common.details']);
        await detailsButton.trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain(en['audit_logs.properties_previous_values']);
        expect(wrapper.text()).toContain(en['audit_logs.properties_new_values']);
        expect(wrapper.text()).toContain('Acme Kft.');
        expect(wrapper.text()).toContain('Acme Zrt.');
        expect(wrapper.text()).toContain(en['audit_logs.properties_raw_json']);
    });

    it('keeps the raw json fallback for unrecognized properties payloads', async () => {
        fetchAuditLogsMock.mockResolvedValueOnce(makeEnvelope([
            {
                id: 11,
                event: 'client_admin.company.updated',
                description: 'Company updated.',
                subject_type: 'Company',
                subject_id: 5,
                causer: { id: 2, name: 'Alice' },
                created_at: '2026-04-09 10:00:00',
            },
        ]));
        showAuditLogMock.mockResolvedValueOnce(makeDetailEnvelope({
            properties: {
                updated_fields: ['name'],
                custom_note: 'manual audit note',
            },
        }));

        const wrapper = mountPage();
        await flushPromises();

        const detailsButton = wrapper.findAll('button').find((node) => node.text() === en['common.details']);
        await detailsButton.trigger('click');
        await flushPromises();

        expect(wrapper.text()).not.toContain(en['audit_logs.properties_previous_values']);
        expect(wrapper.text()).not.toContain(en['audit_logs.properties_new_values']);
        expect(wrapper.text()).toContain(en['audit_logs.properties']);
        expect(wrapper.text()).toContain('manual audit note');
    });

    it('shows an error toast and keeps the dialog closed when detail loading fails', async () => {
        fetchAuditLogsMock.mockResolvedValueOnce(makeEnvelope([
            {
                id: 11,
                event: 'client_admin.company.updated',
                description: 'Company updated.',
                subject_type: 'Company',
                subject_id: 5,
                causer: { id: 2, name: 'Alice' },
                created_at: '2026-04-09 10:00:00',
            },
        ]));
        showAuditLogMock.mockRejectedValueOnce(new Error('A reszletek betoltese sikertelen volt.'));

        const wrapper = mountPage();
        await flushPromises();

        const detailsButton = wrapper.findAll('button').find((node) => node.text() === en['common.details']);
        await detailsButton.trigger('click');
        await flushPromises();

        expect(toastAddMock).toHaveBeenCalledWith(expect.objectContaining({ severity: 'error' }));
    });

    it('renders localized english labels for the audit log page and dialog', async () => {
        fetchAuditLogsMock.mockResolvedValueOnce(makeEnvelope([
            {
                id: 11,
                event: 'client_admin.company.updated',
                description: 'Company updated.',
                subject_type: 'Company',
                subject_id: 5,
                causer: { id: 2, name: 'Alice' },
                created_at: '2026-04-09 10:00:00',
            },
        ]));
        showAuditLogMock.mockResolvedValueOnce(makeDetailEnvelope());

        const wrapper = mountPage();
        await flushPromises();

        expect(wrapper.text()).toContain(en['navigation.audit_logs.label']);
        expect(wrapper.text()).toContain(en['audit_logs.description']);
        expect(wrapper.text()).toContain(en['audit_logs.subject']);

        const detailsButton = wrapper.findAll('button').find((node) => node.text() === en['common.details']);
        await detailsButton.trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain(en['audit_logs.event']);
        expect(wrapper.text()).toContain(en['audit_logs.causer']);
        expect(wrapper.text()).toContain(en['audit_logs.context']);
        expect(wrapper.text()).toContain(en['audit_logs.user_agent']);
    });
});
