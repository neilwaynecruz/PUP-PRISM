import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';
import {
    isValidDocumentDate,
    suggestProcurementFields,
} from '../../resources/js/lib/ocrReview';
import { loginAs } from './fixtures/auth';
import { resetDatabase } from './helpers/db';

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
});

async function imageWithText(page: Page, lines: string[]): Promise<Buffer> {
    const base64 = await page.evaluate((text) => {
        const canvas = document.createElement('canvas');
        canvas.width = 1500;
        canvas.height = text.length * 95 + 60;
        const context = canvas.getContext('2d');

        if (!context) {
            throw new Error('Canvas unavailable');
        }

        context.fillStyle = 'white';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.fillStyle = 'black';
        context.font = '48px monospace';
        text.forEach((line, index) =>
            context.fillText(line, 30, 80 + index * 95),
        );

        return canvas.toDataURL('image/png').split(',')[1];
    }, lines);

    return Buffer.from(base64, 'base64');
}

test('review helpers reject ambiguous dates and never infer financial fields', () => {
    expect(
        suggestProcurementFields(
            'Invoice No: INV-091\nDate: 2026-11-12\nSupplier: Campus Supply\nSKU: CON-E2E-001\nTotal: 9,999.00',
        ),
    ).toEqual({
        reference: 'INV-091',
        documentDate: '2026-11-12',
        supplier: 'Campus Supply',
        productCode: 'CON-E2E-001',
    });
    expect(suggestProcurementFields('Date: 11/12/26').documentDate).toBe('');
    expect(isValidDocumentDate('2026-02-30')).toBe(false);
    expect(isValidDocumentDate('2024-02-29')).toBe(true);
    expect(isValidDocumentDate('12/11/2026')).toBe(false);
});

test('receiving reviews tags and real OCR serial text before explicit receipt submission', async ({
    page,
}, testInfo) => {
    await loginAs(page, 'admin@e2e.test', 'password', { redirectTo: false });
    await page.goto('/inventory/receiving');
    await page.getByTestId('receiving-sku-input').fill('AST-E2E-001');
    await page
        .getByRole('button', { name: 'Read printed asset tags', exact: true })
        .click();
    const tags = page.getByRole('dialog', {
        name: 'Read receiving asset tags',
    });
    await tags.locator('#ocr-identifier').fill('OCR-RCV-001\nOCR-RCV-001');
    await tags
        .getByRole('button', { name: 'Add reviewed tags', exact: true })
        .click();
    await expect(page.getByTestId('receiving-tag-codes-input')).toHaveValue(
        'OCR-RCV-001',
    );
    await page
        .getByRole('button', { name: 'Read serial number', exact: true })
        .click();
    const reader = page.getByRole('dialog', {
        name: 'Read serial for OCR-RCV-001',
    });
    const started = Date.now();
    await reader.locator('#asset-label-photo').setInputFiles({
        name: 'synthetic-serial.png',
        mimeType: 'image/png',
        buffer: await imageWithText(page, ['SN-OCR-901']),
    });
    await expect(
        reader.getByRole('button', { name: 'SN-OCR-901', exact: true }),
    ).toBeVisible();
    await testInfo.attach('synthetic-serial-recognition-time', {
        body: JSON.stringify({ milliseconds: Date.now() - started }),
        contentType: 'application/json',
    });
    await reader
        .getByRole('button', { name: 'SN-OCR-901', exact: true })
        .click();
    await reader
        .getByRole('button', { name: 'Apply reviewed serial', exact: true })
        .click();
    await expect(
        page.getByLabel('Manufacturer serial for OCR-RCV-001', { exact: true }),
    ).toHaveValue('SN-OCR-901');
    await expect(page.getByTestId('receiving-tag-codes-input')).toHaveValue(
        'OCR-RCV-001',
    );
    await page.getByTestId('receive-stock-button').click();
    await expect(
        page.getByText('Stock received.', { exact: true }),
    ).toBeVisible();
    await page.goto('/inventory/assets');
    await page.locator('#asset-search').fill('SN-OCR-901');
    await expect(
        page.getByRole('link').filter({ hasText: 'OCR-RCV-001' }),
    ).toBeVisible();
});

