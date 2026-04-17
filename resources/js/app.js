import "../css/app.css";
import "./bootstrap";

import { createInertiaApp } from "@inertiajs/vue3";
import PrimeVue from "primevue/config";
import Aura from "@primeuix/themes/aura";
import { i18nVue } from "laravel-vue-i18n";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { createApp, h } from "vue";
import ConfirmationService from "primevue/confirmationservice";
import ToastService from "primevue/toastservice";
import { ZiggyVue } from "../../vendor/tightenco/ziggy";
import "primeicons/primeicons.css";

const appName = import.meta.env.VITE_APP_NAME || "SSO Client";
//const langFiles = import.meta.glob('../../lang/*.json');

//const resolveLanguage = async (lang) => {
//    const loader = langFiles[`../../lang/${lang}.json`] ?? langFiles['../../lang/en.json'];
//    const messages = await loader();

//    return messages.default;
//};

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob("./Pages/**/*.vue"),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(i18nVue, {
                locale:
                    props?.initialPage?.props?.locale?.current ||
                    document.documentElement.getAttribute("lang") ||
                    "hu",
                fallbackLocale:
                    props?.initialPage?.props?.locale?.fallback ||
                    "en",
                resolve: async (lang) => {
                    const messages = import.meta.glob("../../lang/*.json"); // */
                    return await messages[`../../lang/${lang}.json`]();
                },
            })
            .use(ZiggyVue)
            .use(ToastService)
            .use(ConfirmationService)
            .use(PrimeVue, {
                theme: {
                    preset: Aura,
                    options: {
                        darkModeSelector: false,
                    },
                },
                ripple: true,
            })
            .mount(el);
    },
    progress: {
        color: "#1f6feb",
    },
});
