import { defineConfig } from '@playwright/test';
import { authE2eConfig } from './tests/e2e/support/authE2eConfig.mjs';

export default defineConfig({
    testDir: './tests/e2e',
    timeout: 90_000,
    expect: {
        timeout: 10_000,
    },
    fullyParallel: false,
    workers: 1,
    reporter: [['list'], ['html', { open: 'never' }]],
    use: {
        baseURL: authE2eConfig.clientBaseUrl,
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        headless: true,
    },
    webServer: [
        {
            command: 'node tests/e2e/scripts/serve-auth-app.mjs server',
            url: authE2eConfig.serverBaseUrl,
            reuseExistingServer: true,
            timeout: 120_000,
        },
        {
            command: 'node tests/e2e/scripts/serve-auth-app.mjs client',
            url: authE2eConfig.clientBaseUrl,
            reuseExistingServer: true,
            timeout: 120_000,
        },
    ],
});
