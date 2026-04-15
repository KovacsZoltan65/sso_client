import { config } from '@vue/test-utils';
import { afterEach, beforeEach, vi } from 'vitest';
import { defineComponent, h, reactive, ref } from 'vue';
import en from '../../../lang/en.json';
import hu from '../../../lang/hu.json';
import { axiosMock, resetAxiosMock } from './mocks/axios';
import { getPage, resetInertiaMocks } from './mocks/inertia';

const translations = { en, hu };

const translate = (key, replacements = {}) => {
    const locale = getPage().props.locale?.current ?? 'hu';
    const fallback = getPage().props.locale?.fallback ?? 'en';
    const message = translations[locale]?.[key] ?? translations[fallback]?.[key] ?? key;

    return Object.entries(replacements).reduce(
        (text, [name, value]) => text.replaceAll(`:${name}`, String(value)),
        message,
    );
};

const ButtonStub = defineComponent({
    inheritAttrs: false,
    props: {
        label: {
            type: String,
            default: '',
        },
        loading: {
            type: Boolean,
            default: false,
        },
        disabled: {
            type: Boolean,
            default: false,
        },
    },
    emits: ['click'],
    setup(props, { attrs, emit, slots }) {
        return () => h('button', {
            ...attrs,
            disabled: props.disabled,
            'data-loading': props.loading ? 'true' : 'false',
            onClick: (event) => emit('click', event),
        }, slots.default ? slots.default() : props.label);
    },
});

const LinkStub = defineComponent({
    inheritAttrs: false,
    props: {
        href: {
            type: String,
            default: '',
        },
        method: {
            type: String,
            default: 'get',
        },
        as: {
            type: String,
            default: 'a',
        },
    },
    setup(props, { attrs, slots }) {
        return () => h(props.as === 'button' ? 'button' : 'a', {
            ...attrs,
            href: props.href,
            'data-method': props.method,
        }, slots.default?.());
    },
});

const makeForm = (initial = {}) => reactive({
    ...initial,
    errors: {},
    processing: false,
    post: vi.fn(),
    patch: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
    reset(...fields) {
        if (fields.length === 0) {
            Object.keys(initial).forEach((key) => {
                this[key] = initial[key];
            });

            return;
        }

        fields.forEach((field) => {
            if (Object.prototype.hasOwnProperty.call(initial, field)) {
                this[field] = initial[field];
            }
        });
    },
    clearErrors(...fields) {
        if (fields.length === 0) {
            this.errors = {};
            return;
        }

        fields.forEach((field) => {
            delete this.errors[field];
        });
    },
});

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => getPage(),
    useForm: (initial = {}) => makeForm(initial),
    Head: defineComponent({
        setup(_props, { slots }) {
            return () => h('div', { 'data-head': 'true' }, slots.default?.());
        },
    }),
    Link: LinkStub,
}));

vi.mock('laravel-vue-i18n', () => ({
    trans: translate,
    wTrans: (key, replacements = {}) => ({ value: translate(key, replacements) }),
    i18nVue: {
        install(app) {
            app.config.globalProperties.$t = translate;
            app.config.globalProperties.trans = translate;
        },
    },
}));

