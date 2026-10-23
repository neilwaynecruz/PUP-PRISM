import { defineConfig, devices } from '@playwright/test';
import { e2eBaseUrl } from './tests/e2e/server-environment';

export default defineConfig({
    testDir: './tests/e2e',
    globalSetup: './tests/e2e/global-setup.ts',
    globalTeardown: './tests/e2e/global-teardown.ts',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    workers: 1,
    reporter: [['html', { outputFolder: 'tests/e2e/report' }], ['list']],
    use: {
        baseURL: e2eBaseUrl,
        testIdAttribute: 'data-testid',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },
    projects: [
        {
            name: 'chromium',
            use: {
                ...devices['Desktop Chrome'],
                viewport: { width: 1280, height: 900 },
            },
        },
    ],
    timeout: 60000,
    expect: {
        timeout: 15000,
    },
});
