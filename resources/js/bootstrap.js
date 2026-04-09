import axios from 'axios';
import { resolveReauthTarget } from '@/Services/reauthContract';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

window.axios.interceptors.response.use(
    (response) => response,
    (error) => {
        const status = error?.response?.status;
        const redirectTo = resolveReauthTarget(error?.response?.data ?? {});
        const currentPath = window.location.pathname;

        if (status === 401 && redirectTo && !currentPath.startsWith('/auth/sso/callback')) {
            window.location.assign(redirectTo);
        }

        return Promise.reject(error);
    },
);
