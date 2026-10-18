import { loginAs } from './fixtures/auth';
import { expect, test } from './fixtures/test';

const representativePages = [
    { path: '/dashboard', heading: 'Dashboard' },
    { path: '/inventory/products', heading: 'Products' },
    { path: '/inventory/bookings', heading: 'Asset booking calendar' },
    { path: '/inventory/requisitions', heading: 'Requisitions' },
    { path: '/inventory/purchase-orders', heading: 'Purchase orders' },
    { path: '/inventory/receiving', heading: 'Receiving' },
    { path: '/notifications', heading: 'Notification history' },
    {
        path: '/settings/profile',
        heading: 'Settings',
        sectionHeading: 'Profile information',
    },
];

test.describe('Phase 3 accessibility and responsive layout', () => {
    test('major workflows expose one descriptive page heading', async ({
        page,
    }) => {
        await loginAs(page, 'admin@e2e.test');

        for (const item of representativePages) {
            await page.goto(item.path);
            await expect(page.getByRole('heading', { level: 1 })).toHaveCount(
                1,
            );
            await expect(
                page.getByRole('heading', {
                    level: 1,
                    name: item.heading,
                    exact: true,
                }),
            ).toBeVisible();

            if (item.sectionHeading) {
                await expect(
                    page.getByRole('heading', {
                        level: 2,
                        name: item.sectionHeading,
                        exact: true,
                    }),
                ).toBeVisible();
            }
        }
    });

    test('keyboard users can skip directly to the main content', async ({
        page,
    }) => {
        await loginAs(page, 'admin@e2e.test');
        await page.goto('/dashboard');

        const skipLink = page.getByRole('link', {
            name: 'Skip to main content',
        });
        await page.keyboard.press('Tab');
        await expect(skipLink).toBeFocused();
        await skipLink.press('Enter');
        await expect(page.locator('#main-content')).toBeFocused();
    });

    test('filters have visible names that explain what they control', async ({
        page,
    }) => {
        await loginAs(page, 'admin@e2e.test');

        await page.goto('/inventory/products');
        await expect(
            page.getByRole('textbox', { name: 'Search' }),
        ).toBeVisible();
        await expect(
            page.getByRole('combobox', { name: 'Product type' }),
        ).toBeVisible();
        await expect(
            page.getByRole('combobox', { name: 'Record status' }),
        ).toBeVisible();

        await page.goto('/notifications');
        await expect(
            page.getByRole('textbox', { name: 'Search' }),
        ).toBeVisible();
        await expect(
            page.getByRole('combobox', { name: 'Read status' }),
        ).toBeVisible();
        await expect(
            page.getByRole('combobox', { name: 'Category' }),
        ).toBeVisible();
    });

    test('stock movement history uses inventory terminology', async ({
        page,
    }) => {
        await loginAs(page, 'admin@e2e.test');
        await page.goto(
            '/inventory/movements?search=phase-3-no-matching-movement',
        );

        await expect(
            page
                .getByText('No stock movements match the current filters.', {
                    exact: true,
                })
                .last(),
        ).toBeVisible();
    });

    for (const viewport of [
        { name: 'phone', width: 390, height: 844 },
        { name: 'tablet', width: 768, height: 1024 },
        { name: 'desktop', width: 1280, height: 900 },
    ]) {
        test(`core list pages avoid horizontal overflow on ${viewport.name}`, async ({
            page,
        }) => {
            test.setTimeout(120_000);
            await loginAs(page, 'admin@e2e.test');
            await page.setViewportSize(viewport);

            for (const path of [
                '/inventory/products',
                '/inventory/bookings',
                '/inventory/requisitions',
                '/inventory/purchase-orders',
                '/notifications',
            ]) {
                await page.goto(path);
                await expect(page.locator('#main-content')).toBeVisible();

                const hasHorizontalOverflow = await page.evaluate(
                    () =>
                        document.documentElement.scrollWidth >
                        document.documentElement.clientWidth,
                );

                expect(
                    hasHorizontalOverflow,
                    `${path} should fit within ${viewport.width}px`,
                ).toBeFalsy();
            }
        });
    }

    test('primary mobile controls meet the minimum touch-target height', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await loginAs(page, 'admin@e2e.test');
        await page.goto('/inventory/products');

        const target = page.getByRole('link', { name: 'New product' });
        const box = await target.boundingBox();

        expect(box).not.toBeNull();
        expect(box!.height).toBeGreaterThanOrEqual(44);
    });
});
