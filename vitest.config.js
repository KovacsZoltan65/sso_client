import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    test: {
        environment: 'jsdom',
        globals: true,
        setupFiles: ['resources/js/tests/setup.js'],
        exclude: [
            'tests/e2e/**',
            'node_modules/**',
        ],
    },
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
});
