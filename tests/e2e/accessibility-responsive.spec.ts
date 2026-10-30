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
        heading: 'My settings',
        sectionHeading: 'Profile information',
    },
];

const masterDataPages = [
    {
        path: '/admin/departments',
        heading: 'Departments',
        searchLabel: 'Search departments',
    },
    {
        path: '/admin/positions',
        heading: 'Positions',
        searchLabel: 'Search positions',
    },
    {
        path: '/admin/categories',
        heading: 'Categories',
        searchLabel: 'Search categories',
    },
    {
        path: '/admin/origins',
        heading: 'Origins',
        searchLabel: 'Search origins',
    },
] as const;

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
        { name: 'phone-320', width: 320, height: 720 },
        { name: 'phone-360', width: 360, height: 800 },
        { name: 'phone-375', width: 375, height: 812 },
        { name: 'phone-390', width: 390, height: 844 },
        { name: 'phone-430', width: 430, height: 932 },
        { name: 'phone-landscape', width: 844, height: 390 },
        { name: 'tablet-768', width: 768, height: 1024 },
        { name: 'tablet-834', width: 834, height: 1112 },
        { name: 'tablet-landscape', width: 1024, height: 768 },
        { name: 'laptop-1280', width: 1280, height: 800 },
        { name: 'desktop-1440', width: 1440, height: 900 },
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

    test('mobile navigation drawer has an explicit close control and closes after navigation', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await loginAs(page, 'admin@e2e.test');
        await page.goto('/dashboard');

        const trigger = page.getByRole('button', {
            name: 'Toggle navigation menu',
        });
        await trigger.click();

        const closeButton = page.getByRole('button', { name: 'Close' });
        await expect(closeButton).toBeVisible();
        const closeButtonBox = await closeButton.boundingBox();
        expect(closeButtonBox).not.toBeNull();
        expect(closeButtonBox!.width).toBeGreaterThanOrEqual(44);
        expect(closeButtonBox!.height).toBeGreaterThanOrEqual(44);

        await closeButton.click();
        await expect(closeButton).toBeHidden();

        await trigger.click();
        await page
            .locator('[data-slot="sidebar-content"]')
            .getByRole('link', { name: 'Products' })
            .click();
        await expect(page).toHaveURL(/\/inventory\/products$/);
        await expect(closeButton).toBeHidden();
    });

    test('five master-data pages remain usable in both themes across phone, tablet, and desktop', async ({
        page,
    }, testInfo) => {
        test.setTimeout(240_000);
        await loginAs(page, 'admin@e2e.test');

        for (const appearance of ['light', 'dark'] as const) {
            await page.evaluate((value) => {
                localStorage.setItem('appearance', value);
            }, appearance);

            for (const viewport of [
                { name: 'phone-320', width: 320, height: 720 },
                { name: 'tablet-768', width: 768, height: 1024 },
                { name: 'desktop-1440', width: 1440, height: 900 },
            ]) {
                await page.setViewportSize(viewport);

                for (const target of masterDataPages) {
                    await page.goto(target.path);
                    await expect(
                        page.getByRole('heading', {
                            level: 1,
                            name: target.heading,
                        }),
                    ).toBeVisible();
                    await expect(
                        page.getByRole('textbox', {
                            name: target.searchLabel,
                        }),
                    ).toBeVisible();
                    await expect(
                        page.getByRole('combobox', { name: 'Status' }),
                    ).toBeVisible();

                    if (viewport.width < 768) {
                        await expect(page.locator('table')).toBeHidden();
                    } else {
                        await expect(page.locator('table')).toBeVisible();
                    }

                    const overflow = await page.evaluate(
                        () =>
                            document.documentElement.scrollWidth -
                            document.documentElement.clientWidth,
                    );
                    expect(overflow).toBeLessThanOrEqual(1);

                    await page.screenshot({
                        path: testInfo.outputPath(
                            `master-${target.heading.toLowerCase()}-${viewport.name}-${appearance}.png`,
                        ),
                        fullPage: true,
                    });
                }

                await page.goto('/admin/locations');
                await expect(
                    page.getByRole('heading', {
                        level: 1,
                        name: 'Physical locations',
                    }),
                ).toBeVisible();
                await expect(
                    page.getByRole('textbox', { name: 'Location code' }),
                ).toBeVisible();
                await expect(
                    page.getByRole('textbox', { name: 'Name', exact: true }),
                ).toBeVisible();

                const locationsOverflow = await page.evaluate(
                    () =>
                        document.documentElement.scrollWidth -
                        document.documentElement.clientWidth,
                );
                expect(locationsOverflow).toBeLessThanOrEqual(1);

                await page.screenshot({
                    path: testInfo.outputPath(
                        `master-locations-${viewport.name}-${appearance}.png`,
                    ),
                    fullPage: true,
                });
            }
        }
    });
});
