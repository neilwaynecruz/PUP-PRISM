import type { Page } from '@playwright/test';
import { loginAs } from './fixtures/auth';
import { expect, test } from './fixtures/test';

const assetTag = 'AST-E2E-0003';
const locationCode = 'P4-E2E';
const locationName = 'Phase 4 Validation Room';

function monitorBrowserErrors(page: Page): string[] {
    const errors: string[] = [];

    page.on('pageerror', (error: Error) => errors.push(error.message));
    page.on(
        'console',
        (message: { type: () => string; text: () => string }) => {
            const text = message.text();
            const isExpectedTestEnvironmentFailure =
                text.includes('net::ERR_NETWORK_ACCESS_DENIED') ||
                (text.includes('WebSocket connection to') &&
                    text.includes('localhost:8080'));

            if (
                message.type() === 'error' &&
                !isExpectedTestEnvironmentFailure
            ) {
                errors.push(text);
            }
        },
    );

    return errors;
}

async function loginAsAdmin(page: Page): Promise<void> {
    await loginAs(page, 'admin@e2e.test');
}

test.describe.serial('Phase 4 inventory and asset validation', () => {
    test('physical count can be recorded, submitted, approved, and completed', async ({
        page,
    }) => {
        test.setTimeout(120_000);
        const browserErrors = monitorBrowserErrors(page);
        page.on('dialog', (dialog) => dialog.accept());

        await loginAsAdmin(page);
        await page.goto('/inventory/counts');
        await expect(
            page.getByRole('heading', {
                level: 1,
                name: 'Physical inventory counts',
            }),
        ).toBeVisible();

        await page.getByLabel('Scope or area').fill('Phase 4 E2E stock room');
        await page.getByLabel('Assigned counter').selectOption({
            label: 'Admin User',
        });
        await page
            .locator('label')
            .filter({ hasText: 'E2E Test Consumable' })
            .getByRole('checkbox')
            .check();
        await page
            .getByRole('button', { name: 'Create Count Session' })
            .click();
        await page.waitForURL(/\/inventory\/counts\/\d+$/);

        const line = page
            .locator('article')
            .filter({ hasText: 'E2E Test Consumable' })
            .first();
        await expect(line).toBeVisible();
        const snapshot = (
            await line.locator('dd').first().textContent()
        )?.trim();

        expect(snapshot).toMatch(/^\d+$/);
        await line.getByLabel('Counted quantity').fill(snapshot!);
        await line.getByRole('button', { name: 'Save Count' }).click();
        await expect(line.locator('dd').nth(2)).toHaveText(snapshot!);

        await page.getByRole('button', { name: 'Submit for Review' }).click();
        await expect(
            page.getByLabel('Count session summary').getByText('Submitted'),
        ).toBeVisible();
        await page
            .getByRole('button', { name: 'Post Approved Differences' })
            .click();
        await expect(
            page.getByLabel('Count session summary').getByText('Approved'),
        ).toBeVisible();
        await page.getByRole('button', { name: 'Complete Session' }).click();
        await expect(
            page.getByLabel('Count session summary').getByText('Completed'),
        ).toBeVisible();

        expect(browserErrors).toEqual([]);
    });

    test('maintenance, transfer, lifecycle, and private documents work end to end', async ({
        page,
    }) => {
        test.setTimeout(240_000);
        const browserErrors = monitorBrowserErrors(page);
        page.on('dialog', (dialog) => dialog.accept());

        await loginAsAdmin(page);
        await page.goto('/admin/locations');
        await page.getByLabel('Location code').fill(locationCode);
        await page.getByLabel('Name').fill(locationName);
        await page.getByRole('button', { name: 'Create Location' }).click();
        await expect(
            page.getByText(locationName, { exact: true }),
        ).toBeVisible();

        await loginAs(page, 'custodian@e2e.test');
        await page.goto(`/inventory/assets?search=${assetTag}`);
        const assetLink = page.getByRole('link').filter({ hasText: assetTag });
        await expect(assetLink).toBeVisible();
        await assetLink.click();
        await page.waitForURL(/\/inventory\/assets\/\d+$/);
        const assetUrl = page.url();

        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        const tomorrowDate = tomorrow.toISOString().slice(0, 10);
        const issue = 'Phase 4 validation inspection';

        await page.getByLabel('Reported issue').fill(issue);
        await page
            .getByLabel('Scheduled service date')
            .first()
            .fill(tomorrowDate);
        await page
            .getByRole('button', { name: 'Create Maintenance Request' })
            .click();

        let maintenance = page
            .locator('article')
            .filter({ hasText: issue })
            .first();
        await expect(maintenance).toContainText('Reported');
        await maintenance.getByRole('button', { name: 'Schedule' }).click();
        maintenance = page
            .locator('article')
            .filter({ hasText: issue })
            .first();
        await expect(maintenance).toContainText('Scheduled');
        await maintenance.getByRole('button', { name: 'Start Work' }).click();
        maintenance = page
            .locator('article')
            .filter({ hasText: issue })
            .first();
        await expect(maintenance).toContainText('In Progress');
        await maintenance.getByText('Service details', { exact: true }).click();
        await maintenance
            .getByLabel('Findings')
            .fill('Inspection completed; no additional defect found.');
        await maintenance.getByLabel('Repair cost').fill('125.50');
        await maintenance
            .getByRole('button', { name: 'Mark Completed' })
            .click();
        maintenance = page
            .locator('article')
            .filter({ hasText: issue })
            .first();
        await expect(maintenance).toContainText('Completed');
        await expect(maintenance).toContainText(
            'Inspection completed; no additional defect found.',
        );

        const documentTitle = 'Phase 4 inspection document';
        await page.getByLabel('Document title').fill(documentTitle);
        await page.getByLabel('Category').selectOption('inspection');
        await page.getByLabel('File (up to 10 MB)').setInputFiles({
            name: 'phase-4-inspection.pdf',
            mimeType: 'application/pdf',
            buffer: Buffer.from('%PDF-1.4\n% Phase 4 validation\n%%EOF'),
        });
        await page.getByRole('button', { name: 'Upload Document' }).click();
        const documentCard = page
            .locator('article')
            .filter({ hasText: documentTitle })
            .first();
        await expect(documentCard).toBeVisible();
        const documentHref = await documentCard
            .getByRole('link', { name: 'Download' })
            .getAttribute('href');

        expect(documentHref).toBeTruthy();
        const downloadResponse = page.waitForResponse(
            (response) =>
                response.url().includes(documentHref!) &&
                response.status() === 200,
        );
        await documentCard.getByRole('link', { name: 'Download' }).click();
        expect((await downloadResponse).headers()['content-type']).toContain(
            'application/pdf',
        );

        await page.getByLabel('Destination').selectOption({
            label: `${locationCode} — ${locationName}`,
        });
        await page
            .getByPlaceholder('Why is this asset moving?')
            .fill('Move the asset to its validated operating room.');
        await page
            .getByRole('button', { name: 'Submit Transfer Request' })
            .click();
        let transfer = page
            .locator('article')
            .filter({ hasText: locationName })
            .first();
        await expect(transfer).toContainText('Pending');

        await loginAsAdmin(page);
        await page.goto(assetUrl);
        transfer = page
            .locator('article')
            .filter({ hasText: locationName })
            .first();
        await transfer.getByRole('button', { name: 'Approve' }).click();
        await expect(
            page.getByLabel('Asset summary').getByText(locationName),
        ).toBeVisible();

        await loginAs(page, 'custodian@e2e.test');
        await page.goto(assetUrl);
        await page
            .getByText('Propose a lifecycle or condition change', {
                exact: true,
            })
            .click();
        await page.getByLabel('Change type').selectOption('retirement');
        await page.getByLabel('Method').fill('Administrative retirement');
        await page.getByLabel('Supporting reference').fill('P4-E2E-RETIREMENT');
        await page
            .getByLabel('Reason')
            .last()
            .fill('The asset completed its supported service life.');
        await page.getByRole('button', { name: 'Submit for Approval' }).click();

        await loginAsAdmin(page);
        await page.goto(assetUrl);
        const retirement = page
            .locator('article')
            .filter({
                hasText: 'The asset completed its supported service life.',
            })
            .first();
        await retirement.getByRole('button', { name: 'Approve' }).click();
        await expect(
            page.getByLabel('Asset summary').getByText('Retired'),
        ).toBeVisible();

        await loginAs(page, 'custodian@e2e.test');
        await page.goto(assetUrl);
        await page
            .getByText('Propose a lifecycle or condition change', {
                exact: true,
            })
            .click();
        await expect(page.getByLabel('Change type')).toHaveValue('disposal');
        await page.getByLabel('Method').fill('Authorized disposal');
        await page.getByLabel('Supporting reference').fill('P4-E2E-DISPOSAL');
        await page
            .getByLabel('Reason')
            .last()
            .fill('Dispose of the retired asset under the recorded approval.');
        await page.getByRole('button', { name: 'Submit for Approval' }).click();

        await loginAsAdmin(page);
        await page.goto(assetUrl);
        const disposal = page
            .locator('article')
            .filter({
                hasText:
                    'Dispose of the retired asset under the recorded approval.',
            })
            .first();
        await disposal.getByRole('button', { name: 'Approve' }).click();
        await expect(
            page.getByLabel('Asset summary').getByText('Disposed'),
        ).toBeVisible();
        await expect(
            page.getByText(documentTitle, { exact: true }),
        ).toBeVisible();
        expect(browserErrors).toEqual([]);

        await loginAs(page, 'recipient@e2e.test');
        const forbiddenResponse = await page.goto(documentHref!);
        expect(forbiddenResponse?.status()).toBe(403);
        expect(browserErrors).toEqual([
            'Failed to load resource: the server responded with a status of 403 (Forbidden)',
        ]);
    });

    test('Phase 4 pages are responsive in light and dark themes', async ({
        page,
    }, testInfo) => {
        test.setTimeout(240_000);
        const browserErrors = monitorBrowserErrors(page);
        await loginAsAdmin(page);

        await page.goto(`/inventory/assets?search=${assetTag}`);
        const assetDetailPath = await page
            .locator('a')
            .filter({ hasText: assetTag })
            .first()
            .getAttribute('href');
        expect(assetDetailPath).toBeTruthy();

        const pages = [
            { name: 'counts', path: '/inventory/counts' },
            { name: 'assets', path: '/inventory/assets' },
            { name: 'asset-detail', path: assetDetailPath! },
            { name: 'locations', path: '/admin/locations' },
        ];
        const viewports = [
            { name: 'phone-320', width: 320, height: 720 },
            { name: 'phone-375', width: 375, height: 812 },
            { name: 'tablet-768', width: 768, height: 1024 },
            { name: 'laptop-1024', width: 1024, height: 768 },
            { name: 'desktop-1440', width: 1440, height: 900 },
        ];

        for (const viewport of viewports) {
            await page.setViewportSize(viewport);

            for (const target of pages) {
                await page.goto(target.path);
                await expect(
                    page.getByRole('heading', { level: 1 }),
                ).toHaveCount(1);
                const overflow = await page.evaluate(
                    () =>
                        document.documentElement.scrollWidth -
                        document.documentElement.clientWidth,
                );

                expect(
                    overflow,
                    `${target.path} overflows at ${viewport.width}px`,
                ).toBeLessThanOrEqual(1);
                await page.screenshot({
                    path: testInfo.outputPath(
                        `${target.name}-${viewport.name}-light.png`,
                    ),
                    fullPage: true,
                });
            }
        }

        await page.evaluate(() => localStorage.setItem('appearance', 'dark'));
        await page.setViewportSize({ width: 1440, height: 900 });

        for (const target of pages) {
            await page.goto(target.path);
            await expect(page.locator('html')).toHaveClass(/dark/);
            await page.screenshot({
                path: testInfo.outputPath(`${target.name}-desktop-dark.png`),
                fullPage: true,
            });
        }

        expect(browserErrors).toEqual([]);
    });
});
