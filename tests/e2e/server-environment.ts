import { resolve } from 'path';

export const e2eBaseUrl = process.env.E2E_APP_URL || 'http://127.0.0.1:8001';
export const e2eProjectRoot = resolve(import.meta.dirname, '..', '..');
export const e2eServerStatePath = resolve(
    e2eProjectRoot,
    'storage',
    'framework',
    'testing',
    'playwright-server.json',
);

export function e2eEnvironment(): NodeJS.ProcessEnv {
    return {
        ...process.env,
        APP_ENV: 'e2e',
        APP_URL: e2eBaseUrl,
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: resolve(e2eProjectRoot, 'database', 'e2e.sqlite'),
        CACHE_STORE: 'array',
        MAIL_MAILER: 'array',
        QUEUE_CONNECTION: 'sync',
        SESSION_DRIVER: 'file',
    };
}
