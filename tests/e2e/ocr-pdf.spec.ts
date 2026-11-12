import { expect, test } from '@playwright/test';
import type { Locator } from '@playwright/test';
import { loginAs } from './fixtures/auth';
import { resetDatabase } from './helpers/db';
import { scannedPdf, textPdf } from './helpers/pdf';

test.beforeEach(async ({ page }) => {
    resetDatabase();
    await page.addInitScript(() => {
        if ('serviceWorker' in navigator) {
            Object.defineProperty(navigator.serviceWorker, 'register', {
                configurable: true,
                value: () => new Promise(() => {}),
            });
        }
    });
    await loginAs(page, 'admin@e2e.test');
});

async function readPdf(
    dialog: Locator,
    text: string,
    buffer = textPdf([[text]]),
): Promise<void> {
    await dialog.locator('#asset-label-photo').setInputFiles({
        name: 'synthetic-local.pdf',
        mimeType: 'application/pdf',
        buffer,
    });
    await expect(
        dialog.getByRole('button', { name: text, exact: true }),
    ).toBeVisible();
    await expect(
        dialog.getByRole('img', { name: /Rendered PDF page/ }),
    ).toBeVisible();
}

test('Assets reads selected PDF pages locally, clears superseded text and requires explicit search', async ({
    page,
}, info) => {
    await page.goto('/inventory/assets');
    const requests: string[] = [];
    page.on('request', (request) => {
        if (/pdf\.worker|pdfOcr|\/pdf-|traineddata/.test(request.url())) {
            requests.push(request.url());
        }
    });
    await page
        .getByRole('button', { name: 'Read printed tag', exact: true })
        .click();
    const dialog = page.getByRole('dialog', { name: 'Read printed asset tag' });
    expect(requests).toEqual([]);
    const started = Date.now();
    await readPdf(
        dialog,
        'AST-E2E-0001',
        textPdf([['AST-E2E-0001'], ['AST-E2E-0002']]),
    );
    await info.attach('pdf-first-page-time', {
        body: JSON.stringify({ milliseconds: Date.now() - started }),
        contentType: 'application/json',
    });
    await expect(page).not.toHaveURL(/search=/);
    await dialog.locator('#ocr-pdf-page').fill('2');
    await expect(dialog.locator('pre')).toHaveCount(0);
    await expect(dialog.locator('#ocr-identifier')).toHaveValue('');
    await dialog
        .getByRole('button', { name: 'Read selected PDF page', exact: true })
        .click();
    await dialog
        .getByRole('button', { name: 'AST-E2E-0002', exact: true })
        .click();
    await expect(dialog.locator('pre')).not.toContainText('AST-E2E-0001');
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(dialog).toHaveCSS('opacity', '1');
    await page.screenshot({
        path: info.outputPath('assets-pdf-page-review.png'),
        animations: 'disabled',
    });
    await dialog
        .getByRole('button', { name: 'Search existing assets', exact: true })
        .click();
    await expect(page).toHaveURL(/search=AST-E2E-0002/);
    await expect(
        page.getByRole('link').filter({ hasText: 'AST-E2E-0002' }),
    ).toBeVisible();
    expect(requests.some((url) => /pdf\.worker/.test(url))).toBe(true);
    expect(
        requests.every(
            (url) => new URL(url).origin === new URL(page.url()).origin,
        ),
    ).toBe(true);
});

test('Receiving reads a scanned PDF serial without replacing the institutional tag', async ({
    page,
}) => {
    await page.goto('/inventory/receiving');
    await page.getByTestId('receiving-sku-input').fill('AST-E2E-001');
    await page.getByTestId('receiving-tag-codes-input').fill('PDF-RCV-TAG');
    await page
        .getByRole('button', { name: 'Read serial number', exact: true })
        .click();
    const jpeg = await page.evaluate(() => {
        const canvas = document.createElement('canvas');
        canvas.width = 1200;
        canvas.height = 400;
        const context = canvas.getContext('2d');

        if (!context) {
            throw new Error('Canvas unavailable');
        }

        context.fillStyle = 'white';
        context.fillRect(0, 0, 1200, 400);
        context.fillStyle = 'black';
        context.font = '60px Arial';
        context.fillText('SN-PDF-ABC', 50, 230);

        return canvas.toDataURL('image/jpeg', 0.95).split(',')[1];
    });
    const dialog = page.getByRole('dialog', {
        name: 'Read serial for PDF-RCV-TAG',
    });
    await readPdf(
        dialog,
        'SN-PDF-ABC',
        scannedPdf(Buffer.from(jpeg, 'base64'), 1200, 400),
    );
    await dialog
        .getByRole('button', { name: 'SN-PDF-ABC', exact: true })
        .click();
    await dialog
        .getByRole('button', { name: 'Apply reviewed serial', exact: true })
        .click();
    await expect(
        page.getByLabel('Manufacturer serial for PDF-RCV-TAG', { exact: true }),
    ).toHaveValue('SN-PDF-ABC');
    await expect(page.getByTestId('receiving-tag-codes-input')).toHaveValue(
        'PDF-RCV-TAG',
    );
});

