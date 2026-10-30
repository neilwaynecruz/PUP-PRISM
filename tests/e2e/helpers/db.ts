import { execFileSync } from 'child_process';
import { DatabaseSync } from 'node:sqlite';
import { dirname, resolve } from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const PHP_BINARY = process.env.PHP_BINARY || 'php';
const PROJECT_ROOT = resolve(__dirname, '..', '..', '..');
const ARTISAN = resolve(PROJECT_ROOT, 'artisan');
const E2E_DATABASE = resolve(PROJECT_ROOT, 'database', 'e2e.sqlite');

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

export function confirmTwoFactor(email: string): void {
    runArtisan(['e2e:configure-two-factor', email]);
}

export function beginTwoFactorSetup(email: string): void {
    runArtisan(['e2e:configure-two-factor', email, '--pending']);
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
    const output = runArtisan([
        'e2e:prepare-handover-verification',
        assetTagCode,
        recipientEmail,
        token,
        '--wait=10',
    ]);

    return parseInt(output, 10);
}

export function getProductOnHandQtyBySku(sku: string): number {
    const database = new DatabaseSync(E2E_DATABASE, { readOnly: true });

    try {
        const row = database
            .prepare(
                `
                    select product_stocks.on_hand_qty
                    from product_stocks
                    inner join products on products.id = product_stocks.product_id
                    where products.sku = ?
                    limit 1
                `,
            )
            .get(sku) as { on_hand_qty?: number } | undefined;

        if (typeof row?.on_hand_qty !== 'number') {
            throw new Error(
                `Unable to find on-hand quantity for SKU [${sku}].`,
            );
        }

        return row.on_hand_qty;
    } finally {
        database.close();
    }
}

export function getProductIdBySku(sku: string): number {
    const database = new DatabaseSync(E2E_DATABASE, { readOnly: true });

    try {
        const row = database
            .prepare('select id from products where sku = ? limit 1')
            .get(sku) as { id?: number } | undefined;

        if (typeof row?.id !== 'number') {
            throw new Error(`Unable to find product ID for SKU [${sku}].`);
        }

        return row.id;
    } finally {
        database.close();
    }
}

/**
 * Insert an unread in-app notification for a user directly into the isolated E2E database.
 */
export function createUnreadNotification(email: string, title: string, url: string): void {
    const database = new DatabaseSync(E2E_DATABASE);

    try {
        const user = database
            .prepare('select id from users where email = ? limit 1')
            .get(email) as { id?: number } | undefined;

        if (typeof user?.id !== 'number') {
            throw new Error(`Unable to find user [${email}].`);
        }

        const now = new Date().toISOString().replace('T', ' ').slice(0, 19);

        database
            .prepare(
                `
                    insert into notifications (id, type, notifiable_type, notifiable_id, data, read_at, created_at, updated_at)
                    values (?, ?, ?, ?, ?, null, ?, ?)
                `,
            )
            .run(
                crypto.randomUUID(),
                'App\\Notifications\\E2eNotification',
                'App\\Models\\User',
                user.id,
                JSON.stringify({ category: 'inventory', severity: 'info', title, message: title, url }),
                now,
                now,
            );
    } finally {
        database.close();
    }
}
