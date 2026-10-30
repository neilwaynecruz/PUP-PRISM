import { loginAs } from './fixtures/auth';
import { expect, test } from './fixtures/test';
import { getLatestRequisitionId, getProductOnHandQtyBySku } from './helpers/db';

test.describe('Requisition Lifecycle', () => {
    test('Submit -> Approve -> Issue -> Verify stock decrement', async ({
        browserName,
        page,
    }) => {
        const pageErrors: string[] = [];
        const consoleErrors: string[] = [];

        page.on('pageerror', (error) => {
            pageErrors.push(error.message);
        });

        page.on('console', (message) => {
            const text = message.text();
            const isExpectedTestEnvironmentFailure =
                text.includes('ERR_NETWORK_ACCESS_DENIED') ||
                (text.includes('localhost:8080') &&
                    (text.includes('WebSocket connection to') ||
                        text.includes(
                            'establish a connection to the server at ws',
                        ))) ||
                (browserName === 'firefox' &&
                    text.includes('downloadable font: download failed') &&
                    text.includes('https://fonts.gstatic.com/'));

            if (
                message.type() === 'error' &&
                !isExpectedTestEnvironmentFailure
            ) {
                consoleErrors.push(text);
            }
        });

        await loginAs(page, 'requester@e2e.test');

        const initialStock = getProductOnHandQtyBySku('CON-E2E-001');

        await page.goto('/inventory/requisitions');
        await expect(page.getByTestId('requisitions-index-page')).toBeVisible();
        expect(pageErrors).toEqual([]);
        expect(consoleErrors).toEqual([]);

        await page
            .getByRole('button', { name: 'Browse Catalog', exact: true })
            .click();
        const productDialog = page.getByRole('dialog', {
            name: 'Browse products for requisition',
        });
        await productDialog
            .getByRole('searchbox', { name: 'Search the product catalog' })
            .fill('CON-E2E-001');
        await expect(
            productDialog.getByText('Multipurpose Copy Paper, A4, 80 gsm'),
        ).toBeVisible();
        await expect(productDialog.getByLabel('Quantity')).toHaveCount(1);
        await productDialog.getByLabel('Quantity').fill('10');
        await productDialog.getByRole('button', { name: 'Add' }).click();
        await productDialog.getByRole('button', { name: 'Done' }).click();
        await expect(page.getByTestId('requisition-sku-input')).toHaveValue(
            'CON-E2E-001',
        );
        await expect(
            page.getByTestId('submit-requisition-button'),
        ).toBeEnabled();

        const [submissionResponse] = await Promise.all([
            page.waitForResponse(
                (response) =>
                    new URL(response.url()).pathname ===
                        '/inventory/requisitions' &&
                    response.request().method() === 'POST',
            ),
            page.getByTestId('submit-requisition-button').click(),
        ]);
        expect(submissionResponse.status()).toBeLessThan(400);

        const requisitionId = getLatestRequisitionId('requester@e2e.test');
        expect(requisitionId).toBeGreaterThan(0);

        await page.goto(`/inventory/requisitions/${requisitionId}`);
        await expect(page.getByTestId('requisition-status-value')).toHaveText(
            'Submitted',
        );

        await loginAs(page, 'supply@e2e.test');

        await page.goto(`/inventory/requisitions/${requisitionId}`);
        await expect(page.getByTestId('requisition-show-page')).toBeVisible();

        await page.getByLabel('Approval notes (optional)').press('Enter');
        const approvalDialog = page.getByRole('dialog', {
            name: 'Approve this requisition?',
        });
        await expect(approvalDialog).toBeVisible();
        await expect(page.getByTestId('requisition-status-value')).toHaveText(
            'Submitted',
        );
        await approvalDialog
            .getByRole('button', { name: 'Approve Request' })
            .click();
        await expect(page.getByTestId('requisition-status-value')).toHaveText(
            'Approved',
        );

        await page.getByTestId('issue-requisition-button').click();
        await expect(page.getByTestId('requisition-status-value')).toHaveText(
            'Issued',
        );
        await expect(
            page.getByTestId('requisition-line-issued-CON-E2E-001'),
        ).toHaveText('10');

        expect(getProductOnHandQtyBySku('CON-E2E-001')).toBe(initialStock - 10);
    });
});
