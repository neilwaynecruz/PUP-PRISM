import type { Page } from '@playwright/test';
import { loginAs } from './fixtures/auth';
import { expect, test } from './fixtures/test';

type ModuleLink = {
    name: string;
    href: string;
    region: 'main' | 'footer';
    testId?: string;
    url?: RegExp;
};

const adminModules: ModuleLink[] = [
    { name: 'Dashboard', href: '/dashboard', region: 'main', url: /\/dashboard$/, testId: 'dashboard-page' },
    { name: 'Products', href: '/inventory/products', region: 'main', url: /\/inventory\/products$/, testId: 'products-index-page' },
    { name: 'Handover', href: '/inventory/handover', region: 'main', url: /\/inventory\/handover$/, testId: 'handover-initiate-page' },
    { name: 'Bookings', href: '/inventory/bookings', region: 'main', url: /\/inventory\/bookings$/, testId: 'bookings-page' },
    { name: 'Requisitions', href: '/inventory/requisitions', region: 'main', url: /\/inventory\/requisitions$/, testId: 'requisitions-index-page' },
    { name: 'Receiving', href: '/inventory/receiving', region: 'main', url: /\/inventory\/receiving$/, testId: 'receiving-page' },
    { name: 'Stock movements', href: '/inventory/movements', region: 'main', url: /\/inventory\/movements$/, testId: 'movements-index-page' },
    { name: 'Audit logs', href: '/inventory/audit-logs', region: 'main', url: /\/inventory\/audit-logs$/, testId: 'audit-logs-page' },
    { name: 'Settings', href: '/settings/profile', region: 'footer', url: /\/settings\/profile$/, testId: 'profile-settings-page' },
];

function moduleLink(page: Page, module: ModuleLink) {
    const region =
        module.region === 'main'
            ? page.locator('[data-slot="sidebar-content"]')
            : page.locator('[data-slot="sidebar-footer"]');

    return region.getByRole('link', { name: module.name });
}

test.describe('Inventory Navigation', () => {
    test('admin sees every allowed module link', async ({ page }) => {
        await loginAs(page, 'admin@e2e.test');

        for (const module of adminModules) {
            await expect(moduleLink(page, module)).toHaveCount(1);
        }
    });

    test('supply head sees only allowed modules and module content matches the current URL', async ({ page }) => {
        await loginAs(page, 'supply@e2e.test');

        await expect(
            moduleLink(page, adminModules[7]),
        ).toHaveCount(0);
        await expect(
            moduleLink(page, adminModules[6]),
        ).toHaveCount(0);
        await expect(
            moduleLink(page, adminModules[2]),
        ).toHaveCount(0);

        await Promise.all([
            page.waitForURL(/\/inventory\/products$/),
            moduleLink(page, adminModules[1]).click(),
        ]);
        await expect(page.getByTestId('products-index-page')).toBeVisible();

        await Promise.all([
            page.waitForURL(/\/inventory\/bookings$/),
            moduleLink(page, adminModules[3]).click(),
        ]);
        await expect(page.getByTestId('bookings-page')).toBeVisible();

        await Promise.all([
            page.waitForURL(/\/inventory\/requisitions$/),
            moduleLink(page, adminModules[4]).click(),
        ]);
        await expect(page.getByTestId('requisitions-index-page')).toBeVisible();

        await Promise.all([
            page.waitForURL(/\/inventory\/receiving$/),
            moduleLink(page, adminModules[5]).click(),
        ]);
        await expect(page.getByTestId('receiving-page')).toBeVisible();
    });

    test('property custodian only sees modules they are allowed to access', async ({ page }) => {
        await loginAs(page, 'custodian@e2e.test');

        await expect(moduleLink(page, adminModules[5])).toHaveCount(0);
        await expect(moduleLink(page, adminModules[6])).toHaveCount(0);
        await expect(moduleLink(page, adminModules[7])).toHaveCount(0);

        for (const module of [
            adminModules[0],
            adminModules[1],
            adminModules[2],
            adminModules[3],
            adminModules[4],
            adminModules[8],
        ]) {
            await expect(moduleLink(page, module)).toHaveCount(1);
        }
    });

    test('admin can switch rapidly across modules without stale content or client errors', async ({ page }) => {
        const pageErrors: string[] = [];
        const consoleErrors: string[] = [];

        page.on('pageerror', (error) => {
            pageErrors.push(error.message);
        });

        page.on('console', (message) => {
            const text = message.text();

            if (
                message.type() !== 'error' ||
                text.includes('fonts.googleapis.com') ||
                text.includes('ERR_NETWORK_ACCESS_DENIED')
            ) {
                return;
            }

            consoleErrors.push(text);
        });

        await loginAs(page, 'admin@e2e.test');

        for (let round = 0; round < 2; round++) {
            for (const module of adminModules) {
                await Promise.all([
                    page.waitForURL(module.url!),
                    moduleLink(page, module).click(),
                ]);
                await expect(page.getByTestId(module.testId!)).toBeVisible();
            }
        }

        expect(pageErrors).toEqual([]);
        expect(consoleErrors).toEqual([]);
    });
});
