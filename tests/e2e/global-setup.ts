import { execFileSync, spawn } from 'child_process';
import {
    copyFileSync,
    existsSync,
    mkdirSync,
    readdirSync,
    rmSync,
    writeFileSync,
} from 'fs';
import { DatabaseSync } from 'node:sqlite';
import { dirname, resolve } from 'path';
import {
    e2eBaseUrl,
    e2eDatabasePath,
    e2eEnvironment,
    e2eProjectRoot,
    e2eServerStatePath,
    e2eSessionDirectory,
    e2eSsrBundlePath,
    e2eTemplateDatabasePath,
    e2eTwoFactorFixturesPath,
} from './server-environment';

const startupTimeoutMilliseconds = 120_000;

type TwoFactorRow = {
    two_factor_secret: string;
    two_factor_recovery_codes: string;
};

async function isServerAvailable(): Promise<boolean> {
    try {
        const response = await fetch(e2eBaseUrl, {
            redirect: 'manual',
            signal: AbortSignal.timeout(5_000),
        });

        return response.status < 500;
    } catch {
        return false;
    }
}

function clearStaleSessionFiles(): void {
    const sessionsDirectory = e2eSessionDirectory;

    if (!existsSync(sessionsDirectory)) {
        mkdirSync(sessionsDirectory, { recursive: true });

        return;
    }

    for (const entry of readdirSync(sessionsDirectory)) {
        if (entry === '.gitignore') {
            continue;
        }

        rmSync(resolve(sessionsDirectory, entry), { force: true });
    }
}

function removeSqliteSidecars(databasePath: string): void {
    for (const suffix of ['-wal', '-shm', '-journal']) {
        rmSync(`${databasePath}${suffix}`, { force: true });
    }
}

function captureTwoFactorRow(email: string): TwoFactorRow {
    const database = new DatabaseSync(e2eDatabasePath, { readOnly: true });

    try {
        const row = database
            .prepare(
                'select two_factor_secret, two_factor_recovery_codes from users where email = ? limit 1',
            )
            .get(email) as TwoFactorRow | undefined;

        if (!row?.two_factor_secret || !row.two_factor_recovery_codes) {
            throw new Error(
                `Unable to capture encrypted two-factor fixture data for [${email}].`,
            );
        }

        return row;
    } finally {
        database.close();
    }
}

function prepareDatabaseSnapshot(
    phpBinary: string,
    environment: NodeJS.ProcessEnv,
): void {
    mkdirSync(dirname(e2eDatabasePath), { recursive: true });
    mkdirSync(dirname(e2eTemplateDatabasePath), { recursive: true });

    if (!existsSync(e2eDatabasePath)) {
        writeFileSync(e2eDatabasePath, '');
    }

    removeSqliteSidecars(e2eDatabasePath);

    execFileSync(
        phpBinary,
        ['artisan', 'e2e:setup', '--fresh', '--env=e2e', '--no-interaction'],
        {
            cwd: e2eProjectRoot,
            env: environment,
            stdio: 'ignore',
            timeout: 120_000,
        },
    );

    const database = new DatabaseSync(e2eDatabasePath);

    try {
        database.exec('pragma journal_mode = delete;');
    } finally {
        database.close();
    }

    removeSqliteSidecars(e2eDatabasePath);
    copyFileSync(e2eDatabasePath, e2eTemplateDatabasePath);

    execFileSync(
        phpBinary,
        [
            'artisan',
            'e2e:configure-two-factor',
            'admin@e2e.test',
            '--env=e2e',
            '--no-interaction',
        ],
        {
            cwd: e2eProjectRoot,
            env: environment,
            stdio: 'ignore',
            timeout: 120_000,
        },
    );
    const confirmed = captureTwoFactorRow('admin@e2e.test');

    execFileSync(
        phpBinary,
        [
            'artisan',
            'e2e:configure-two-factor',
            'admin@e2e.test',
            '--pending',
            '--env=e2e',
            '--no-interaction',
        ],
        {
            cwd: e2eProjectRoot,
            env: environment,
            stdio: 'ignore',
            timeout: 120_000,
        },
    );
    const pending = captureTwoFactorRow('admin@e2e.test');

    writeFileSync(
        e2eTwoFactorFixturesPath,
        JSON.stringify({ confirmed, pending }),
        'utf8',
    );

    removeSqliteSidecars(e2eDatabasePath);
    copyFileSync(e2eTemplateDatabasePath, e2eDatabasePath);
}

export default async function globalSetup(): Promise<void> {
    if (await isServerAvailable()) {
        throw new Error(
            `Port ${new URL(e2eBaseUrl).port} is already in use. Stop the existing server before running E2E tests.`,
        );
    }

    const environment = e2eEnvironment();
    const phpBinary = process.env.PHP_BINARY || 'php';
    const publicDirectory = resolve(e2eProjectRoot, 'public');
    const buildManifest = resolve(publicDirectory, 'build', 'manifest.json');

    if (!existsSync(buildManifest)) {
        throw new Error(
            'The production asset manifest is missing. Run `npm run build` before the E2E suite.',
        );
    }

    if (!existsSync(e2eSsrBundlePath)) {
        const viteCli = resolve(
            e2eProjectRoot,
            'node_modules',
            'vite',
            'bin',
            'vite.js',
        );
        execFileSync(
            process.execPath,
            [viteCli, 'build', '--mode=e2e', '--ssr'],
            {
                cwd: e2eProjectRoot,
                env: environment,
                stdio: 'ignore',
                timeout: 180_000,
            },
        );
    }

    clearStaleSessionFiles();

    execFileSync(
        phpBinary,
        ['artisan', 'config:clear', '--env=e2e', '--no-interaction'],
        {
            cwd: e2eProjectRoot,
            env: environment,
            stdio: 'ignore',
            timeout: 120_000,
        },
    );

    prepareDatabaseSnapshot(phpBinary, environment);

    const applicationUrl = new URL(e2eBaseUrl);
    const serverEnvironment: NodeJS.ProcessEnv =
        process.platform === 'win32'
            ? environment
            : {
                  ...environment,
                  PHP_CLI_SERVER_WORKERS: '4',
              };
    const server = spawn(
        phpBinary,
        [
            '-d',
            'opcache.enable=1',
            '-d',
            'opcache.enable_cli=1',
            '-S',
            `${applicationUrl.hostname}:${applicationUrl.port || '8000'}`,
            '../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php',
        ],
        {
            cwd: publicDirectory,
            detached: true,
            env: serverEnvironment,
            stdio: 'ignore',
            windowsHide: true,
        },
    );

    if (!server.pid) {
        throw new Error('The isolated E2E application server could not start.');
    }

    mkdirSync(dirname(e2eServerStatePath), { recursive: true });
    writeFileSync(
        e2eServerStatePath,
        JSON.stringify({ pid: server.pid, url: e2eBaseUrl }),
        'utf8',
    );
    server.unref();

    const deadline = Date.now() + startupTimeoutMilliseconds;

    while (Date.now() < deadline) {
        if (await isServerAvailable()) {
            return;
        }

        await new Promise((resolvePromise) => setTimeout(resolvePromise, 250));
    }

    process.kill(server.pid);

    throw new Error(
        `The isolated E2E application server did not become available within ${startupTimeoutMilliseconds / 1000} seconds.`,
    );
}
