import { execFileSync } from 'child_process';
import { resolve } from 'path';
import type { Page } from '@playwright/test';
import { loginAs } from './fixtures/auth';
import { expect, test } from './fixtures/test';
import { getUserIdByEmail, verifyEmail } from './helpers/db';

function trackRuntimeErrors(
    page: Page,
    browserName: string,
): { pageErrors: string[]; consoleErrors: string[] } {
    const pageErrors: string[] = [];
    const consoleErrors: string[] = [];

    page.on('pageerror', (error) => {
        pageErrors.push(error.message);
    });

    page.on('console', (message) => {
        const text = message.text();
        const isExpectedTestEnvironmentFailure =
            text.includes('ERR_NETWORK_ACCESS_DENIED') ||
            text.includes(
                'InvalidStateError: An attempt was made to use an object that is not, or is no longer, usable',
            ) ||
            (text.includes('localhost:8080') &&
                (text.includes('WebSocket connection to') ||
                    text.includes(
                        'establish a connection to the server at ws',
                    ))) ||
            (browserName === 'firefox' &&
                text.includes('downloadable font: download failed') &&
                (text.includes('https://fonts.gstatic.com/') ||
                    text.includes('status=2152398850')));

        if (message.type() === 'error' && !isExpectedTestEnvironmentFailure) {
            consoleErrors.push(text);
        }
    });

    return { pageErrors, consoleErrors };
}

function createPendingHandover(
    assetTagCode: string,
    fromUserId: number,
    toUserId: number,
    token: string,
): number {
    const output = execFileSync(
        process.env.PHP_BINARY || 'php',
        [
            resolve(process.cwd(), 'artisan'),
            'e2e:create-handover',
            assetTagCode,
            String(fromUserId),
            String(toUserId),
            token,
            '--no-interaction',
            '--env=e2e',
        ],
        {
            cwd: process.cwd(),
            encoding: 'utf8',
            timeout: 30000,
        },
    ).trim();

    return parseInt(output, 10);
}

