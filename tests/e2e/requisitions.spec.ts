import { loginAs } from './fixtures/auth';
import { expect, test } from './fixtures/test';
import { getLatestRequisitionId } from './helpers/db';

test.describe('Requisition Lifecycle', () => {
    test('Submit -> Approve -> Issue -> Verify stock decrement', async ({ page }) => {
        await loginAs(page, 'requester@e2e.test');

        // Capture initial stock before requisition flow
        await page.goto('/inventory/products');
        await page.getByTestId('product-search-input').fill('CON-E2E-001');
        await page.waitForLoadState('networkidle');
        await page.getByTestId('product-row-CON-E2E-001').getByTestId('view-product-button').click();
        await page.waitForLoadState('networkidle');
        const initialStockText = await page.getByTestId('product-on-hand-value').textContent() ?? '';
        const initialMatch = initialStockText.match(/On hand: (\d+)/);
        expect(initialMatch).not.toBeNull();
        const initialStock = parseInt(initialMatch![1], 10);

        await page.goto('/inventory/requisitions');
        await expect(page.getByTestId('requisitions-index-page')).toBeVisible();

        await page.getByTestId('requisition-sku-input').fill('CON-E2E-001');
        await page.getByTestId('requisition-qty-input').fill('10');
        await page.getByTestId('requisition-notes-input').fill('E2E requisition request');

        await page.getByTestId('submit-requisition-button').click();
        await page.waitForLoadState('networkidle');

        const requisitionId = getLatestRequisitionId('requester@e2e.test');
        expect(requisitionId).toBeGreaterThan(0);

        await page.goto(`/inventory/requisitions/${requisitionId}`);
        await expect(page.getByTestId('requisition-status-value')).toHaveText('Submitted');

        await loginAs(page, 'supply@e2e.test');

        await page.goto(`/inventory/requisitions/${requisitionId}`);
        await expect(page.getByTestId('requisition-show-page')).toBeVisible();

        await page.getByTestId('approve-requisition-button').click();
        await expect(page.getByTestId('requisition-status-value')).toHaveText('Approved');

        await page.getByTestId('issue-requisition-button').click();
        await expect(page.getByTestId('requisition-status-value')).toHaveText('Issued');
        await expect(page.getByTestId('requisition-line-issued-CON-E2E-001')).toHaveText('10');

        await page.goto('/inventory/products');
        await page.getByTestId('product-search-input').fill('CON-E2E-001');
        await expect(page).toHaveURL(/search=CON-E2E-001$/);
        await Promise.all([
            page.waitForURL(/\/inventory\/products\/\d+$/),
            page.getByTestId('product-row-CON-E2E-001').getByTestId('view-product-button').click(),
        ]);
        await expect(page.getByTestId('product-on-hand-value')).toHaveText(new RegExp(`On hand: ${initialStock - 10}`));
    });
});
