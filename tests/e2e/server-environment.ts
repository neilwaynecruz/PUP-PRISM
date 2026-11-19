import { resolve } from 'path';

export const e2eBaseUrl = process.env.E2E_APP_URL || 'http://127.0.0.1:8001';
export const e2eProjectRoot = resolve(import.meta.dirname, '..', '..');
export const e2eSessionDirectory = resolve(
    e2eProjectRoot,
    'storage/framework/testing/e2e-sessions',
);
export const e2eDatabasePath = resolve(
    e2eProjectRoot,
    'database',
    'e2e.sqlite',
);
export const e2eTemplateDatabasePath = resolve(
    e2eProjectRoot,
    'storage',
    'framework',
    'testing',
    'e2e.template.sqlite',
);
export const e2eTwoFactorFixturesPath = resolve(
    e2eProjectRoot,
    'storage',
    'framework',
    'testing',
    'e2e-two-factor.json',
);
export const e2eSsrBundlePath = resolve(
    e2eProjectRoot,
    'bootstrap',
    'ssr',
    'app.js',
);
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
        APP_CONFIG_CACHE: resolve(
            e2eProjectRoot,
            'bootstrap/cache/e2e-config.php',
        ),
        APP_URL: e2eBaseUrl,
        BOOST_BROWSER_LOGS_WATCHER: 'false',
        BCRYPT_ROUNDS: '4',
        INERTIA_SSR_ENABLED: 'false',
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: resolve(e2eProjectRoot, 'database', 'e2e.sqlite'),
        DB_SQLITE_BUSY_TIMEOUT: '5000',
        CACHE_STORE: 'array',
        MAIL_MAILER: 'array',
        QUEUE_CONNECTION: 'sync',
        SESSION_DRIVER: 'file',
        SESSION_FILES: e2eSessionDirectory,
    };
}
