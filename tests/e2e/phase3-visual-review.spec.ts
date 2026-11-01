import { loginAs } from './fixtures/auth';
import { expect, test } from './fixtures/test';

const pages: Array<{
    path: string;
    name: string;
    testId?: string;
}> = [
    { path: '/dashboard', name: 'dashboard', testId: 'dashboard-page' },
    {
        path: '/inventory/products',
        name: 'products',
        testId: 'products-index-page',
    },
    { path: '/inventory/bookings', name: 'bookings', testId: 'bookings-page' },
    {
        path: '/inventory/requisitions',
        name: 'requisitions',
        testId: 'requisitions-index-page',
    },
    {
        path: '/inventory/purchase-orders',
        name: 'purchase-orders',
        testId: 'purchase-orders-index-page',
    },
    {
        path: '/inventory/receiving',
        name: 'receiving',
        testId: 'receiving-page',
    },
    {
        path: '/notifications',
        name: 'notifications',
    },
    { path: '/inventory/assets', name: 'assets' },
    { path: '/inventory/suppliers', name: 'suppliers' },
    {
        path: '/inventory/movements',
        name: 'stock-movements',
        testId: 'movements-index-page',
    },
    { path: '/inventory/counts', name: 'physical-counts' },
    { path: '/inventory/forecasting', name: 'forecasting' },
    {
        path: '/inventory/handover',
        name: 'handover',
        testId: 'handover-initiate-page',
    },
    {
        path: '/inventory/audit-logs',
        name: 'audit-logs',
        testId: 'audit-logs-page',
    },
    { path: '/inventory/trash', name: 'inventory-trash' },
    { path: '/admin/alerts', name: 'alerts' },
    { path: '/admin/operations/health', name: 'operations-health' },
    { path: '/admin/users', name: 'users' },
    { path: '/admin/departments', name: 'departments' },
    { path: '/admin/positions', name: 'positions' },
    { path: '/admin/categories', name: 'categories' },
    { path: '/admin/origins', name: 'origins' },
    { path: '/admin/locations', name: 'locations' },
    {
        path: '/settings/profile',
        name: 'settings-profile',
        testId: 'profile-settings-page',
    },
    { path: '/settings/security', name: 'settings-security' },
    { path: '/settings/appearance', name: 'settings-appearance' },
    { path: '/settings/notifications', name: 'settings-notifications' },
    { path: '/settings/api-tokens', name: 'settings-api-tokens' },
];

const viewports = [
    { name: 'phone-390', width: 390, height: 844 },
    { name: 'tablet-834', width: 834, height: 1112 },
    { name: 'desktop-1440', width: 1440, height: 900 },
] as const;

test.describe('Phase 3 visual review', () => {
    test('dashboard remains balanced in both themes across representative viewports', async ({
        page,
    }, testInfo) => {
        test.setTimeout(180_000);

        const browserErrors: string[] = [];
        page.on('pageerror', (error) => browserErrors.push(error.message));
        page.on('console', (message) => {
            const text = message.text();

            if (
                message.type() === 'error' &&
                !text.includes('fonts.googleapis.com') &&
                !text.includes('ERR_NETWORK_ACCESS_DENIED') &&
                !(
                    text.includes('localhost:8080') &&
                    (text.includes('WebSocket connection to') ||
                        text.includes(
                            'establish a connection to the server at ws',
                        ))
                )
            ) {
                browserErrors.push(text);
            }
        });

        await loginAs(page, 'admin@e2e.test');
        const fontFaces = await page.evaluate(async () => {
            const [inter, mono] = await Promise.all([
                document.fonts.load('400 16px Inter'),
                document.fonts.load('600 16px "JetBrains Mono"'),
            ]);

            return [inter.length, mono.length];
        });
        expect(fontFaces[0]).toBeGreaterThan(0);
        expect(fontFaces[1]).toBeGreaterThan(0);

        for (const appearance of ['light', 'dark'] as const) {
            await page.evaluate((value) => {
                localStorage.setItem('appearance', value);
            }, appearance);

            for (const viewport of viewports) {
                await page.setViewportSize(viewport);
                await page.goto('/dashboard');
                await expect(page.getByTestId('dashboard-page')).toBeVisible();

                if (appearance === 'dark') {
                    await expect(page.locator('html')).toHaveClass(/dark/);
                } else {
                    await expect(page.locator('html')).not.toHaveClass(/dark/);
                }

                const overflow = await page.evaluate(
                    () =>
                        document.documentElement.scrollWidth -
                        document.documentElement.clientWidth,
                );

                expect(
                    overflow,
                    `dashboard overflow at ${viewport.name} in ${appearance} mode`,
                ).toBeLessThanOrEqual(1);

                await page.screenshot({
                    path: testInfo.outputPath(
                        `dashboard-focused-${viewport.name}-${appearance}.png`,
                    ),
                    fullPage: true,
                });
            }
        }

        expect(browserErrors).toEqual([]);
    });

    test('login and key pages remain balanced across viewports', async ({
        page,
    }, testInfo) => {
        test.setTimeout(900_000);

        const browserErrors: string[] = [];
        page.on('pageerror', (error) => browserErrors.push(error.message));
        page.on('console', (message) => {
            const text = message.text();

            if (
                message.type() === 'error' &&
                !text.includes('fonts.googleapis.com') &&
                !text.includes('ERR_NETWORK_ACCESS_DENIED') &&
                !(
                    text.includes('localhost:8080') &&
                    (text.includes('WebSocket connection to') ||
                        text.includes(
                            'establish a connection to the server at ws',
                        ))
                )
            ) {
                browserErrors.push(text);
            }
        });

        await page.setViewportSize({ width: 1280, height: 900 });
        await page.goto('/login');
        await expect(page.getByTestId('login-page')).toBeVisible();
        await page.screenshot({
            path: testInfo.outputPath('login-desktop.png'),
            fullPage: true,
        });

        await loginAs(page, 'admin@e2e.test');

        for (const appearance of ['light', 'dark'] as const) {
            await page.evaluate((value) => {
                localStorage.setItem('appearance', value);
            }, appearance);

            for (const viewport of viewports) {
                await page.setViewportSize({
                    width: viewport.width,
                    height: viewport.height,
                });

                for (const target of pages) {
                    await page.goto(target.path);

                    if (page.url().includes('/user/confirm-password')) {
                        await page
                            .locator('input[name="password"]')
                            .fill('password');
                        await Promise.all([
                            page.waitForURL(new RegExp(`${target.path}$`)),
                            page
                                .getByRole('button', {
                                    name: 'Confirm password',
                                })
                                .click(),
                        ]);
                    }

                    if (target.testId) {
                        await expect(
                            page.getByTestId(target.testId),
                        ).toBeVisible();
                    } else {
                        await expect(
                            page.getByRole('heading', { level: 1 }),
                        ).toBeVisible();
                    }

                    if (appearance === 'dark') {
                        await expect(page.locator('html')).toHaveClass(/dark/);
                    } else {
                        await expect(page.locator('html')).not.toHaveClass(
                            /dark/,
                        );
                    }

                    const overflow = await page.evaluate(() => {
                        const root = document.documentElement;

                        return root.scrollWidth - root.clientWidth;
                    });

                    expect(
                        overflow,
                        `${target.name} overflow at ${viewport.name} in ${appearance} mode`,
                    ).toBeLessThanOrEqual(1);

                    await page.screenshot({
                        path: testInfo.outputPath(
                            `${target.name}-${viewport.name}-${appearance}.png`,
                        ),
                        fullPage: true,
                    });
                }
            }
        }

        expect(browserErrors).toEqual([]);
    });
});