test('maintenance and transfer label lookup require selecting an existing asset', async ({
    page,
}, testInfo) => {
    await loginAs(page, 'admin@e2e.test', 'password', { redirectTo: false });

    for (const workflow of ['maintenance', 'transfer'] as const) {
        await page.goto('/inventory/assets');
        await page.setViewportSize({
            width: workflow === 'maintenance' ? 390 : 834,
            height: 900,
        });
        await page.evaluate(
            (dark) => document.documentElement.classList.toggle('dark', dark),
            workflow === 'transfer',
        );
        const trigger = page.getByRole('button', {
            name: `Find ${workflow} asset from label`,
            exact: true,
        });
        await trigger.click();
        const dialog = page.getByRole('dialog', {
            name: 'Read printed asset tag',
        });
        await dialog.locator('#ocr-identifier').fill('AST-E2E-0001');
        await page.screenshot({
            path: testInfo.outputPath(`${workflow}-ocr-review.png`),
            fullPage: true,
        });
        await dialog
            .getByRole('button', {
                name: 'Search existing assets',
                exact: true,
            })
            .click();
        await expect(trigger).toBeFocused();
        await expect(page).toHaveURL(/search=AST-E2E-0001/);
        const candidate = page
            .getByRole('link')
            .filter({ hasText: 'AST-E2E-0001' });
        await expect(candidate).toHaveAttribute(
            'href',
            new RegExp(
                `#${workflow === 'maintenance' ? 'maintenance-title' : 'transfer-title'}$`,
            ),
        );
        await candidate.click();
        await expect(
            page.getByRole('heading', {
                name:
                    workflow === 'maintenance'
                        ? 'Maintenance and repairs'
                        : 'Location transfers',
                exact: true,
            }),
        ).toBeVisible();
    }
});

test('handover OCR searches candidates without selecting a recipient or initiating a handover', async ({
    page,
}) => {
    await loginAs(page, 'admin@e2e.test', 'password', { redirectTo: false });
    await page.goto('/inventory/handover');
    await page
        .getByRole('button', { name: 'Find asset from label', exact: true })
        .click();
    const dialog = page.getByRole('dialog', {
        name: 'Find handover asset from label',
    });
    await dialog.locator('#ocr-identifier').fill('AST-E2E-000');
    await dialog
        .getByRole('button', { name: 'Search existing assets', exact: true })
        .click();
    await expect(page.getByTestId('handover-asset-options')).toContainText(
        'AST-E2E-0001',
    );
    await expect(page.getByTestId('handover-selected-asset')).toHaveCount(0);
    await expect(page.getByTestId('send-verification-button')).toBeDisabled();
});

test('physical-count label lookup stays within the session and leaves observed quantity manual', async ({
    page,
}) => {
    await loginAs(page, 'admin@e2e.test', 'password', { redirectTo: false });
    await page.goto('/inventory/counts');
    await page.locator('#count-scope').fill('OCR fixture session');
    const assignee = page.locator('#count-assigned');
    const administrator = await assignee
        .locator('option')
        .filter({ hasText: 'Adrian' })
        .getAttribute('value');
    expect(administrator).toBeTruthy();
    await assignee.selectOption(administrator!);
    await page.getByRole('checkbox').filter({ visible: true }).first().check();
    await page
        .getByRole('button', { name: 'Create Count Session', exact: true })
        .click();
    await expect(page).toHaveURL(/\/inventory\/counts\/\d+$/);
    const quantities = page.locator('input[type="number"]');
    const before = await quantities.evaluateAll((inputs) =>
        inputs.map((input) => (input as HTMLInputElement).value),
    );
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
    await dialog.locator('#ocr-identifier').fill('OUTSIDE-SESSION');
    await dialog
        .getByRole('button', { name: 'Find session item', exact: true })
        .click();
    await expect(page.getByRole('status')).toContainText(
        'No matching item in this session',
    );
    await page
        .getByRole('button', { name: 'Show all items', exact: true })
        .click();
    expect(
        await quantities.evaluateAll((inputs) =>
            inputs.map((input) => (input as HTMLInputElement).value),
        ),
    ).toEqual(before);
});