for (const workflow of ['maintenance', 'transfer'] as const) {
    test(`${workflow} lookup accepts a PDF and still requires record selection`, async ({
        page,
    }) => {
        await page.goto('/inventory/assets');
        await page
            .getByRole('button', {
                name: `Find ${workflow} asset from label`,
                exact: true,
            })
            .click();
        const dialog = page.getByRole('dialog', {
            name: 'Read printed asset tag',
        });
        await readPdf(dialog, 'AST-E2E-0001');
        await dialog
            .getByRole('button', { name: 'AST-E2E-0001', exact: true })
            .click();
        await dialog
            .getByRole('button', {
                name: 'Search existing assets',
                exact: true,
            })
            .click();
        const candidate = page
            .getByRole('link')
            .filter({ hasText: 'AST-E2E-0001' });
        await expect(candidate).toHaveAttribute(
            'href',
            new RegExp(
                `#${workflow === 'maintenance' ? 'maintenance-title' : 'transfer-title'}$`,
            ),
        );
    });
}

test('Handover PDF lookup fills candidate search without initiating handover', async ({
    page,
}) => {
    await page.goto('/inventory/handover');
    await page
        .getByRole('button', { name: 'Find asset from label', exact: true })
        .click();
    const dialog = page.getByRole('dialog', {
        name: 'Find handover asset from label',
    });
    await readPdf(dialog, 'AST-E2E-0001');
    await dialog
        .getByRole('button', { name: 'AST-E2E-0001', exact: true })
        .click();
    await dialog
        .getByRole('button', { name: 'Search existing assets', exact: true })
        .click();
    await expect(page.getByTestId('handover-asset-options')).toContainText(
        'AST-E2E-0001',
    );
    await expect(page.getByTestId('handover-selected-asset')).toHaveCount(0);
    await expect(page.getByTestId('send-verification-button')).toBeDisabled();
});

test('Physical Counts PDF lookup stays inside session scope and never supplies a count', async ({
    page,
}) => {
    await page.goto('/inventory/counts');
    await page.locator('#count-scope').fill('PDF count fixture');
    const assignee = await page
        .locator('#count-assigned option')
        .filter({ hasText: 'Adrian' })
        .getAttribute('value');
    expect(assignee).toBeTruthy();
    await page.locator('#count-assigned').selectOption(assignee!);
    await page.getByRole('checkbox').filter({ visible: true }).first().check();
    await page
        .getByRole('button', { name: 'Create Count Session', exact: true })
        .click();
    await expect(page).toHaveURL(/\/inventory\/counts\/\d+$/);
    await page
        .getByRole('button', {
            name: 'Find count item from label',
            exact: true,
        })
        .click();
    const dialog = page.getByRole('dialog', {
        name: 'Find count item from label',
        exact: true,
    });
    await readPdf(dialog, 'OUTSIDE-SESSION');
    await dialog
        .getByRole('button', { name: 'OUTSIDE-SESSION', exact: true })
        .click();
    await dialog
        .getByRole('button', { name: 'Find session item', exact: true })
        .click();
    await expect(page.getByRole('status')).toContainText(
        'No matching item in this session',
    );
    await page
        .getByRole('button', { name: 'Show all items', exact: true })
        .click();
    await expect(page.locator('input[type="number"]').first()).toHaveValue('');
});

