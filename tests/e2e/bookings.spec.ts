import { loginAs } from './fixtures/auth';
import { expect, test } from './fixtures/test';

test.describe('Asset Booking Flow', () => {
    test('Request booking -> Approve -> Verify calendar', async ({ page }) => {
        // Step 1: Login as requester and request booking
        await loginAs(page, 'requester@e2e.test');

        await page.goto('/inventory/bookings');
        await expect(page.getByTestId('bookings-page')).toBeVisible();

        // Search and select an asset
        await page.getByTestId('booking-asset-search-input').fill('AST-E2E-0002');
        await expect(page).toHaveURL(/asset_search=AST-E2E-0002$/);
        await expect(page.getByTestId('booking-asset-select')).toHaveValue(/\d+/);

        // Fill in dates
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        const startAt = `${tomorrow.toISOString().slice(0, 10)}T09:00`;
        const endAt = `${tomorrow.toISOString().slice(0, 10)}T12:00`;

        await page.getByTestId('booking-start-input').fill(startAt);
        await page.getByTestId('booking-end-input').fill(endAt);
        await page.getByTestId('booking-purpose-input').fill('E2E Test Booking');

        // Submit the booking request
        await page.getByTestId('request-booking-button').click();
        await page.waitForLoadState('networkidle');

        // Verify booking request was created
        await expect(page.locator('body')).toContainText('Requested');

        // Step 2: Login as Admin and approve
        await loginAs(page, 'admin@e2e.test');

        await page.goto('/inventory/bookings');
        await page.waitForLoadState('networkidle');

        // The approval queue should have our pending request
        await expect(page.getByTestId('approve-booking-button').first()).toBeVisible({ timeout: 10000 });

        // Approve the booking
        await page.getByTestId('approve-booking-button').first().click();
        await page.waitForLoadState('networkidle');

        // Verify the booking was approved
        await expect(page.locator('body')).toContainText('Approved');

        // Step 3: Verify the booking appears in the booking records section
        await expect(page.locator('body')).toContainText('E2E Test Asset');
    });
});
