import type { Page } from '@playwright/test';
import { loginAs } from './fixtures/auth';
import { expect, test } from './fixtures/test';

test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
        if ('serviceWorker' in navigator) {
            Object.defineProperty(navigator.serviceWorker, 'register', {
                configurable: true,
                value: () => new Promise(() => {}),
            });
        }
    });
});

test.afterEach(async ({ page }) => {
    await page.unrouteAll({ behavior: 'ignoreErrors' });
});

test('a failed OCR interface download explains recovery and keeps asset search usable', async ({
    page,
}) => {
    await loginAs(page, 'admin@e2e.test');
    await page.goto('/inventory/assets');
    let intercepted = false;
    await page.route('**/AssetOcrDialog-*.js', async (route) => {
        intercepted = true;
        await route.abort('failed');
    });
    await page
        .getByRole('button', { name: 'Read printed tag', exact: true })
        .click();
    await expect(page.getByRole('alert')).toContainText('could not load');
    expect(intercepted).toBe(true);
    await page.locator('#asset-search').fill('AST-E2E-0001');
    await expect(page).toHaveURL(/search=AST-E2E-0001/);
    await expect(
        page.getByRole('link').filter({ hasText: 'AST-E2E-0001' }),
    ).toBeVisible();
});

test('invalid image types, empty files and oversized photos have actionable errors', async ({
    page,
}) => {
    await loginAs(page, 'admin@e2e.test');
    await page.goto('/inventory/assets');
    await page
        .getByRole('button', { name: 'Read printed tag', exact: true })
        .click();
    const dialog = page.getByRole('dialog', { name: 'Read printed asset tag' });

    for (const sample of [
        {
            name: 'document.pdf',
            mimeType: 'application/pdf',
            buffer: Buffer.from('%PDF'),
            message: 'Choose a JPEG, PNG, or WebP',
        },
        {
            name: 'empty.png',
            mimeType: 'image/png',
            buffer: Buffer.alloc(0),
            message: 'non-empty image smaller than 8 MB',
        },
        {
            name: 'large.png',
            mimeType: 'image/png',
            buffer: Buffer.alloc(8 * 1024 * 1024 + 1),
            message: 'non-empty image smaller than 8 MB',
        },
    ]) {
        await dialog.locator('#asset-label-photo').setInputFiles(sample);
        await expect(dialog.getByRole('alert')).toContainText(sample.message);
        await expect(dialog.locator('#asset-label-photo')).toBeEnabled();
    }

    await page.evaluate(() => {
        Object.defineProperty(window, 'Worker', {
            configurable: true,
            value: undefined,
        });
    });
    await dialog.locator('#asset-label-photo').setInputFiles({
        name: 'label.png',
        mimeType: 'image/png',
        buffer: await labelImage(page, 'AST-E2E-0001'),
    });
    await expect(dialog.getByRole('alert')).toContainText(
        'unavailable in this browser',
    );
    await dialog.locator('#ocr-identifier').fill('AST-E2E-0001');
    await expect(
        dialog.getByRole('button', { name: 'Search existing assets' }),
    ).toBeEnabled();
});

test('mobile keyboard users stay inside the OCR dialog and can submit manual lookup', async ({
    page,
}) => {
    await page.setViewportSize({ width: 320, height: 720 });
    await loginAs(page, 'admin@e2e.test');
    await page.goto('/inventory/assets');
    await page
        .getByRole('button', { name: 'Read printed tag', exact: true })
        .click();
    const dialog = page.getByRole('dialog', { name: 'Read printed asset tag' });
    await dialog.locator('#ocr-identifier').focus();

    for (const key of [
        'Tab',
        'Tab',
        'Tab',
        'Tab',
        'Shift+Tab',
        'Shift+Tab',
        'Shift+Tab',
        'Shift+Tab',
    ]) {
        await page.keyboard.press(key);
        await expect(dialog.locator(':focus')).toHaveCount(1);
    }

    await dialog.locator('#ocr-identifier').fill('AST-E2E-0001');
    await dialog.locator('#ocr-identifier').press('Enter');
    await expect(dialog).toBeHidden();
    await expect(page).toHaveURL(/search=AST-E2E-0001/);
    await expect(
        page.getByRole('link').filter({ hasText: 'AST-E2E-0001' }),
    ).toBeVisible();
});

