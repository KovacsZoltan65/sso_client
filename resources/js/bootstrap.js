import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

window.axios.interceptors.response.use(
    (response) => response,
    (error) => {
        const status = error?.response?.status;
        const redirectTo = error?.response?.data?.reauth_to || error?.response?.data?.redirect_to;
        const currentPath = window.location.pathname;

        if (status === 401 && redirectTo && !currentPath.startsWith('/auth/sso/callback')) {
            window.location.assign(redirectTo);
        }

        return Promise.reject(error);
    },
);
