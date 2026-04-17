import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';

const items = [
    { labelKey: 'navigation.dashboard.label', icon: 'pi pi-home', route: 'dashboard' },
    { labelKey: 'navigation.profile.label', icon: 'pi pi-id-card', route: 'profile.edit' },
    { labelKey: 'navigation.my_account.label', icon: 'pi pi-user', route: 'account.show' },
    { labelKey: 'navigation.companies.label', icon: 'pi pi-building', route: 'companies.index', permission: 'companies.view' },
    { labelKey: 'navigation.employees.label', icon: 'pi pi-users', route: 'employees.index', permission: 'employees.view' },
    { labelKey: 'navigation.users.label', icon: 'pi pi-users', route: 'users.index', permission: 'users.view' },
    { labelKey: 'navigation.roles.label', icon: 'pi pi-shield', route: 'roles.index', permission: 'roles.view' },
    { labelKey: 'navigation.permissions.label', icon: 'pi pi-lock', route: 'permissions.index', permission: 'permissions.view' },
    { labelKey: 'navigation.connection_health.label', icon: 'pi pi-link', route: 'sso.status', permission: 'sso-status.view' },
    { labelKey: 'navigation.audit_logs.label', icon: 'pi pi-history', route: 'audit-logs.index', permission: 'audit-logs.view' },
];

export function useNavigation() {
    const page = usePage();

    const allowedItems = computed(() =>
        items.filter((item) => {
            if (!item.permission) {
                return true;
            }

            return page.props.auth.user?.permissions?.includes(item.permission);
        }).map((item) => ({
            ...item,
            label: trans(item.labelKey),
        })),
    );

    return {
        items: allowedItems,
    };
}