test('cancelling while OCR initializes releases the worker and does not reuse stale text', async ({
    page,
}) => {
    await loginAs(page, 'admin@e2e.test');
    await page.goto('/inventory/assets');
    let releaseModel: (() => void) | undefined;
    const heldModel = new Promise<void>((resolve) => {
        releaseModel = resolve;
    });
    await page.route('**/ocr/eng.traineddata', async (route) => {
        if (route.request().method() === 'HEAD') {
            await route.fulfill({ status: 200, body: '' });

            return;
        }

        await heldModel;
        await route.fulfill({
            status: 200,
            path: 'public/ocr/eng.traineddata',
            contentType: 'application/octet-stream',
        });
    });
    await page
        .getByRole('button', { name: 'Read printed tag', exact: true })
        .click();
    const dialog = page.getByRole('dialog', { name: 'Read printed asset tag' });
    const modelRequested = page.waitForRequest(
        (request) =>
            request.url().endsWith('/ocr/eng.traineddata') &&
            request.method() === 'GET',
    );
    const modelResponded = page.waitForResponse(
        (response) =>
            response.url().endsWith('/ocr/eng.traineddata') &&
            response.request().method() === 'GET',
    );
    await dialog.locator('#asset-label-photo').setInputFiles({
        name: 'label.png',
        mimeType: 'image/png',
        buffer: await labelImage(page, 'AST-E2E-0001'),
    });
    await modelRequested;
    await dialog
        .getByRole('button', { name: 'Cancel and use manual search' })
        .click();
    await expect(dialog).toBeHidden();
    releaseModel?.();
    await modelResponded;
    await expect.poll(() => page.workers().length).toBe(0);
    await page
        .getByRole('button', { name: 'Read printed tag', exact: true })
        .click();
    await expect(dialog.locator('#ocr-identifier')).toHaveValue('');
    await expect(
        dialog.getByText('Detected text', { exact: false }),
    ).toHaveCount(0);
    await expect(dialog.locator('#asset-label-photo')).toBeEnabled();
});

async function labelImage(page: Page, text: string): Promise<Buffer> {
    const image = await page.evaluate((value) => {
        const canvas = document.createElement('canvas');
        canvas.width = 1100;
        canvas.height = 220;
        const context = canvas.getContext('2d')!;
        context.fillStyle = 'white';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.fillStyle = 'black';
        context.font = '60px monospace';
        context.fillText(value, 40, 130);

        return canvas.toDataURL('image/png').split(',')[1];
    }, text);

    return Buffer.from(image, 'base64');
}

test('actual local OCR reads tag and serial fixtures, requires review, and supports explicit record selection', async ({
    page,
}, testInfo) => {
    await loginAs(page, 'admin@e2e.test');
    await page.goto('/inventory/assets');
    const ocrRequests: string[] = [];
    page.on('request', (request) => {
        if (/worker.min|tesseract-core|traineddata/.test(request.url())) {
            ocrRequests.push(request.url());
        }
    });

    for (const text of ['AST-E2E-0001', 'SN-PRISM-901']) {
        await page
            .getByRole('button', { name: 'Read printed tag', exact: true })
            .click();
        const dialog = page.getByRole('dialog', {
            name: 'Read printed asset tag',
        });
        const started = Date.now();
        await dialog.locator('#asset-label-photo').setInputFiles({
            name: 'synthetic-label.png',
            mimeType: 'image/png',
            buffer: await labelImage(page, text),
        });
        await expect(
            dialog.getByRole('button', { name: text, exact: true }),
        ).toBeVisible();
        await testInfo.attach(`synthetic-${text}`, {
            body: JSON.stringify({
                expected: text,
                milliseconds: Date.now() - started,
            }),
            contentType: 'application/json',
        });
        await expect(page).not.toHaveURL(/search=/);
        await dialog.getByRole('button', { name: text, exact: true }).click();
        await expect(dialog.locator('#ocr-identifier')).toHaveValue(text);
        await dialog.locator('#ocr-identifier').fill('AST-E2E-0001');
        await dialog
            .getByRole('button', { name: 'Search existing assets' })
            .click();
        await expect(page).toHaveURL(/search=AST-E2E-0001/);
        await expect(
            page.getByRole('link').filter({ hasText: 'AST-E2E-0001' }),
        ).toBeVisible();
        await page.goto('/inventory/assets');
    }

    expect(ocrRequests.some((url) => url.includes('traineddata'))).toBeTruthy();
    expect(
        ocrRequests.every(
            (url) => new URL(url).origin === new URL(page.url()).origin,
        ),
    ).toBeTruthy();
    await page.locator('#asset-search').fill('AST-E2E-0001');
    await page.getByRole('link').filter({ hasText: 'AST-E2E-0001' }).click();
    await expect(page).toHaveURL(/\/inventory\/assets\/\d+$/);
});

test('unreadable and invalid images recover to editable manual search with no-match and multiple candidates', async ({
    page,
}) => {
    await loginAs(page, 'admin@e2e.test');
    await page.goto('/inventory/assets');
    await page
        .getByRole('button', { name: 'Read printed tag', exact: true })
        .click();
    const dialog = page.getByRole('dialog', { name: 'Read printed asset tag' });
    await dialog.locator('#asset-label-photo').setInputFiles({
        name: 'invalid.png',
        mimeType: 'image/png',
        buffer: Buffer.from('not an image'),
    });
    await expect(dialog.getByRole('alert')).toContainText('could not be read');
    await dialog.locator('#asset-label-photo').setInputFiles({
        name: 'blank.png',
        mimeType: 'image/png',
        buffer: await labelImage(page, ''),
    });
    await expect(dialog.getByRole('alert')).toContainText('No readable text');
    await dialog.locator('#ocr-identifier').fill('NO-SUCH-ASSET');
    await dialog
        .getByRole('button', { name: 'Search existing assets' })
        .click();
    await expect(
        page.getByRole('heading', { name: 'No assets found' }),
    ).toBeVisible();
    await page.locator('#asset-search').fill('AST-E2E-000');
    await expect(page).toHaveURL(/search=AST-E2E-000/);
    await expect(
        page.getByRole('link').filter({ hasText: 'AST-E2E-0001' }),
    ).toBeVisible();
    await expect(
        page.getByRole('link').filter({ hasText: 'AST-E2E-0002' }),
    ).toBeVisible();
});

