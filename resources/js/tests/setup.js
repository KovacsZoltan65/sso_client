import { config } from '@vue/test-utils';
import { afterEach, beforeEach, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import { axiosMock, resetAxiosMock } from './mocks/axios';
import { getPage, resetInertiaMocks } from './mocks/inertia';

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

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => getPage(),
    Head: defineComponent({
        setup(_props, { slots }) {
            return () => h('div', { 'data-head': 'true' }, slots.default?.());
        },
    }),
    Link: LinkStub,
}));

vi.mock('primevue/button', () => ({ default: ButtonStub }));
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
vi.mock('primevue/usetoast', () => ({
    useToast: () => ({
        add: toastAddMock,
    }),
}));
vi.mock('axios', () => ({
    default: axiosMock,
}));

global.route = vi.fn((name) => `/${String(name).replace(/\./g, '/')}`);
global.ResizeObserver = class {
    observe() {}
    unobserve() {}
    disconnect() {}
};

config.global.stubs = {
    teleport: true,
};

config.global.mocks = {
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
