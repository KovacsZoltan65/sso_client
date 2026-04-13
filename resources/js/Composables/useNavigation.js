import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const items = [
    { label: 'Dashboard', icon: 'pi pi-home', route: 'dashboard' },
    { label: 'Profile', icon: 'pi pi-id-card', route: 'profile.edit' },
    { label: 'My Account', icon: 'pi pi-user', route: 'account.show' },
    { label: 'Companies', icon: 'pi pi-building', route: 'companies.index', permission: 'companies.view' },
    { label: 'Employees', icon: 'pi pi-users', route: 'employees.index', permission: 'employees.view' },
    { label: 'Users', icon: 'pi pi-users', route: 'users.index', permission: 'users.view' },
    { label: 'Roles', icon: 'pi pi-shield', route: 'roles.index', permission: 'roles.view' },
    { label: 'Permissions', icon: 'pi pi-lock', route: 'permissions.index', permission: 'permissions.view' },
    { label: 'Connection Health', icon: 'pi pi-link', route: 'sso.status', permission: 'sso-status.view' },
    { label: 'Audit Logs', icon: 'pi pi-history', route: 'audit-logs.index', permission: 'audit-logs.view' },
];

export function useNavigation() {
    const page = usePage();

    const allowedItems = computed(() =>
        items.filter((item) => {
            if (!item.permission) {
                return true;
            }

            return page.props.auth.user?.permissions?.includes(item.permission);
        }),
    );

    return {
        items: allowedItems,
    };
}
