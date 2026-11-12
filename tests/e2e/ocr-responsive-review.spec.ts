import { expect, test } from '@playwright/test';
import type { Locator, Page, TestInfo } from '@playwright/test';
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
    await loginAs(page, 'admin@e2e.test');
});

async function inspectDialog(
    page: Page,
    dialog: Locator,
    info: TestInfo,
): Promise<void> {
    for (const [width, dark] of [
        [320, false],
        [834, true],
        [1440, false],
    ] as const) {
        await page.setViewportSize({ width, height: 900 });
        await page.evaluate(
            (value) => document.documentElement.classList.toggle('dark', value),
            dark,
        );
        await expect(dialog).toBeVisible();
        await expect(dialog).toHaveCSS('opacity', '1');
        const bounds = await dialog.boundingBox();
        expect(bounds).not.toBeNull();
        expect(bounds!.x).toBeGreaterThanOrEqual(0);
        expect(bounds!.y).toBeGreaterThanOrEqual(0);
        expect(bounds!.x + bounds!.width).toBeLessThanOrEqual(width);
        expect(bounds!.y + bounds!.height).toBeLessThanOrEqual(901);
        await page.keyboard.press('Tab');
        expect(
            await dialog.evaluate((element) =>
                element.contains(document.activeElement),
            ),
        ).toBe(true);
        await page.screenshot({
            path: info.outputPath(
                `ocr-review-${width}-${dark ? 'dark' : 'light'}.png`,
            ),
            animations: 'disabled',
        });
    }
}

test('receiving serial review fits phone tablet and desktop', async ({
    page,
}, info) => {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto('/inventory/receiving');
    await page.getByTestId('receiving-sku-input').fill('AST-E2E-001');
    await page.getByTestId('receiving-tag-codes-input').fill('OCR-VISUAL-TAG');
    await page
        .getByRole('button', { name: 'Read serial number', exact: true })
        .click();
    await inspectDialog(
        page,
        page.getByRole('dialog', { name: 'Read serial for OCR-VISUAL-TAG' }),
        info,
    );
    await page.keyboard.press('Escape');
    await expect(
        page.getByRole('button', { name: 'Read serial number', exact: true }),
    ).toBeFocused();
    await expect(
        page.getByLabel('Manufacturer serial for OCR-VISUAL-TAG', {
            exact: true,
        }),
    ).toHaveValue('');
    expect(errors).toEqual([]);
});

test('handover review fits phone tablet and desktop', async ({
    page,
}, info) => {
    await page.goto('/inventory/handover');
    await page
        .getByRole('button', { name: 'Find asset from label', exact: true })
        .click();
    await inspectDialog(
        page,
        page.getByRole('dialog', { name: 'Find handover asset from label' }),
        info,
    );
});

test('physical-count review fits phone tablet and desktop', async ({
    page,
}, info) => {
    await page.goto('/inventory/counts');
    await page.locator('#count-scope').fill('Responsive OCR synthetic count');
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
    await inspectDialog(
        page,
        page.getByRole('dialog', {
            name: 'Find count item from label',
            exact: true,
        }),
        info,
    );
});

test('procurement review fits phone tablet and desktop with scrollable actions', async ({
    page,
}, info) => {
    await page.goto('/inventory/purchase-orders/create');
    await page
        .getByRole('button', { name: 'Read procurement document', exact: true })
        .click();
    const dialog = page.getByRole('dialog', {
        name: 'Review procurement document',
    });
    await dialog.locator('#ocr-reference').fill('REVIEW-ONLY');
    await inspectDialog(page, dialog, info);
    await dialog
        .getByRole('button', { name: 'Review draft matches', exact: true })
        .scrollIntoViewIfNeeded();
    await expect(
        dialog.getByRole('button', {
            name: 'Review draft matches',
            exact: true,
        }),
    ).toBeEnabled();
    await page.keyboard.press('Escape');
    await expect(page.locator('#notes')).toHaveValue('');
});
