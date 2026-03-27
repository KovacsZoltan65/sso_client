import { defineConfig, mergeConfig } from 'vitest/config';
import viteConfig from './vite.config.js';

export default mergeConfig(viteConfig, defineConfig({
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
}));