test('procurement image suggestions stay editable and never change prices, quantities or delivery date', async ({
    page,
}, testInfo) => {
    await loginAs(page, 'admin@e2e.test', 'password', { redirectTo: false });
    await page.goto('/inventory/purchase-orders/create');
    await page
        .getByRole('button', { name: 'Read procurement document', exact: true })
        .click();
    const dialog = page.getByRole('dialog', {
        name: 'Review procurement document',
        exact: true,
    });
    await dialog.locator('#asset-label-photo').setInputFiles({
        name: 'synthetic-invoice.png',
        mimeType: 'image/png',
        buffer: await imageWithText(page, [
            'Invoice No: INV-PRISM-ABC',
            'Date: 2026-11-12',
            'SKU: CON-E2E-001',
        ]),
    });
    await expect(dialog.locator('pre')).toContainText('INV-PRISM-ABC');
    await dialog.locator('#ocr-document-type').selectOption('invoice');
    await dialog.locator('#ocr-reference').fill('INV-OCR-9@1');
    await expect(dialog.locator('#ocr-reference')).toHaveValue('INV-OCR-9@1');
    await dialog.locator('#ocr-reference').fill('INV-OCR-901');
    await dialog.locator('#ocr-document-date').fill('11/12/26');
    await expect(dialog.getByRole('alert')).toContainText('unambiguous');
    await expect(
        dialog.getByRole('button', {
            name: 'Review draft matches',
            exact: true,
        }),
    ).toBeDisabled();
    await dialog.locator('#ocr-document-date').fill('2026-11-12');
    await dialog.locator('#ocr-supplier').fill('NO-MATCH-SUPPLIER');
    await page.setViewportSize({ width: 390, height: 844 });
    await page.screenshot({
        path: testInfo.outputPath('procurement-review-phone-light.png'),
        fullPage: true,
    });
    await dialog
        .getByRole('button', { name: 'Review draft matches', exact: true })
        .click();
    await expect(page.getByTestId('procurement-ocr-draft')).toContainText(
        'No supplier match',
    );
    await expect(page.locator('#supplier_id')).toHaveValue('');
    await expect(page.locator('#notes')).toHaveValue('');
    await page
        .getByRole('button', {
            name: 'Add reviewed reference to order notes',
            exact: true,
        })
        .click();
    await expect(page.locator('#notes')).toHaveValue(
        'invoice reference: INV-OCR-901; document date: 2026-11-12',
    );
    await expect(page.locator('#expected_delivery_at')).toHaveValue('');
    await expect(page.locator('#tax')).toHaveValue('0');
});

test('a replacement document image clears old suggestions even when reading fails', async ({
    page,
}) => {
    await loginAs(page, 'admin@e2e.test', 'password', { redirectTo: false });
    await page.goto('/inventory/purchase-orders/create');
    await page
        .getByRole('button', { name: 'Read procurement document', exact: true })
        .click();
    const dialog = page.getByRole('dialog', {
        name: 'Review procurement document',
        exact: true,
    });
    await dialog.locator('#ocr-reference').fill('PREVIOUS-DRAFT');
    await dialog.locator('#ocr-supplier').fill('PREVIOUS-SUPPLIER');
    await dialog.locator('#asset-label-photo').setInputFiles({
        name: 'broken.png',
        mimeType: 'image/png',
        buffer: Buffer.from('not an image'),
    });
    await expect(dialog.getByRole('alert')).toContainText('could not be read');
    await expect(dialog.locator('#ocr-reference')).toHaveValue('');
    await expect(dialog.locator('#ocr-supplier')).toHaveValue('');
    await expect(
        dialog.getByRole('button', {
            name: 'Review draft matches',
            exact: true,
        }),
    ).toBeDisabled();
    await page.keyboard.press('Escape');
    await expect(
        page.getByRole('button', {
            name: 'Read procurement document',
            exact: true,
        }),
    ).toBeFocused();
    await expect(page.locator('#notes')).toHaveValue('');
});
