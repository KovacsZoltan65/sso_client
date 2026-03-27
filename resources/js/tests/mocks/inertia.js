import { reactive } from 'vue';

let currentPage = reactive({
    props: {
        auth: {},
        flash: {},
        sso: {
            status: {},
        },
    },
    url: '/',
});

export function setPageProps(props) {
    currentPage.props = props;
}

export function setPageUrl(url) {
    currentPage.url = url;
}

export function resetInertiaMocks() {
    currentPage = reactive({
        props: {
            auth: {},
            flash: {},
            sso: {
                status: {},
            },
        },
        url: '/',
    });
}

export function getPage() {
    return currentPage;
}