test.describe('Priority 4 Stabilization & Return-to-Stock UI', () => {
    test('ProductSelectDialog In-stock checkbox binds via model-value and requisition tabs support arrow navigation', async ({
        browserName,
        page,
    }) => {
        const { pageErrors, consoleErrors } = trackRuntimeErrors(
            page,
            browserName,
        );

        await loginAs(page, 'requester@e2e.test');
        await page.goto('/inventory/requisitions');
        await expect(page.getByTestId('requisitions-index-page')).toBeVisible();

        const tablist = page.getByRole('tablist');
        const tabs = tablist.getByRole('tab');
        const tabCount = await tabs.count();
        expect(tabCount).toBeGreaterThanOrEqual(2);

        await tabs.nth(0).focus();
        await expect(tabs.nth(0)).toBeFocused();

        await page.keyboard.press('ArrowRight');
        await expect(tabs.nth(1)).toBeFocused();

        await page.keyboard.press('End');
        await expect(tabs.nth(tabCount - 1)).toBeFocused();

        await page.keyboard.press('ArrowRight');
        await expect(tabs.nth(0)).toBeFocused();

        await page.keyboard.press('ArrowLeft');
        await expect(tabs.nth(tabCount - 1)).toBeFocused();

        await page.keyboard.press('Home');
        await expect(tabs.nth(0)).toBeFocused();

        await page
            .getByRole('button', { name: 'Browse Catalog', exact: true })
            .click();

        const productDialog = page.getByRole('dialog', {
            name: 'Browse products for requisition',
        });
        await expect(productDialog).toBeVisible();

        const inStockCheckbox = productDialog.getByRole('checkbox', {
            name: 'In-stock only',
        });
        await expect(inStockCheckbox).toHaveAttribute(
            'data-state',
            'unchecked',
        );

        const [filteredLookupResponse] = await Promise.all([
            page.waitForResponse(
                (response) =>
                    new URL(response.url()).pathname ===
                        '/inventory/products/lookup' &&
                    new URL(response.url()).searchParams.get(
                        'in_stock_only',
                    ) === '1',
            ),
            inStockCheckbox.click(),
        ]);
        expect(filteredLookupResponse.status()).toBe(200);
        await expect(inStockCheckbox).toHaveAttribute('data-state', 'checked');
        await expect(inStockCheckbox).toHaveAttribute('aria-checked', 'true');
        await expect(
            productDialog.getByText('Multipurpose Copy Paper, A4, 80 gsm'),
        ).toBeVisible();

        await inStockCheckbox.click();
        await expect(inStockCheckbox).toHaveAttribute(
            'data-state',
            'unchecked',
        );
        await expect(inStockCheckbox).toHaveAttribute('aria-checked', 'false');

        expect(pageErrors).toEqual([]);
        expect(consoleErrors).toEqual([]);
    });

    test('Receiving toggles conditional product fields by SKU type and preserves batch state when removing rows', async ({
        browserName,
        page,
    }) => {
        const { pageErrors, consoleErrors } = trackRuntimeErrors(
            page,
            browserName,
        );

        await loginAs(page, 'supply@e2e.test');
        await page.goto('/inventory/receiving');
        await expect(page.getByTestId('receiving-page')).toBeVisible();

        const skuInput = page.getByTestId('receiving-sku-input');
        const tagCodesInput = page.getByTestId('receiving-tag-codes-input');
        const expiryLabel = page.getByText('Expiry date (consumables only)');

        await expect(tagCodesInput).toBeVisible();
        await expect(expiryLabel).toBeVisible();

        await skuInput.fill('CON-E2E-001');
        await expect(expiryLabel).toBeVisible();
        await expect(tagCodesInput).toHaveCount(0);

        await skuInput.fill('AST-E2E-001');
        await expect(tagCodesInput).toBeVisible();
        await expect(expiryLabel).toHaveCount(0);

        await page
            .getByRole('button', { name: 'Batch delivery', exact: true })
            .click();

        const batchSkuInputs = page.locator(
            'input[placeholder="CON-001"]:visible',
        );
        await expect(batchSkuInputs).toHaveCount(1);
        await batchSkuInputs.nth(0).fill('CON-E2E-001');

        await page
            .getByRole('button', { name: 'Add Delivery Line', exact: true })
            .click();
        await expect(batchSkuInputs).toHaveCount(2);
        await batchSkuInputs.nth(1).fill('AST-E2E-001');

        await page.getByRole('button', { name: 'Remove line' }).first().click();

        await expect(batchSkuInputs).toHaveCount(1);
        await expect(batchSkuInputs.nth(0)).toHaveValue('AST-E2E-001');
        await expect(
            page.getByText('Asset tag codes (for asset SKUs)'),
        ).toBeVisible();

        expect(pageErrors).toEqual([]);
        expect(consoleErrors).toEqual([]);
    });

    test('Verified handover checks out asset and custodian returns it to stock cleanly via Asset Show UI', async ({
        browserName,
        page,
    }) => {
        const { pageErrors, consoleErrors } = trackRuntimeErrors(
            page,
            browserName,
        );

        verifyEmail('recipient@e2e.test');
        const fromUserId = getUserIdByEmail('custodian@e2e.test');
        const toUserId = getUserIdByEmail('recipient@e2e.test');
        const verificationToken = 'E2E-STAB-RETURN-TOKEN';
        const handoverId = createPendingHandover(
            'AST-E2E-0001',
            fromUserId,
            toUserId,
            verificationToken,
        );
        expect(handoverId).toBeGreaterThan(0);

        await loginAs(page, 'recipient@e2e.test');
        await page.goto(
            `/inventory/handover/verify/${handoverId}?token=${verificationToken}`,
        );

        const signatureCanvas = page.locator(
            '[data-testid="handover-signature-pad"] canvas',
        );
        await expect(signatureCanvas).toBeVisible();
        const box = await signatureCanvas.boundingBox();

        if (!box) {
            throw new Error('Signature pad canvas bounding box missing.');
        }

        await page.mouse.move(box.x + 25, box.y + 30);
        await page.mouse.down();
        await page.mouse.move(box.x + 120, box.y + 70, { steps: 6 });
        await page.mouse.up();

        const [verifyResponse] = await Promise.all([
            page.waitForResponse(
                (response) =>
                    new URL(response.url()).pathname ===
                        `/inventory/handover/verify/${handoverId}` &&
                    response.request().method() === 'POST',
            ),
            page.getByTestId('verify-handover-button').click(),
        ]);
        expect(verifyResponse.status()).toBeLessThan(400);
        await expect(page).toHaveURL(/\/dashboard/);

        await loginAs(page, 'custodian@e2e.test');
        await page.goto('/inventory/assets');
        await page.locator('#asset-search').fill('AST-E2E-0001');

        const assetLink = page
            .locator('a[href*="/inventory/assets/"]')
            .filter({ hasText: 'AST-E2E-0001' })
            .first();
        await expect(assetLink).toBeVisible();
        await assetLink.click();

        await expect(page).toHaveURL(/\/inventory\/assets\/\d+/);
        const match = new URL(page.url()).pathname.match(
            /\/inventory\/assets\/(\d+)/,
        );
        const assetId = match?.[1];
        expect(assetId).toBeTruthy();

        const breadcrumb = page.getByLabel('breadcrumb');
        await expect(breadcrumb).toContainText('Assets');
        await expect(breadcrumb).toContainText('AST-E2E-0001');

        await expect(page.locator('main')).not.toContainText('NaN');
        await expect(
            page.getByText('Checked Out', { exact: true }),
        ).toBeVisible();

        const returnButton = page.getByTestId('return-asset-button');
        await expect(returnButton).toBeVisible();
        await returnButton.click();

        const returnLocationSelect = page.getByTestId('return-asset-location');
        await expect(returnLocationSelect).toBeVisible();

        if ((await returnLocationSelect.locator('option').count()) > 1) {
            await returnLocationSelect.selectOption({ index: 1 });
        }

        await page
            .getByTestId('return-asset-condition')
            .selectOption('serviceable');
        await page
            .getByTestId('return-asset-notes')
            .fill('Returned after verified field assignment.');

        const [returnResponse] = await Promise.all([
            page.waitForResponse(
                (response) =>
                    new URL(response.url()).pathname ===
                        `/inventory/assets/${assetId}/return` &&
                    response.request().method() === 'POST',
            ),
            page.getByTestId('confirm-return-asset-button').click(),
        ]);
        expect(returnResponse.status()).toBeLessThan(400);

        await expect(returnButton).toHaveCount(0);
        await expect(
            page.getByText('Available', { exact: true }),
        ).toBeVisible();

        expect(pageErrors).toEqual([]);
        expect(consoleErrors).toEqual([]);
    });
});