test('OCR failure preserves manual input and dialog fits both themes on phones, tablets and desktop', async ({
    page,
}, testInfo) => {
    await loginAs(page, 'admin@e2e.test');
    await page.goto('/inventory/assets');
    await page.route('**/ocr/eng.traineddata', (route) =>
        route.fulfill({ status: 503, body: 'Unavailable' }),
    );
    await page
        .getByRole('button', { name: 'Read printed tag', exact: true })
        .click();
    const dialog = page.getByRole('dialog', { name: 'Read printed asset tag' });
    await dialog.locator('#asset-label-photo').setInputFiles({
        name: 'label.png',
        mimeType: 'image/png',
        buffer: await labelImage(page, 'AST-E2E-0001'),
    });
    await expect(dialog.getByRole('alert')).toContainText(
        'language model is unavailable',
    );

    for (const width of [320, 390, 834, 1440]) {
        await page.setViewportSize({ width, height: 900 });

        for (const theme of ['light', 'dark']) {
            await page.evaluate(
                (value) =>
                    document.documentElement.classList.toggle(
                        'dark',
                        value === 'dark',
                    ),
                theme,
            );
            await expect(dialog).toBeVisible();
            expect(
                await page.evaluate(
                    () =>
                        document.documentElement.scrollWidth <=
                        window.innerWidth,
                ),
            ).toBeTruthy();
            const bounds = await dialog.boundingBox();
            expect(bounds!.x).toBeGreaterThanOrEqual(0);
            expect(bounds!.x + bounds!.width).toBeLessThanOrEqual(width);
            await testInfo.attach(`ocr-${theme}-${width}`, {
                body: await page.screenshot({
                    path: testInfo.outputPath(`ocr-${theme}-${width}.png`),
                    animations: 'disabled',
                }),
                contentType: 'image/png',
            });
        }
    }

    await dialog.locator('#ocr-identifier').fill('AST-E2E-0001');
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(
        page.getByRole('button', { name: 'Read printed tag', exact: true }),
    ).toBeFocused();
    await page.locator('#asset-search').fill('AST-E2E-0001');
    await expect(page).toHaveURL(/search=AST-E2E-0001/);
    await expect(
        page.getByRole('link').filter({ hasText: 'AST-E2E-0001' }),
    ).toBeVisible();
});

test('closing QR dialog during permission request releases the late camera stream', async ({
    page,
}) => {
    await page.addInitScript(() => {
        const state = window as Window & {
            releaseCamera?: () => void;
            cameraStopped?: boolean;
        };
        const mediaDevices = navigator.mediaDevices ?? ({} as MediaDevices);
        Object.defineProperty(navigator, 'mediaDevices', {
            configurable: true,
            value: mediaDevices,
        });
        Object.defineProperty(mediaDevices, 'getUserMedia', {
            configurable: true,
            value: () =>
                new Promise((resolve) => {
                    state.releaseCamera = () =>
                        resolve({
                            getTracks: () => [
                                {
                                    stop: () => {
                                        state.cameraStopped = true;
                                    },
                                },
                            ],
                        });
                }),
        });
    });
    await loginAs(page, 'admin@e2e.test');
    await page.goto('/inventory/assets');
    await page
        .getByRole('button', { name: 'Scan asset QR code', exact: true })
        .click();
    await expect(page.getByText('Requesting camera access…')).toBeVisible();
    await page.keyboard.press('Escape');
    await page.evaluate(() =>
        (window as Window & { releaseCamera?: () => void }).releaseCamera?.(),
    );
    await expect
        .poll(() =>
            page.evaluate(
                () =>
                    (window as Window & { cameraStopped?: boolean })
                        .cameraStopped,
            ),
        )
        .toBe(true);
    await page.evaluate(() => {
        Object.defineProperty(navigator.mediaDevices, 'getUserMedia', {
            configurable: true,
            value: async () => {
                throw new DOMException('Permission denied', 'NotAllowedError');
            },
        });
    });
    await page
        .getByRole('button', { name: 'Scan asset QR code', exact: true })
        .click();
    await expect(page.getByRole('alert')).toContainText(
        'Camera access was blocked. Allow camera permission or enter the code manually.',
    );
    await page.keyboard.press('Escape');
    await page.locator('#asset-search').fill('AST-E2E-0001');
    await expect(page).toHaveURL(/search=AST-E2E-0001/);
    await expect(
        page.getByRole('link').filter({ hasText: 'AST-E2E-0001' }),
    ).toBeVisible();
});
