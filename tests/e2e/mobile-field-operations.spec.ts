import { expect, test } from './fixtures/test';
import { loginAs } from './fixtures/auth';

test.use({
    viewport: { width: 390, height: 844 },
    isMobile: true,
    hasTouch: true,
});

test.describe('Mobile Field Operations', () => {
    test('receiving batch mode shows mobile cards and scanner entry points', async ({ page }) => {
        await loginAs(page, 'admin@e2e.test');

        await page.goto('/inventory/receiving');
        await expect(page.getByTestId('receiving-page')).toBeVisible();

        await page.getByRole('button', { name: 'Batch', exact: true }).click();
        await expect(page.getByTestId('receiving-batch-mobile-lines')).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Batch scan tags' }).first(),
        ).toBeVisible();

        await page.getByRole('button', { name: '+ Add line' }).click();
        await expect(page.getByText('Batch line 2')).toBeVisible();
    });

    test('bookings puts the request form first on mobile and keeps scanner access visible', async ({ page }) => {
        await loginAs(page, 'requester@e2e.test');

        await page.goto('/inventory/bookings');
        await expect(page.getByTestId('bookings-page')).toBeVisible();
        await expect(page.getByText('New booking request').first()).toBeVisible();
        await expect(page.getByTestId('booking-scan-button')).toBeVisible();
        await expect(
            page.getByText(
                'Mobile view uses a compact weekly calendar for faster field scheduling.',
            ),
        ).toBeVisible();
    });

    test('forecasting exposes mobile cards and registers the PWA shell', async ({ page }) => {
        await loginAs(page, 'admin@e2e.test');

        await page.goto('/inventory/forecasting');
        await expect(page.getByTestId('forecast-mobile-cards')).toBeVisible();

        const manifestResponse = await page.request.get('/manifest.webmanifest');
        expect(manifestResponse.ok()).toBeTruthy();

        await page.goto('/dashboard');
        await page.waitForFunction(async () => {
            if (!('serviceWorker' in navigator)) {
                return false;
            }

            const registrations = await navigator.serviceWorker.getRegistrations();

            return registrations.length > 0;
        });
    });
});
