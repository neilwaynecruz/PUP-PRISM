import { loginAs } from './fixtures/auth';
import { expect, test } from './fixtures/test';
import {
    getUserIdByEmail,
    preparePendingHandoverVerification,
} from './helpers/db';

test.describe('Asset Handover Flow', () => {
    const handoverToken = 'e2e-handover-test-token-2026';
    let handoverId: number;
    let recipientUserId: number;

    test.beforeEach(() => {
        recipientUserId = getUserIdByEmail('recipient@e2e.test');
    });

    test('Initiate, verify, and download receipt', async ({
        page,
    }, testInfo) => {
        const clientErrors: string[] = [];
        page.on('pageerror', (error) => clientErrors.push(error.message));
        // Login as custodian to initiate handover
        await loginAs(page, 'custodian@e2e.test');

        await page.goto('/inventory/handover');
        await expect(page.getByTestId('handover-initiate-page')).toBeVisible();

        // Fill in the handover form
        await page.getByTestId('handover-asset-tag-input').fill('AST-E2E-0001');
        await page.getByTestId('handover-asset-tag-input').press('Enter');
        await expect(page.getByTestId('handover-selected-asset')).toContainText(
            'AST-E2E-0001',
        );
        await page
            .getByTestId('handover-recipient-search-input')
            .fill('recipient@e2e.test');
        await expect(page).toHaveURL(/recipient_search=recipient%40e2e\.test/);
        await page
            .getByTestId('handover-recipient-options')
            .locator(`[data-user-id="${recipientUserId}"]`)
            .click();
        await expect(
            page.getByTestId('handover-selected-recipient'),
        ).toContainText('recipient@e2e.test');

        // Submit the handover form
        await Promise.all([
            page.waitForResponse(
                (response) =>
                    response.url().endsWith('/inventory/handover') &&
                    response.request().method() === 'POST' &&
                    response.status() >= 300 &&
                    response.status() < 400,
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

        // A sender cannot accept; switching accounts preserves the private link.
        await page.goto(
            `/inventory/handover/verify/${handoverId}?token=${handoverToken}`,
        );
        await expect(page.getByTestId('handover-recovery-page')).toBeVisible();
        await page.getByRole('button', { name: 'Switch account' }).click();
        await expect(page.getByTestId('login-page')).toBeVisible();
        await page.getByTestId('login-email-input').fill('recipient@e2e.test');
        await page.getByTestId('login-password-input').fill('password');
        await page.getByTestId('login-button').click();
        await expect(page.getByTestId('handover-verify-page')).toBeVisible();

        // The recipient can also start a fresh review without finding an email.
        await page.goto('/inventory/handover/mine');
        await expect(page.getByTestId('my-handovers-page')).toBeVisible();
        await expect(page.getByTestId('my-handovers-page')).toContainText(
            'AST-E2E-0001',
        );

        for (const width of [390, 834, 1280]) {
            await page.setViewportSize({ width, height: 900 });

            for (const theme of ['light', 'dark']) {
                await page.evaluate((appearance) => {
                    document.documentElement.classList.toggle(
                        'dark',
                        appearance === 'dark',
                    );
                }, theme);
                await expect(
                    page.getByRole('button', { name: 'Review and Accept' }),
                ).toBeVisible();
                expect(
                    await page.evaluate(
                        () =>
                            document.documentElement.scrollWidth <=
                            window.innerWidth,
                    ),
                ).toBe(true);
                await page.screenshot({
                    animations: 'disabled',
                    path: testInfo.outputPath(
                        `my-handovers-${width}-${theme}.png`,
                    ),
                    fullPage: true,
                });
            }
        }

        await page.getByRole('button', { name: 'Review and Accept' }).click();
        await expect(page.getByTestId('handover-verify-page')).toBeVisible();

        page.on('console', (msg) => {
            if (msg.type() === 'error') {
                console.log('Console error:', msg.text());
            }
        });
        page.on('pageerror', (error) => {
            console.log('Page error:', error.message);
        });

        await page.goto(
            `/inventory/handover/verify/${handoverId}?token=${handoverToken}`,
        );
        await expect(page.getByTestId('handover-verify-page')).toBeVisible();

        // Draw a signature on the signature pad
        const signatureCanvas = page
            .getByTestId('handover-signature-pad')
            .locator('canvas');
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
        }

        await expect(
            page.locator('input[name="signature_png"]'),
        ).not.toHaveValue('');

        // Submit the verification
        const [verificationResponse] = await Promise.all([
            page.waitForResponse(
                (response) =>
                    new URL(response.url()).pathname ===
                        `/inventory/handover/verify/${handoverId}` &&
                    response.request().method() === 'POST',
            ),
            page.getByTestId('verify-handover-button').click(),
        ]);
        expect(verificationResponse.status()).toBeLessThan(400);

        // Confirm that the verified state remains after a fresh server render.
        await page.goto(
            `/inventory/handover/verify/${handoverId}?token=${handoverToken}`,
        );
        await expect(page.getByTestId('download-receipt-button')).toBeVisible();

        // Download receipt
        const receiptDownload = page.waitForEvent('download');

        await page.getByTestId('download-receipt-button').click();

        const download = await receiptDownload;
        expect(download.suggestedFilename()).toBe(
            `internal-property-accountability-receipt-${handoverId}.pdf`,
        );
        expect(await download.failure()).toBeNull();
        const stream = await download.createReadStream();
        const chunks: Buffer[] = [];

        for await (const chunk of stream) {
            chunks.push(Buffer.from(chunk));
        }

        expect(Buffer.concat(chunks).subarray(0, 5).toString()).toBe('%PDF-');

        const response = await page.request.head(
            `/inventory/handover/receipt/${handoverId}`,
        );
        expect(response.status()).toBe(200);
        expect(response.headers()['content-type']).toContain('application/pdf');
        expect(clientErrors).toEqual([]);
    });
});
