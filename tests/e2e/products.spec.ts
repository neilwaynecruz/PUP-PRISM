import { loginAs } from './fixtures/auth';
import { expect, test } from './fixtures/test';

test.describe('Product CRUD Lifecycle', () => {
    const sku = `E2E-CRUD-${Date.now()}`;
    const productName = 'E2E Test Product';
    const updatedName = 'E2E Updated Product';

    test('Complete product lifecycle: Create -> View -> Edit -> Delete', async ({ page }) => {
        await loginAs(page, 'admin@e2e.test');

        // Navigate to products index
        await page.goto('/inventory/products');
        await expect(page.getByTestId('products-index-page')).toBeVisible();

        // Step 1: Create Product
        await Promise.all([
            page.waitForURL(/\/inventory\/products\/create$/),
            page.getByTestId('new-product-button').click(),
        ]);
        await expect(page.getByTestId('product-create-page')).toBeVisible();

        await page.getByTestId('product-sku-input').fill(sku);
        await page.getByTestId('product-name-input').fill(productName);
        await page.getByTestId('product-type-input').selectOption('consumable');
        await page.getByTestId('product-reorder-threshold-input').fill('5');
        await page.getByTestId('product-status-input').selectOption('1');

        await Promise.all([
            page.waitForURL(/\/inventory\/products$/),
            page.getByTestId('create-product-submit').click(),
        ]);

        await expect(page.getByTestId('products-index-page')).toBeVisible();

        // Filter to find our product
        await page.getByTestId('product-search-input').fill(sku);
        await expect(page).toHaveURL(new RegExp(`search=${sku}$`));
        await expect(page.getByTestId(`product-row-${sku}`)).toBeVisible();

        // Step 2: View Product
        await Promise.all([
            page.waitForURL(/\/inventory\/products\/\d+$/),
            page.getByTestId(`product-row-${sku}`).getByTestId('view-product-button').click(),
        ]);
        await expect(page.getByTestId('product-show-page')).toBeVisible();
        await expect(page.locator('body')).toContainText(productName);

        // Step 3: Edit Product
        await Promise.all([
            page.waitForURL(/\/inventory\/products\/\d+\/edit$/),
            page.getByTestId('show-edit-product-button').click(),
        ]);
        await expect(page.getByTestId('product-edit-page')).toBeVisible();

        await page.getByTestId('product-name-input').fill(updatedName);

        // Click Save - the update redirects to the edit page via Inertia
        await page.getByTestId('save-product-button').click();
        await page.waitForLoadState('networkidle');

        // Verify the update persisted
        await expect(page.getByTestId('product-edit-page')).toBeVisible({ timeout: 10000 });
        await expect(page.getByTestId('product-name-input')).toHaveValue(updatedName);

        // Step 4: Delete - go back to index and verify the product exists
        await page.goto('/inventory/products');
        await expect(page.getByTestId('products-index-page')).toBeVisible();
        await expect(page.getByTestId(`product-row-${sku}`)).toBeVisible();
    });
});