vi.mock('primevue/button', () => ({ default: ButtonStub }));
vi.mock('primevue/menu', () => ({ default: defineComponent({
    inheritAttrs: false,
    props: {
        model: {
            type: Array,
            default: () => [],
        },
        popup: {
            type: Boolean,
            default: false,
        },
        appendTo: {
            type: String,
            default: null,
        },
        pt: {
            type: Object,
            default: () => ({}),
        },
    },
    setup(props, { attrs, expose }) {
        const open = ref(false);

        expose({
            toggle: () => {
                open.value = !open.value;
            },
            hide: () => {
                open.value = false;
            },
        });

        return () => open.value ? h('div', {
            ...attrs,
            'data-menu-popup': props.popup ? 'true' : 'false',
            'data-menu-append-to': props.appendTo ?? '',
        }, props.model.map((item) => h('button', {
            type: 'button',
            disabled: Boolean(item.disabled),
            onClick: () => item.command?.({ item }),
        }, item.label))) : null;
    },
}) }));
vi.mock('primevue/inputtext', () => ({ default: defineComponent({
    inheritAttrs: false,
    props: {
        modelValue: {
            type: String,
            default: '',
        },
        disabled: {
            type: Boolean,
            default: false,
        },
        readonly: {
            type: Boolean,
            default: false,
        },
    },
    emits: ['update:modelValue'],
    setup(props, { attrs, emit }) {
        return () => h('input', {
            ...attrs,
            value: props.modelValue,
            disabled: props.disabled,
            readonly: props.readonly,
            onInput: (event) => emit('update:modelValue', event.target.value),
        });
    },
}) }));
vi.mock('primevue/password', () => ({ default: defineComponent({
    inheritAttrs: false,
    props: {
        modelValue: {
            type: String,
            default: '',
        },
        disabled: {
            type: Boolean,
            default: false,
        },
    },
    emits: ['update:modelValue'],
    setup(props, { attrs, emit }) {
        return () => h('input', {
            ...attrs,
            type: 'password',
            value: props.modelValue,
            disabled: props.disabled,
            onInput: (event) => emit('update:modelValue', event.target.value),
        });
    },
}) }));
vi.mock('primevue/message', () => ({ default: defineComponent({
    setup(_props, { slots }) {
        return () => h('div', { 'data-message': 'true' }, slots.default?.());
    },
}) }));
vi.mock('primevue/progressspinner', () => ({ default: defineComponent({
    setup() {
        return () => h('div', { 'data-spinner': 'true' });
    },
}) }));
vi.mock('primevue/toast', () => ({ default: defineComponent({
    setup() {
        return () => h('div', { 'data-toast': 'true' });
    },
}) }));
export const toastAddMock = vi.fn();
export const confirmRequireMock = vi.fn();
vi.mock('primevue/usetoast', () => ({
    useToast: () => ({
        add: toastAddMock,
    }),
}));
vi.mock('primevue/useconfirm', () => ({
    useConfirm: () => ({
        require: confirmRequireMock,
    }),
}));
vi.mock('primevue/dialog', () => ({ default: defineComponent({
    name: 'DialogStub',
    props: {
        visible: {
            type: Boolean,
            default: false,
        },
    },
    setup(props, { slots }) {
        return () => props.visible ? h('div', { 'data-dialog': 'true' }, slots.default?.()) : null;
    },
}) }));
vi.mock('primevue/confirmdialog', () => ({ default: defineComponent({
    setup() {
        return () => h('div', { 'data-confirm-dialog': 'true' });
    },
}) }));
vi.mock('primevue/datatable', () => ({ default: defineComponent({
    name: 'DataTableStub',
    props: {
        scrollable: {
            type: Boolean,
            default: false,
        },
        scrollHeight: {
            type: String,
            default: null,
        },
        value: {
            type: Array,
            default: () => [],
        },
        rows: {
            type: Number,
            default: 0,
        },
        totalRecords: {
            type: Number,
            default: 0,
        },
    },
    setup(props, { attrs, slots }) {
        return () => h('div', {
            ...attrs,
            'data-datatable': 'true',
            'data-scrollable': props.scrollable ? 'true' : 'false',
            'data-scroll-height': props.scrollHeight ?? '',
        }, slots.default?.());
    },
}) }));
vi.mock('primevue/column', () => ({ default: defineComponent({
    name: 'ColumnStub',
    setup() {
        return () => null;
    },
}) }));
vi.mock('primevue/select', () => ({ default: defineComponent({
    inheritAttrs: false,
    props: {
        modelValue: {
            default: null,
        },
        options: {
            type: Array,
            default: () => [],
        },
        optionLabel: {
            type: String,
            default: 'label',
        },
        optionValue: {
            type: String,
            default: 'value',
        },
    },
    emits: ['update:modelValue'],
    setup(props, { attrs, emit }) {
        return () => h('select', {
            ...attrs,
            value: props.modelValue === null ? '' : String(props.modelValue),
            onChange: (event) => {
                const selected = props.options.find((option) => String(option[props.optionValue] ?? '') === event.target.value);
                emit('update:modelValue', selected ? selected[props.optionValue] : null);
            },
        }, [
            h('option', { value: '' }, ''),
            ...props.options.map((option) => h('option', { value: String(option[props.optionValue] ?? '') }, option[props.optionLabel])),
        ]);
    },
}) }));
vi.mock('primevue/checkbox', () => ({ default: defineComponent({
    inheritAttrs: false,
    props: {
        modelValue: {
            default: false,
        },
    },
    emits: ['update:modelValue'],
    setup(props, { attrs, emit }) {
        return () => h('input', {
            ...attrs,
            type: 'checkbox',
            checked: Boolean(props.modelValue),
            onChange: (event) => emit('update:modelValue', event.target.checked),
        });
    },
}) }));
vi.mock('primevue/textarea', () => ({ default: defineComponent({
    inheritAttrs: false,
    props: {
        modelValue: {
            type: String,
            default: '',
        },
    },
    emits: ['update:modelValue'],
    setup(props, { attrs, emit }) {
        return () => h('textarea', {
            ...attrs,
            value: props.modelValue,
            onInput: (event) => emit('update:modelValue', event.target.value),
        });
    },
}) }));
vi.mock('primevue/tag', () => ({ default: defineComponent({
    props: {
        value: {
            type: String,
            default: '',
        },
    },
    setup(props) {
        return () => h('span', { 'data-tag': 'true' }, props.value);
    },
}) }));
vi.mock('axios', () => ({
    default: axiosMock,
}));

global.route = vi.fn((name) => {
    if (!name) {
        return {
            current: () => false,
        };
    }

    return `/${String(name).replace(/\./g, '/')}`;
});
global.ResizeObserver = class {
    observe() {}
    unobserve() {}
    disconnect() {}
};

config.global.stubs = {
    teleport: true,
};

config.global.mocks = {
    $t: (...args) => translate(...args),
    trans: (...args) => translate(...args),
    route: (...args) => global.route(...args),
};

beforeEach(() => {
    resetInertiaMocks();
    resetAxiosMock();
    toastAddMock.mockReset();
});

afterEach(() => {
    vi.clearAllMocks();
});
