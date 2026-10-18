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
];

const viewports = [
    { name: 'phone-375', width: 375, height: 812 },
    { name: 'tablet-768', width: 768, height: 1024 },
    { name: 'desktop-1440', width: 1440, height: 900 },
] as const;

test.describe('Phase 3 visual review', () => {
    test('login and key pages remain balanced across viewports', async ({
        page,
    }, testInfo) => {
        test.setTimeout(180000);

        await page.setViewportSize({ width: 1280, height: 900 });
        await page.goto('/login');
        await expect(page.getByTestId('login-page')).toBeVisible();
        await page.screenshot({
            path: testInfo.outputPath('login-desktop.png'),
            fullPage: true,
        });

        await loginAs(page, 'admin@e2e.test');

        for (const viewport of viewports) {
            await page.setViewportSize({
                width: viewport.width,
                height: viewport.height,
            });

            for (const target of pages) {
                await page.goto(target.path);

                if (target.testId) {
                    await expect(page.getByTestId(target.testId)).toBeVisible();
                } else {
                    await expect(
                        page.getByRole('heading', { level: 1 }),
                    ).toBeVisible();
                }

                const overflow = await page.evaluate(() => {
                    const root = document.documentElement;

                    return root.scrollWidth - root.clientWidth;
                });

                expect(
                    overflow,
                    `${target.name} overflow at ${viewport.name}`,
                ).toBeLessThanOrEqual(1);

                await page.screenshot({
                    path: testInfo.outputPath(
                        `${target.name}-${viewport.name}.png`,
                    ),
                    fullPage: true,
                });
            }
        }
    });
});
