import { loginAs } from './fixtures/auth';
import { expect, test } from './fixtures/test';

test.describe('Asset Booking Flow', () => {
    test('Request booking -> Approve -> Verify calendar', async ({ page }) => {
        // Step 1: Login as requester and request booking
        await loginAs(page, 'requester@e2e.test', 'password', {
            redirectTo: '/inventory/bookings',
        });
        await expect(page.getByTestId('bookings-page')).toBeVisible();

        // Search and select an asset
        await page
            .getByTestId('booking-asset-search-input')
            .fill('AST-E2E-0002');
        await expect(page).toHaveURL(/asset_search=AST-E2E-0002$/);
        await expect(page.getByTestId('booking-asset-select')).toHaveValue(
            /\d+/,
        );

        // Fill in dates
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        const startAt = `${tomorrow.toISOString().slice(0, 10)}T09:00`;
        const endAt = `${tomorrow.toISOString().slice(0, 10)}T12:00`;

        await page.getByTestId('booking-start-input').fill(startAt);
        await page.getByTestId('booking-end-input').fill(endAt);
        await page
            .getByTestId('booking-purpose-input')
            .fill('Engineering laboratory equipment reservation');

        // Submit the booking request and wait for the POST + Inertia redirect rather than networkidle.
        const requestCreated = page.waitForResponse(
            (response) =>
                new URL(response.url()).pathname === '/inventory/bookings' &&
                response.request().method() === 'POST' &&
                response.status() < 400,
        );
        await page.getByTestId('request-booking-button').click();
        await requestCreated;

        // Verify booking request was created
        await expect(page.locator('body')).toContainText('Requested');

        // Step 2: Login as Admin directly onto /inventory/bookings and approve
        await loginAs(page, 'admin@e2e.test', 'password', {
            redirectTo: '/inventory/bookings',
        });

        // The approval queue should have our pending request
        await expect(
            page.getByTestId('approve-booking-button').first(),
        ).toBeVisible({ timeout: 10000 });

        // Approve the booking
        const requestApproved = page.waitForResponse(
            (response) =>
                new URL(response.url()).pathname.startsWith(
                    '/inventory/bookings/',
                ) &&
                response.request().method() !== 'GET' &&
                response.status() < 400,
        );
        await page.getByTestId('approve-booking-button').first().click();
        await requestApproved;

        // Verify the pending action clears and the booking transitions to Approved
        await expect(page.getByTestId('approve-booking-button')).toHaveCount(0);
        await expect(page.locator('body')).toContainText('Approved');

        // Step 3: Verify the booking appears in the booking records section
        await expect(page.locator('body')).toContainText(
            'Business Laptop, 14-inch',
        );
    });
});
