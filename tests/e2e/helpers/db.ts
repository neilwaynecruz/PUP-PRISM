import { execFileSync } from 'child_process';
import { dirname, resolve } from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const PHP_BINARY = process.env.PHP_BINARY || 'php';
const PROJECT_ROOT = resolve(__dirname, '..', '..', '..');
const ARTISAN = resolve(PROJECT_ROOT, 'artisan');

function runArtisan(args: string[]): string {
    const result = execFileSync(
        PHP_BINARY,
        [ARTISAN, ...args, '--no-interaction', '--env=e2e'],
        {
            cwd: PROJECT_ROOT,
            encoding: 'utf8',
            timeout: 120000,
        },
    );

    return result.trim();
}

export function resetDatabase(): void {
    runArtisan(['e2e:setup', '--fresh']);
}

export function verifyEmail(email: string): void {
    runArtisan(['e2e:verify-email', email]);
}

export function getUserIdByEmail(email: string): number {
    const output = runArtisan(['e2e:user-id', email]);

    return parseInt(output, 10);
}

export function getLatestRequisitionId(requesterEmail: string): number {
    const output = runArtisan(['e2e:latest-requisition-id', requesterEmail]);

    return parseInt(output, 10);
}

export function preparePendingHandoverVerification(
    assetTagCode: string,
    recipientEmail: string,
    token: string,
): number {
    const output = runArtisan(
        ['e2e:prepare-handover-verification', assetTagCode, recipientEmail, token, '--wait=10'],
    );

    return parseInt(output, 10);
}
