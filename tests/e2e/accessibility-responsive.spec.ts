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

    test('dashboard date range prevents an invalid manual selection', async ({
        page,
    }) => {
        await loginAs(page, 'admin@e2e.test');
        await page.goto('/dashboard');

        const startDate = page.getByLabel('From', { exact: true });
        const endDate = page.getByLabel('To', { exact: true });
        const applyButton = page.getByRole('button', {
            name: 'Apply date range',
        });

        await startDate.fill('2026-10-30');
        await endDate.fill('2026-10-01');

        await expect(
            page.getByRole('alert').filter({
                hasText: 'The end date must be on or after the start date.',
            }),
        ).toBeVisible();
        await expect(startDate).toHaveAttribute('aria-invalid', 'true');
        await expect(endDate).toHaveAttribute('aria-invalid', 'true');
        await expect(applyButton).toBeDisabled();

        await endDate.fill('2026-10-30');
        await expect(applyButton).toBeEnabled();
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
                '/admin/users',
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

    test('users use readable record cards on phones and the full table on larger screens', async ({
        page,
    }) => {
        await loginAs(page, 'admin@e2e.test');

        await page.setViewportSize({ width: 320, height: 720 });
        await page.goto('/admin/users');
        await expect(page.getByTestId('users-mobile-list')).toBeVisible();
        await expect(page.locator('table')).toBeHidden();

        const mobileOverflow = await page.evaluate(
            () =>
                document.documentElement.scrollWidth -
                document.documentElement.clientWidth,
        );
        expect(mobileOverflow).toBeLessThanOrEqual(1);

        await page.setViewportSize({ width: 1024, height: 768 });
        await expect(page.getByTestId('users-mobile-list')).toBeHidden();
        await expect(page.locator('table')).toBeVisible();
    });

    test('mobile navigation drawer has an explicit close control and closes after navigation', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await loginAs(page, 'admin@e2e.test');
        await page.goto('/dashboard');

        const trigger = page.locator('[data-slot="sidebar-trigger"]');
        await expect(trigger).toHaveAttribute('aria-expanded', 'false');
        await trigger.click();
        await expect(trigger).toHaveAttribute('aria-expanded', 'true');

        const mobileDrawer = page.locator('[data-mobile="true"]');
        await expect(mobileDrawer).toBeVisible();
        await expect
            .poll(() =>
                page.evaluate(() => getComputedStyle(document.body).overflow),
            )
            .toBe('hidden');
        await expect
            .poll(() =>
                page.evaluate(() => {
                    const drawer = document.querySelector(
                        '[data-mobile="true"]',
                    );

                    return drawer?.contains(document.activeElement) ?? false;
                }),
            )
            .toBe(true);
        await page.keyboard.press('Shift+Tab');
        await expect
            .poll(() =>
                page.evaluate(() => {
                    const drawer = document.querySelector(
                        '[data-mobile="true"]',
                    );

                    return drawer?.contains(document.activeElement) ?? false;
                }),
            )
            .toBe(true);

        const closeButton = page.getByRole('button', { name: 'Close' });
        await expect(closeButton).toBeVisible();
        const closeButtonBox = await closeButton.boundingBox();
        expect(closeButtonBox).not.toBeNull();
        expect(closeButtonBox!.width).toBeGreaterThanOrEqual(44);
        expect(closeButtonBox!.height).toBeGreaterThanOrEqual(44);

        await closeButton.click();
        await expect(closeButton).toBeHidden();
        await expect(trigger).toBeFocused();

        await trigger.click();
        const overlay = page.locator('[data-slot="sheet-overlay"]');
        const overlayBox = await overlay.boundingBox();
        expect(overlayBox).not.toBeNull();
        await page.mouse.click(
            overlayBox!.x + overlayBox!.width - 4,
            overlayBox!.y + overlayBox!.height / 2,
        );
        await expect(mobileDrawer).toBeHidden();
        await expect(trigger).toBeFocused();

        await trigger.click();
        await page
            .locator('[data-slot="sidebar-content"]')
            .getByRole('link', { name: 'Dashboard' })
            .click();
        await expect(closeButton).toBeHidden();

        await trigger.click();
        await page
            .locator('[data-slot="sidebar-content"]')
            .getByRole('link', { name: 'Products' })
            .click();
        await expect(page).toHaveURL(/\/inventory\/products$/);
        await expect(closeButton).toBeHidden();
        await trigger.click();
        await expect(
            page
                .locator('[data-slot="sidebar-content"]')
                .getByRole('link', { name: 'Products' }),
        ).toHaveAttribute('data-active', 'true');
        await page.keyboard.press('Escape');
    });

    test('navigation toggle exposes state, restores focus, and collapses the desktop sidebar', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await loginAs(page, 'admin@e2e.test');
        await page.goto('/dashboard');

        const mobileTrigger = page.locator('[data-slot="sidebar-trigger"]');
        await mobileTrigger.click();
        await expect(mobileTrigger).toHaveAttribute('aria-expanded', 'true');
        await expect(page.locator('[data-mobile="true"]')).toContainText(
            'PUP PRISM',
        );

        await page.keyboard.press('Escape');
        await expect(mobileTrigger).toHaveAttribute('aria-expanded', 'false');
        await expect(mobileTrigger).toBeFocused();

        await mobileTrigger.click();
        await expect(mobileTrigger).toHaveAttribute('aria-expanded', 'true');
        await page.setViewportSize({ width: 1024, height: 768 });
        await expect(page.locator('[data-mobile="true"]')).toBeHidden();
        await page.setViewportSize({ width: 390, height: 844 });
        await expect(mobileTrigger).toHaveAttribute('aria-expanded', 'false');

        await page.setViewportSize({ width: 1280, height: 800 });
        const desktopSidebar = page.locator(
            '[data-slot="sidebar"][data-state]',
        );
        const desktopTrigger = page.locator('[data-slot="sidebar-trigger"]');

        await expect(desktopSidebar).toHaveAttribute('data-state', 'expanded');
        await desktopTrigger.click();
        await expect(desktopSidebar).toHaveAttribute('data-state', 'collapsed');
        await desktopTrigger.click();
        await expect(desktopSidebar).toHaveAttribute('data-state', 'expanded');
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
                await expect(
                    page.getByRole('textbox', { name: 'Search locations' }),
                ).toBeVisible();
                await expect(
                    page.getByRole('combobox', {
                        name: 'Filter locations by status',
                    }),
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

    test('suppliers page renders mobile record cards without horizontal overflow', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 360, height: 800 });
        await loginAs(page, 'admin@e2e.test');
        await page.goto('/inventory/suppliers');

        await expect(page.getByTestId('suppliers-index-page')).toBeVisible();
        await expect(
            page.getByRole('textbox', { name: 'Search suppliers' }),
        ).toBeVisible();
        await expect(page.locator('table')).toBeHidden();

        const overflow = await page.evaluate(
            () =>
                document.documentElement.scrollWidth -
                document.documentElement.clientWidth,
        );
        expect(overflow).toBeLessThanOrEqual(1);
    });
});
