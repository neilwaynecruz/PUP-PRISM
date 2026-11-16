import type { Page } from '@playwright/test';
import { loginAs } from './fixtures/auth';
import { expect, test } from './fixtures/test';

async function submitStockReceipt(page: Page): Promise<void> {
    const receiptSubmitted = page.waitForResponse(
        (response) =>
            new URL(response.url()).pathname === '/inventory/receiving' &&
            response.request().method() === 'POST' &&
            response.status() < 400,
    );
    await page.getByTestId('receive-stock-button').click();
    await receiptSubmitted;
    await expect(page.getByTestId('receiving-page')).toBeVisible();
    await expect(page.getByTestId('receive-stock-button')).toBeEnabled();
}

test.describe('Stock Receiving Flow', () => {
    test('Receive consumable stock', async ({ page }) => {
        await loginAs(page, 'admin@e2e.test');

        await page.goto('/inventory/receiving');
        await expect(page.getByTestId('receiving-page')).toBeVisible();

        await page.getByTestId('receiving-sku-input').fill('CON-E2E-001');
        await page.getByTestId('receiving-qty-input').fill('25');
        await page.getByTestId('receiving-reference-input').fill('E2E-DR-001');

        await submitStockReceipt(page);
        await expect(page.getByTestId('receiving-qty-input')).toHaveValue('');

        await page.goto('/inventory/products');
        await page.getByTestId('product-search-input').fill('CON-E2E-001');
        await expect(page).toHaveURL(/search=CON-E2E-001$/);
        await Promise.all([
            page.waitForURL(/\/inventory\/products\/\d+$/),
            page
                .getByTestId('product-row-CON-E2E-001')
                .getByTestId('view-product-button')
                .click(),
        ]);
        await expect(page.getByTestId('product-on-hand-value')).toHaveText(
            /(?:On hand:\s*)?125/,
        );
    });

    test('Receive asset stock', async ({ page }) => {
        await loginAs(page, 'admin@e2e.test');

        await page.goto('/inventory/receiving');
        await expect(page.getByTestId('receiving-page')).toBeVisible();

        await page.getByTestId('receiving-sku-input').fill('AST-E2E-001');
        await page
            .getByTestId('receiving-tag-codes-input')
            .fill('AST-E2E-RCV-001\nAST-E2E-RCV-002');

        await submitStockReceipt(page);
        await expect(page.getByTestId('receiving-tag-codes-input')).toHaveValue(
            '',
        );

        await page.goto('/inventory/products');
        await page.getByTestId('product-search-input').fill('AST-E2E-001');
        await expect(page).toHaveURL(/search=AST-E2E-001$/);
        await Promise.all([
            page.waitForURL(/\/inventory\/products\/\d+$/),
            page
                .getByTestId('product-row-AST-E2E-001')
                .getByTestId('view-product-button')
                .click(),
        ]);
        await expect(page.getByTestId('product-assets-count-value')).toHaveText(
            '4',
        );
        await expect(
            page.getByText(
                'Individual assets are tracked by property identifier and custodian assignment.',
            ),
        ).toBeVisible();
    });
});
