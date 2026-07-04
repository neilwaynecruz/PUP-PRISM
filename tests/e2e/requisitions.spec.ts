import { loginAs } from './fixtures/auth';
import { expect, test } from './fixtures/test';
import { getLatestRequisitionId, getProductOnHandQtyBySku } from './helpers/db';

test.describe('Requisition Lifecycle', () => {
    test('Submit -> Approve -> Issue -> Verify stock decrement', async ({ page }) => {
        const pageErrors: string[] = [];
        const consoleErrors: string[] = [];

        page.on('pageerror', (error) => {
            pageErrors.push(error.message);
        });

        page.on('console', (message) => {
            if (message.type() === 'error') {
                consoleErrors.push(message.text());
            }
        });

        await loginAs(page, 'requester@e2e.test');

        const initialStock = getProductOnHandQtyBySku('CON-E2E-001');

        await page.goto('/inventory/requisitions');
        await expect(page.getByTestId('requisitions-index-page')).toBeVisible();
        expect(pageErrors).toEqual([]);
        expect(consoleErrors).toEqual([]);

        await page.getByTestId('requisition-sku-input').fill('CON-E2E-001');
        await page.getByTestId('requisition-qty-input').fill('10');

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

        expect(getProductOnHandQtyBySku('CON-E2E-001')).toBe(initialStock - 10);
    });
});
