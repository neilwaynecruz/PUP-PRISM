import { execFileSync } from 'child_process';
import { copyFileSync, existsSync, readFileSync, rmSync } from 'fs';
import { DatabaseSync } from 'node:sqlite';
import { dirname, resolve } from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const PHP_BINARY = process.env.PHP_BINARY || 'php';
const PROJECT_ROOT = resolve(__dirname, '..', '..', '..');
const ARTISAN = resolve(PROJECT_ROOT, 'artisan');
const E2E_DATABASE = resolve(PROJECT_ROOT, 'database', 'e2e.sqlite');
const E2E_TEMPLATE_DATABASE = resolve(
    PROJECT_ROOT,
    'storage',
    'framework',
    'testing',
    'e2e.template.sqlite',
);
const E2E_TWO_FACTOR_FIXTURES = resolve(
    PROJECT_ROOT,
    'storage',
    'framework',
    'testing',
    'e2e-two-factor.json',
);

type TwoFactorRow = {
    two_factor_secret: string;
    two_factor_recovery_codes: string;
};

type TwoFactorFixtures = {
    confirmed: TwoFactorRow;
    pending: TwoFactorRow;
};

let cachedTwoFactorFixtures: TwoFactorFixtures | null = null;

function formatSqliteTimestamp(): string {
    return new Date().toISOString().replace('T', ' ').slice(0, 19);
}

function sleepSync(milliseconds: number): void {
    Atomics.wait(new Int32Array(new SharedArrayBuffer(4)), 0, 0, milliseconds);
}

function getTwoFactorFixtures(): TwoFactorFixtures | null {
    if (cachedTwoFactorFixtures) {
        return cachedTwoFactorFixtures;
    }

    if (!existsSync(E2E_TWO_FACTOR_FIXTURES)) {
        return null;
    }

    cachedTwoFactorFixtures = JSON.parse(
        readFileSync(E2E_TWO_FACTOR_FIXTURES, 'utf8'),
    ) as TwoFactorFixtures;

    return cachedTwoFactorFixtures;
}

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
    if (!existsSync(E2E_TEMPLATE_DATABASE)) {
        runArtisan(['e2e:setup', '--fresh']);

        return;
    }

    for (let attempt = 0; attempt < 20; attempt++) {
        try {
            for (const suffix of ['-wal', '-shm', '-journal']) {
                rmSync(`${E2E_DATABASE}${suffix}`, { force: true });
            }

            copyFileSync(E2E_TEMPLATE_DATABASE, E2E_DATABASE);

            return;
        } catch (error) {
            const code = (error as NodeJS.ErrnoException).code;

            if (attempt === 19 || (code !== 'EBUSY' && code !== 'EPERM')) {
                throw error;
            }

            sleepSync(15);
        }
    }
}

function isSqliteLockError(error: unknown): boolean {
    if (!(error instanceof Error)) {
        return false;
    }

    const message = error.message.toLowerCase();

    return (
        message.includes('database is locked') ||
        message.includes('sqlite_busy') ||
        message.includes('ebusy')
    );
}

function withDatabase<T>(
    readOnly: boolean,
    callback: (database: DatabaseSync) => T,
): T {
    for (let attempt = 0; attempt < 20; attempt++) {
        let database: DatabaseSync | null = null;

        try {
            database = readOnly
                ? new DatabaseSync(E2E_DATABASE, { readOnly: true })
                : new DatabaseSync(E2E_DATABASE);
            database.exec('pragma busy_timeout = 5000;');

            return callback(database);
        } catch (error) {
            if (attempt === 19 || !isSqliteLockError(error)) {
                throw error;
            }

            sleepSync(25);
        } finally {
            database?.close();
        }
    }

    throw new Error('Exhausted SQLite lock retries.');
}

export function verifyEmail(email: string): void {
    withDatabase(false, (database) => {
        const user = database
            .prepare(
                'select id, email_verified_at from users where email = ? limit 1',
            )
            .get(email) as
            | { id?: number; email_verified_at?: string | null }
            | undefined;

        if (typeof user?.id !== 'number') {
            throw new Error(`User with email '${email}' not found.`);
        }

        if (user.email_verified_at) {
            return;
        }

        const now = formatSqliteTimestamp();

        database
            .prepare(
                'update users set email_verified_at = ?, updated_at = ? where id = ?',
            )
            .run(now, now, user.id);
    });
}

function applyTwoFactorState(email: string, pending: boolean): void {
    const fixtures = getTwoFactorFixtures();

    if (!fixtures) {
        runArtisan(
            pending
                ? ['e2e:configure-two-factor', email, '--pending']
                : ['e2e:configure-two-factor', email],
        );

        return;
    }

    const row = pending ? fixtures.pending : fixtures.confirmed;
    const now = formatSqliteTimestamp();

    withDatabase(false, (database) => {
        const result = database
            .prepare(
                `
                    update users
                    set two_factor_secret = ?,
                        two_factor_recovery_codes = ?,
                        two_factor_confirmed_at = ?,
                        updated_at = ?
                    where email = ?
                `,
            )
            .run(
                row.two_factor_secret,
                row.two_factor_recovery_codes,
                pending ? null : now,
                now,
                email,
            );

        if (result.changes === 0) {
            throw new Error(`User with email '${email}' was not found.`);
        }
    });
}

export function confirmTwoFactor(email: string): void {
    applyTwoFactorState(email, false);
}

export function beginTwoFactorSetup(email: string): void {
    applyTwoFactorState(email, true);
}

export function getUserIdByEmail(email: string): number {
    return withDatabase(true, (database) => {
        const row = database
            .prepare('select id from users where email = ? limit 1')
            .get(email) as { id?: number } | undefined;

        if (typeof row?.id !== 'number') {
            throw new Error(`User with email '${email}' not found.`);
        }

        return row.id;
    });
}

export function getLatestRequisitionId(requesterEmail: string): number {
    return withDatabase(true, (database) => {
        const row = database
            .prepare(
                `
                    select requisitions.id
                    from requisitions
                    inner join users on users.id = requisitions.requester_id
                    where users.email = ?
                    order by requisitions.id desc
                    limit 1
                `,
            )
            .get(requesterEmail) as { id?: number } | undefined;

        if (typeof row?.id !== 'number') {
            throw new Error(
                `No requisition found for requester '${requesterEmail}'.`,
            );
        }

        return row.id;
    });
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
    return withDatabase(true, (database) => {
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
    });
}

export function getProductIdBySku(sku: string): number {
    return withDatabase(true, (database) => {
        const row = database
            .prepare('select id from products where sku = ? limit 1')
            .get(sku) as { id?: number } | undefined;

        if (typeof row?.id !== 'number') {
            throw new Error(`Unable to find product ID for SKU [${sku}].`);
        }

        return row.id;
    });
}

/**
 * Insert an unread in-app notification for a user directly into the isolated E2E database.
 */
export function createUnreadNotification(
    email: string,
    title: string,
    url: string,
): void {
    withDatabase(false, (database) => {
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
                JSON.stringify({
                    category: 'inventory',
                    severity: 'info',
                    title,
                    message: title,
                    url,
                }),
                now,
                now,
            );
    });
}
