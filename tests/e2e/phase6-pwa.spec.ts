import { loginAs, logout } from './fixtures/auth';
import { expect, test } from './fixtures/test';

test.describe('Phase 6 PWA safety', () => {
    test('offline screen remains readable across required viewport sizes', async ({
        page,
    }) => {
        const viewportWidths = [320, 375, 768, 1024, 1440];

        for (const width of viewportWidths) {
            const colorScheme = width === 375 ? 'dark' : 'light';

            await page.setViewportSize({ width, height: 900 });
            await page.emulateMedia({ colorScheme });
            await page.goto('/offline.html');

            await expect(
                page.getByRole('heading', { name: 'You are offline' }),
            ).toBeVisible();
            await expect(
                page.getByRole('button', { name: 'Try again' }),
            ).toBeVisible();

            const hasHorizontalOverflow = await page.evaluate(
                () => document.documentElement.scrollWidth > window.innerWidth,
            );

            expect(hasHorizontalOverflow).toBeFalsy();

            await page.screenshot({
                path: `test-results/phase6-visual/offline-${width}-${colorScheme}.png`,
                fullPage: true,
            });
        }
    });

    test('publishes one installable manifest with PRISM icons', async ({
        page,
    }) => {
        const response = await page.request.get('/manifest.webmanifest');

        expect(response.ok()).toBeTruthy();

        const manifest = (await response.json()) as {
            id: string;
            start_url: string;
            display: string;
            icons: Array<{ sizes: string; src: string }>;
        };

        expect(manifest.id).toBe('/');
        expect(manifest.start_url).toBe('/dashboard');
        expect(manifest.display).toBe('standalone');
        expect(manifest.icons).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    sizes: '192x192',
                    src: '/Icons/prism-icon-192x192.png',
                }),
                expect.objectContaining({
                    sizes: '512x512',
                    src: '/Icons/prism-icon-512x512.png',
                }),
            ]),
        );

        for (const icon of manifest.icons) {
            const iconResponse = await page.request.get(icon.src);

            expect(iconResponse.ok()).toBeTruthy();
            expect(iconResponse.headers()['content-type']).toContain(
                'image/png',
            );
        }
    });

    test('shows a clear offline warning and never caches authenticated pages', async ({
        context,
        page,
    }) => {
        await loginAs(page, 'admin@e2e.test');
        await page.goto('/dashboard');

        await page.waitForFunction(async () => {
            if (!('serviceWorker' in navigator)) {
                return false;
            }

            await navigator.serviceWorker.ready;

            return Boolean(navigator.serviceWorker.controller);
        });

        const cachedUrls = await page.evaluate(async () => {
            const urls: string[] = [];

            for (const cacheName of await caches.keys()) {
                const cache = await caches.open(cacheName);

                for (const request of await cache.keys()) {
                    urls.push(request.url);
                }
            }

            return urls;
        });

        expect(
            cachedUrls.some((url) => {
                const pathname = new URL(url).pathname;

                return (
                    pathname === '/dashboard' ||
                    pathname.startsWith('/inventory') ||
                    pathname.startsWith('/api')
                );
            }),
        ).toBeFalsy();

        await context.setOffline(true);
        await page.evaluate(() => window.dispatchEvent(new Event('offline')));

        await expect(page.getByText('You are offline')).toBeVisible();
        await expect(
            page.getByText(
                'Reconnect before submitting changes. PRISM does not save new inventory work while offline.',
            ),
        ).toBeVisible();

        await page.reload({ waitUntil: 'domcontentloaded' });
        await expect(
            page.getByRole('heading', { name: 'You are offline' }),
        ).toBeVisible();
        await expect(
            page.getByText(
                'No new inventory changes have been saved or submitted while this page is offline.',
            ),
        ).toBeVisible();

        await context.setOffline(false);
    });

    test('offers installation only after the browser emits an install prompt', async ({
        page,
    }) => {
        await loginAs(page, 'admin@e2e.test');
        await page.goto('/dashboard');

        await expect(page.getByText('Install PUP PRISM')).toHaveCount(0);

        await page.evaluate(() => {
            const promptEvent = new Event('beforeinstallprompt', {
                cancelable: true,
            });
            Object.assign(promptEvent, {
                prompt: async () => undefined,
                userChoice: Promise.resolve({
                    outcome: 'accepted',
                    platform: 'web',
                }),
            });
            window.dispatchEvent(promptEvent);
        });

        await expect(page.getByText('Install PUP PRISM')).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Install app' }),
        ).toBeVisible();
    });

    test("does not retain one user's protected data after another user signs in", async ({
        context,
        page,
    }) => {
        await loginAs(page, 'admin@e2e.test');
        await expect(page.getByTestId('dashboard-page')).toBeVisible();
        await expect(
            page.getByText('Adrian Mercado', { exact: true }),
        ).toBeVisible();

        await logout(page);
        await loginAs(page, 'recipient@e2e.test');
        await expect(page.getByTestId('dashboard-page')).toBeVisible();
        await expect(
            page.getByText('Grace Bautista', { exact: true }),
        ).toBeVisible();
        await expect(
            page.getByText('Adrian Mercado', { exact: true }),
        ).toHaveCount(0);

        const browserStorage = await page.evaluate(async () => {
            const cacheUrls: string[] = [];

            for (const cacheName of await caches.keys()) {
                const cache = await caches.open(cacheName);

                for (const request of await cache.keys()) {
                    cacheUrls.push(request.url);
                }
            }

            const indexedDatabaseNames =
                'databases' in indexedDB
                    ? (await indexedDB.databases())
                          .map((database) => database.name)
                          .filter((name): name is string => Boolean(name))
                    : [];

            return {
                cacheUrls,
                indexedDatabaseNames,
                localStorage: { ...localStorage },
                sessionStorage: { ...sessionStorage },
            };
        });

        const serializedStorage = JSON.stringify(browserStorage);

        expect(serializedStorage).not.toContain('admin@e2e.test');
        expect(serializedStorage).not.toContain('Adrian Mercado');
        expect(browserStorage.indexedDatabaseNames).toEqual([]);
        expect(
            browserStorage.cacheUrls.some((url) => {
                const pathname = new URL(url).pathname;

                return (
                    pathname === '/dashboard' ||
                    pathname.startsWith('/inventory') ||
                    pathname.startsWith('/api')
                );
            }),
        ).toBeFalsy();

        await context.setOffline(true);
        await page.reload({ waitUntil: 'domcontentloaded' });
        await expect(
            page.getByRole('heading', { name: 'You are offline' }),
        ).toBeVisible();
        await expect(
            page.getByText('Adrian Mercado', { exact: true }),
        ).toHaveCount(0);
        await context.setOffline(false);
    });

    test('dashboard and forecasting pages do not emit framework or chart warnings', async ({
        page,
    }) => {
        const actionableWarnings: string[] = [];

        page.on('console', (message) => {
            const text = message.text();

            if (
                message.type() === 'warning' &&
                (text.includes('Extraneous non-props attributes') ||
                    text.includes("'Filler' plugin"))
            ) {
                actionableWarnings.push(text);
            }
        });

        await loginAs(page, 'admin@e2e.test');
        await expect(page.getByTestId('dashboard-page')).toBeVisible();

        await page.goto('/inventory/forecasting');
        await expect(
            page.getByRole('heading', {
                name: 'Forecasting & Inventory Optimization',
            }),
        ).toBeVisible();

        expect(actionableWarnings).toEqual([]);
    });
});
