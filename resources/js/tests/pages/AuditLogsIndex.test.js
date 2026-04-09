import { flushPromises, mount } from '@vue/test-utils';
import AuditLogsIndex from '@/Pages/AuditLogs/Index.vue';
import DataTable from 'primevue/datatable';
import { toastAddMock } from '../setup';
import { setPageProps } from '../mocks/inertia';

const fetchAuditLogsMock = vi.fn();

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

function mountPage() {
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

        await wrapper.get('input[placeholder="Kereses esemeny vagy leiras alapjan"]').setValue('login');

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

        expect(wrapper.text()).toContain('Betoltes folyamatban...');

        resolveRequest(makeEnvelope());
        await flushPromises();
    });
});
