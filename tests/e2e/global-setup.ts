import { execFileSync, spawn } from 'child_process';
import { existsSync, mkdirSync, writeFileSync } from 'fs';
import { dirname, resolve } from 'path';
import {
    e2eBaseUrl,
    e2eEnvironment,
    e2eProjectRoot,
    e2eServerStatePath,
} from './server-environment';

const startupTimeoutMilliseconds = 120_000;

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

    const applicationUrl = new URL(e2eBaseUrl);
    const server = spawn(
        phpBinary,
        [
            '-S',
            `${applicationUrl.hostname}:${applicationUrl.port || '8000'}`,
            '../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php',
        ],
        {
            cwd: publicDirectory,
            detached: true,
            env: environment,
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
