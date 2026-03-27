import { defineConfig, mergeConfig } from 'vitest/config';
import baseConfig from './vitest.config.js';

export default mergeConfig(baseConfig, defineConfig({
    test: {
        include: [
            'resources/js/tests/pages/Welcome.test.js',
            'resources/js/tests/pages/AuthLogin.test.js',
            'resources/js/tests/composables/useAuth.test.js',
        ],
    },
}));
