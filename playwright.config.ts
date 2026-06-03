import { resolve } from 'path';
import { defineConfig, devices } from '@playwright/test';

const BASE_URL = process.env.E2E_APP_URL || 'http://127.0.0.1:8001';
const APP_URL = new URL(BASE_URL);
const E2E_DATABASE = resolve(process.cwd(), 'database', 'e2e.sqlite');
const SERVER_COMMAND = `${process.env.PHP_BINARY || 'php'} artisan config:clear --env=e2e && ${process.env.PHP_BINARY || 'php'} artisan serve --host=${APP_URL.hostname} --port=${APP_URL.port || '8000'} --env=e2e`;

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    workers: 1,
    reporter: [
        ['html', { outputFolder: 'tests/e2e/report' }],
        ['list'],
    ],
    use: {
        baseURL: BASE_URL,
        testIdAttribute: 'data-testid',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },
    webServer: {
        command: SERVER_COMMAND,
        env: {
            ...process.env,
            APP_ENV: 'e2e',
            APP_URL: BASE_URL,
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: E2E_DATABASE,
            MAIL_MAILER: 'array',
            QUEUE_CONNECTION: 'sync',
            SESSION_DRIVER: 'file',
        },
        url: BASE_URL,
        reuseExistingServer: false,
        timeout: 120000,
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