test('Procurement renders PDF text for reviewed source fields, not financial extraction', async ({
    page,
}, info) => {
    await page.goto('/inventory/purchase-orders/create');
    await page
        .getByRole('button', { name: 'Read procurement document', exact: true })
        .click();
    const dialog = page.getByRole('dialog', {
        name: 'Review procurement document',
    });
    await dialog.locator('#asset-label-photo').setInputFiles({
        name: 'invoice-fixture.pdf',
        mimeType: 'application/pdf',
        buffer: textPdf([
            [
                'Invoice No: INV-PRISM-ABC',
                'Date: 2026-11-12',
                'SKU: CON-E2E-001',
            ],
        ]),
    });
    await expect(dialog.locator('pre')).toContainText('INV-PRISM-ABC');
    await expect(dialog.locator('#ocr-reference')).toHaveValue('INV-PRISM-ABC');
    await expect(dialog.locator('#ocr-document-date')).toHaveValue(
        '2026-11-12',
    );
    await page.setViewportSize({ width: 834, height: 1112 });
    await page.evaluate(() => document.documentElement.classList.add('dark'));
    await expect(dialog).toHaveCSS('opacity', '1');
    await page.screenshot({
        path: info.outputPath('procurement-pdf-review-dark.png'),
        animations: 'disabled',
    });
    await dialog
        .getByRole('button', { name: 'Review draft matches', exact: true })
        .click();
    await expect(page.getByTestId('procurement-ocr-draft')).toContainText(
        'INV-PRISM-ABC',
    );
    await expect(page.locator('#notes')).toHaveValue('');
    await expect(page.locator('#expected_delivery_at')).toHaveValue('');
    await expect(page.locator('#tax')).toHaveValue('0');
});

test('Malformed, empty, oversized and over-limit PDFs preserve manual recovery', async ({
    page,
}) => {
    await page.goto('/inventory/assets');
    await page
        .getByRole('button', { name: 'Read printed tag', exact: true })
        .click();
    const dialog = page.getByRole('dialog', { name: 'Read printed asset tag' });

    for (const sample of [
        {
            buffer: Buffer.from('not a PDF'),
            error: 'This PDF could not be read',
        },
        { buffer: Buffer.alloc(0), error: 'non-empty PDF smaller than 8 MB' },
        {
            buffer: Buffer.alloc(8 * 1024 * 1024 + 1),
            error: 'non-empty PDF smaller than 8 MB',
        },
        {
            buffer: textPdf(
                Array.from({ length: 21 }, () => ['TOO-MANY-PAGES']),
            ),
            error: 'at most 20 pages',
        },
    ]) {
        await dialog.locator('#asset-label-photo').setInputFiles({
            name: 'invalid.pdf',
            mimeType: 'application/pdf',
            buffer: sample.buffer,
        });
        await expect(dialog.getByRole('alert')).toContainText(sample.error);
        await expect(dialog.locator('#asset-label-photo')).toBeEnabled();
    }

    await dialog.locator('#ocr-identifier').fill('AST-E2E-0001');
    await dialog
        .getByRole('button', { name: 'Search existing assets', exact: true })
        .click();
    await expect(
        page.getByRole('link').filter({ hasText: 'AST-E2E-0001' }),
    ).toBeVisible();
});

test('Closing PDF review terminates its worker and clears the document before reopening', async ({
    page,
}) => {
    await page.goto('/inventory/assets');
    await page.evaluate(() => {
        const NativeWorker = window.Worker;
        const state = document.documentElement.dataset;
        state.pdfWorkersCreated = '0';
        state.pdfWorkersTerminated = '0';
        window.Worker = class extends NativeWorker {
            private readonly pdfWorker: boolean;

            constructor(url: string | URL, options?: WorkerOptions) {
                super(url, options);
                this.pdfWorker = String(url).includes('pdf.worker');

                if (this.pdfWorker) {
                    state.pdfWorkersCreated = String(
                        Number(state.pdfWorkersCreated) + 1,
                    );
                }
            }

            override terminate(): void {
                if (this.pdfWorker) {
                    state.pdfWorkersTerminated = String(
                        Number(state.pdfWorkersTerminated) + 1,
                    );
                }

                super.terminate();
            }
        };
    });
    const trigger = page.getByRole('button', {
        name: 'Read printed tag',
        exact: true,
    });
    await trigger.click();
    const dialog = page.getByRole('dialog', { name: 'Read printed asset tag' });
    await readPdf(dialog, 'AST-E2E-0001');
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(page.locator('html')).toHaveAttribute(
        'data-pdf-workers-created',
        '1',
    );
    await expect(page.locator('html')).toHaveAttribute(
        'data-pdf-workers-terminated',
        '1',
    );
    await expect(trigger).toBeFocused();
    await trigger.click();
    await expect(dialog.locator('#ocr-pdf-page')).toHaveCount(0);
    await expect(dialog.locator('pre')).toHaveCount(0);
    await expect(dialog.locator('#ocr-identifier')).toHaveValue('');
});
