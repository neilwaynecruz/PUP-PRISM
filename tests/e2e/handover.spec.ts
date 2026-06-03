import { loginAs } from './fixtures/auth';
import { expect, test } from './fixtures/test';
import { getUserIdByEmail, preparePendingHandoverVerification } from './helpers/db';

test.describe('Asset Handover Flow', () => {
    const handoverToken = 'e2e-handover-test-token-2026';
    let handoverId: number;
    let recipientUserId: number;

    test.beforeEach(() => {
        recipientUserId = getUserIdByEmail('recipient@e2e.test');
    });

    test('Initiate, verify, and download receipt', async ({ page }) => {
        // Login as custodian to initiate handover
        await loginAs(page, 'custodian@e2e.test');

        await page.goto('/inventory/handover');
        await expect(page.getByTestId('handover-initiate-page')).toBeVisible();

        // Fill in the handover form
        await page.getByTestId('handover-asset-tag-input').fill('AST-E2E-0001');
        await page.getByTestId('handover-recipient-search-input').fill('recipient@e2e.test');
        await expect(page).toHaveURL(/recipient_search=recipient%40e2e\.test$/);
        await page.getByTestId('handover-recipient-select').selectOption(String(recipientUserId));
        await expect(page.getByTestId('handover-recipient-select')).toHaveValue(String(recipientUserId));

        // Submit the handover form
        await Promise.all([
            page.waitForResponse(
                (response) =>
                    response.url().endsWith('/inventory/handover')
                    && response.request().method() === 'POST'
                    && response.status() >= 300
                    && response.status() < 400,
            ),
            page.getByTestId('send-verification-button').click(),
        ]);

        // Prepare the handover with a known token
        handoverId = preparePendingHandoverVerification(
            'AST-E2E-0001',
            'recipient@e2e.test',
            handoverToken,
        );

        expect(handoverId).toBeGreaterThan(0);

        // Login as recipient and verify
        await loginAs(page, 'recipient@e2e.test');

        page.on('console', (msg) => {
            if (msg.type() === 'error') {
                console.log('Console error:', msg.text());
            }
        });
        page.on('pageerror', (error) => {
            console.log('Page error:', error.message);
        });

        await page.goto(`/inventory/handover/verify/${handoverId}?token=${handoverToken}`);
        await expect(page.getByTestId('handover-verify-page')).toBeVisible();

        // Draw a signature on the signature pad
        const signatureCanvas = page.getByTestId('handover-signature-pad').locator('canvas');
        await expect(signatureCanvas).toBeVisible();

        const box = await signatureCanvas.boundingBox();
        expect(box).not.toBeNull();

        if (box) {
            // Draw a deliberate signature
            const steps = 20;
            for (let i = 0; i <= steps; i++) {
                const x = box.x + 20 + (i / steps) * 120;
                const y = box.y + 40 + Math.sin((i / steps) * Math.PI * 2) * 20;
                await page.mouse.move(x, y);
                if (i === 0) {
                    await page.mouse.down();
                }
            }
            await page.mouse.up();
            await page.waitForTimeout(500);
        }

        // Submit the verification
        await page.getByTestId('verify-handover-button').click();
        await page.waitForLoadState('networkidle');

        // Now test the receipt download - navigate to verify page WITH the token
        await page.goto(`/inventory/handover/verify/${handoverId}?token=${handoverToken}`);
        await page.waitForLoadState('networkidle');
        await expect(page.getByTestId('download-receipt-button')).toBeVisible({ timeout: 10000 });

        // Download receipt
        const receiptResponse = page.waitForResponse(
            (response) =>
                response.url().includes(`/inventory/handover/receipt/${handoverId}`)
                && response.status() === 200,
        );

        await page.getByTestId('download-receipt-button').click();

        const response = await receiptResponse;
        expect(response.headers()['content-type']).toContain('application/pdf');
    });
});
