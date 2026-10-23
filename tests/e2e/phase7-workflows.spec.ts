import { resolve } from 'path';
import { loginAs } from './fixtures/auth';
import { expect, test } from './fixtures/test';

test.describe('Phase 7 optimized workflows', () => {
    test('requester can find a consumable by name and add it without knowing the SKU', async ({
        page,
    }) => {
        await loginAs(page, 'requester@e2e.test');
        await page.goto('/inventory/requisitions');

        await page
            .getByRole('button', { name: 'Browse Catalog', exact: true })
            .click();
        const dialog = page.getByRole('dialog', {
            name: 'Browse products for requisition',
        });

        await expect(dialog).toBeVisible();
        await expect(
            dialog.getByText('consumable products only'),
        ).toBeVisible();
        await dialog
            .getByRole('searchbox', { name: 'Search the product catalog' })
            .fill('  copy   paper ');
        await expect(
            dialog.getByText('Multipurpose Copy Paper, A4, 80 gsm'),
        ).toBeVisible();
        await dialog.getByLabel('Quantity').fill('2');
        await dialog.getByRole('button', { name: 'Add' }).click();
        await dialog.getByRole('button', { name: 'Done' }).click();

        await expect(page.getByTestId('requisition-sku-input')).toHaveValue(
            'CON-E2E-001',
        );
        await expect(page.getByTestId('requisition-qty-input')).toHaveValue(
            '2',
        );
    });

    test('filtered label scope is confirmed and the rendered QR decodes to its SKU', async ({
        page,
    }, testInfo) => {
        await loginAs(page, 'supply@e2e.test');
        await page.goto('/inventory/products');

        await page
            .getByRole('button', { name: 'Print labels' })
            .first()
            .click();
        const confirmation = page.getByRole('dialog', {
            name: 'Print labels for filtered products?',
        });

        await expect(confirmation).toBeVisible();
        await confirmation
            .getByRole('button', { name: 'Continue to label preview' })
            .click();
        await expect(page).toHaveURL(/\/inventory\/products\/labels\/sheet/);
        await expect(
            page.getByRole('heading', { name: 'Bulk QR Label Printing' }),
        ).toBeVisible();
        await expect(
            page.getByRole('checkbox', { name: 'Include product SKU' }),
        ).toBeChecked();
        await expect(
            page.getByRole('checkbox', { name: 'Include product name' }),
        ).toBeChecked();
        await expect(
            page.getByRole('checkbox', { name: 'Include product category' }),
        ).not.toBeChecked();

        await page.addScriptTag({
            path: resolve('node_modules/jsqr/dist/jsQR.js'),
        });

        const firstLabel = page.locator('.label-card').first();
        await expect(firstLabel).toBeVisible();
        await page.screenshot({
            path: testInfo.outputPath('bulk-label-preview-light.png'),
            fullPage: false,
        });
        await page.evaluate(() =>
            document.documentElement.classList.add('dark'),
        );
        await page.screenshot({
            path: testInfo.outputPath('bulk-label-preview-dark.png'),
            fullPage: false,
        });
        const expectedSku = (
            await firstLabel.locator('.font-mono').textContent()
        )?.trim();
        const decodedSku = await firstLabel
            .locator('svg')
            .evaluate(async (svg) => {
                const serializedSvg = new XMLSerializer().serializeToString(
                    svg,
                );
                const image = new Image();
                const source = URL.createObjectURL(
                    new Blob([serializedSvg], { type: 'image/svg+xml' }),
                );

                try {
                    await new Promise<void>((resolveImage, rejectImage) => {
                        image.onload = () => resolveImage();
                        image.onerror = () =>
                            rejectImage(
                                new Error('QR SVG could not be rendered.'),
                            );
                        image.src = source;
                    });

                    const canvas = document.createElement('canvas');
                    canvas.width = 640;
                    canvas.height = 640;
                    const context = canvas.getContext('2d');

                    if (!context) {
                        throw new Error('Canvas is unavailable.');
                    }

                    context.fillStyle = '#ffffff';
                    context.fillRect(0, 0, canvas.width, canvas.height);
                    context.drawImage(image, 40, 40, 560, 560);
                    const pixels = context.getImageData(
                        0,
                        0,
                        canvas.width,
                        canvas.height,
                    );
                    const decoder = (
                        window as typeof window & {
                            jsQR: (
                                data: Uint8ClampedArray,
                                width: number,
                                height: number,
                            ) => { data: string } | null;
                        }
                    ).jsQR;

                    return (
                        decoder(pixels.data, pixels.width, pixels.height)
                            ?.data ?? null
                    );
                } finally {
                    URL.revokeObjectURL(source);
                }
            });

        expect(decodedSku).toBe(expectedSku);
    });

    test('sidebar search opens its first result with the keyboard', async ({
        page,
    }) => {
        await loginAs(page, 'admin@e2e.test');
        await page.goto('/dashboard');

        const navigationSearch = page.getByRole('searchbox', {
            name: 'Search navigation',
        });
        await navigationSearch.fill('Products');
        await navigationSearch.press('Enter');

        await expect(page).toHaveURL(/\/inventory\/products$/);
    });

    for (const viewport of [
        { width: 320, height: 720 },
        { width: 375, height: 812 },
        { width: 768, height: 1024 },
        { width: 1024, height: 900 },
        { width: 1440, height: 1000 },
    ]) {
        for (const theme of ['light', 'dark'] as const) {
            test(`requisition catalog is responsive at ${viewport.width}px in ${theme} mode`, async ({
                page,
            }, testInfo) => {
                await page.setViewportSize(viewport);
                await loginAs(page, 'requester@e2e.test');
                await page.goto('/inventory/requisitions');
                await page.evaluate((selectedTheme) => {
                    document.documentElement.classList.toggle(
                        'dark',
                        selectedTheme === 'dark',
                    );
                }, theme);
                await page
                    .getByRole('button', {
                        name: 'Browse Catalog',
                        exact: true,
                    })
                    .click();
                await expect(
                    page.getByRole('dialog', {
                        name: 'Browse products for requisition',
                    }),
                ).toBeVisible();
                await expect(
                    page.getByText('Multipurpose Copy Paper, A4, 80 gsm'),
                ).toBeVisible();

                const hasHorizontalOverflow = await page.evaluate(
                    () =>
                        document.documentElement.scrollWidth >
                        document.documentElement.clientWidth,
                );

                expect(hasHorizontalOverflow).toBeFalsy();
                const dialogBox = await page
                    .getByRole('dialog', {
                        name: 'Browse products for requisition',
                    })
                    .boundingBox();

                expect(dialogBox).not.toBeNull();
                expect(dialogBox!.x).toBeGreaterThanOrEqual(0);
                expect(dialogBox!.x + dialogBox!.width).toBeLessThanOrEqual(
                    viewport.width,
                );
                expect(dialogBox!.height).toBeLessThanOrEqual(viewport.height);
                await page.screenshot({
                    path: testInfo.outputPath(
                        `requisition-catalog-${viewport.width}-${theme}.png`,
                    ),
                    fullPage: false,
                });
            });
        }
    }
});
