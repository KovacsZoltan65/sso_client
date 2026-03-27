import { config } from '@vue/test-utils';
import { afterEach, beforeEach, vi } from 'vitest';
import { defineComponent, h } from 'vue';
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
});

afterEach(() => {
    vi.clearAllMocks();
});
