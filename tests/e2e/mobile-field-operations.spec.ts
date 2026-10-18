import { loginAs } from './fixtures/auth';
import { expect, test } from './fixtures/test';

test.use({
    viewport: { width: 390, height: 844 },
    isMobile: true,
    hasTouch: true,
});

test.describe('Mobile Field Operations', () => {
    test('receiving batch mode shows mobile cards and scanner entry points', async ({
        page,
    }) => {
        await loginAs(page, 'admin@e2e.test');

        await page.goto('/inventory/receiving');
        await expect(page.getByTestId('receiving-page')).toBeVisible();

        await page.getByRole('button', { name: 'Batch', exact: true }).click();
        await expect(
            page.getByTestId('receiving-batch-mobile-lines'),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Batch scan tags' }).first(),
        ).toBeVisible();

        await page.getByRole('button', { name: 'Add line' }).click();
        await expect(page.getByText('Batch line 2')).toBeVisible();
    });

    test('bookings puts the request form first on mobile and keeps scanner access visible', async ({
        page,
    }) => {
        await loginAs(page, 'requester@e2e.test');

        await page.goto('/inventory/bookings');
        await expect(page.getByTestId('bookings-page')).toBeVisible();
        await expect(
            page.getByText('New booking request').first(),
        ).toBeVisible();
        await expect(page.getByTestId('booking-scan-button')).toBeVisible();
        await expect(
            page.getByText(
                'Mobile view uses a compact weekly calendar for faster field scheduling.',
            ),
        ).toBeVisible();
    });

    test('scanner reports denied camera permission and keeps manual entry available', async ({
        page,
    }) => {
        await page.addInitScript(() => {
            const mediaDevices = navigator.mediaDevices ?? ({} as MediaDevices);

            Object.defineProperty(mediaDevices, 'getUserMedia', {
                configurable: true,
                value: async () => {
                    throw new DOMException(
                        'Permission denied',
                        'NotAllowedError',
                    );
                },
            });
            Object.defineProperty(navigator, 'mediaDevices', {
                configurable: true,
                value: mediaDevices,
            });
        });

        await loginAs(page, 'requester@e2e.test');

        const response = await page.goto('/inventory/bookings');

        expect(response?.headers()['permissions-policy']).toBe(
            'camera=(self), microphone=(), geolocation=(), payment=()',
        );

        await page.getByTestId('booking-scan-button').click();
        await expect(
            page.getByText(
                'Camera access was blocked. Allow camera permission or enter the code manually.',
            ),
        ).toBeVisible();
        await expect(page.getByTestId('booking-asset-select')).toBeVisible();
    });

    test('scanner reports unavailable and busy camera states', async ({
        page,
    }) => {
        await page.addInitScript(() => {
            const scannerWindow = window as Window & {
                cameraFailureName?: string;
            };
            const mediaDevices = navigator.mediaDevices ?? ({} as MediaDevices);

            scannerWindow.cameraFailureName = 'NotFoundError';
            Object.defineProperty(mediaDevices, 'getUserMedia', {
                configurable: true,
                value: async () => {
                    throw new DOMException(
                        'Camera unavailable',
                        scannerWindow.cameraFailureName,
                    );
                },
            });
            Object.defineProperty(navigator, 'mediaDevices', {
                configurable: true,
                value: mediaDevices,
            });
        });

        await loginAs(page, 'requester@e2e.test');
        await page.goto('/inventory/bookings');

        await page.getByTestId('booking-scan-button').click();
        await expect(
            page.getByText(
                'No camera was found on this device. Enter the code manually.',
            ),
        ).toBeVisible();
        await page.getByRole('button', { name: 'Close' }).first().click();

        await page.evaluate(() => {
            (
                window as Window & { cameraFailureName?: string }
            ).cameraFailureName = 'NotReadableError';
        });
        await page.getByTestId('booking-scan-button').click();
        await expect(
            page.getByText(
                'The camera is unavailable or already in use by another app. Close the other app and retry, or enter the code manually.',
            ),
        ).toBeVisible();
        await expect(page.getByTestId('booking-asset-select')).toBeVisible();
    });

    test('scanner reports unsupported browser and keeps manual entry available', async ({
        page,
    }) => {
        await page.addInitScript(() => {
            Object.defineProperty(navigator, 'mediaDevices', {
                configurable: true,
                value: undefined,
            });
        });

        await loginAs(page, 'requester@e2e.test');
        await page.goto('/inventory/bookings');

        await page.getByTestId('booking-scan-button').click();
        await expect(page.getByText('Camera not supported')).toBeVisible();
        await expect(
            page.getByText(
                'This browser does not support camera scanning. Enter the code manually.',
            ),
        ).toBeVisible();
        await expect(page.getByTestId('booking-asset-select')).toBeVisible();
    });

    test('scanner reports insecure contexts before requesting camera access', async ({
        page,
    }) => {
        await page.addInitScript(() => {
            Object.defineProperty(window, 'isSecureContext', {
                configurable: true,
                value: false,
            });
        });

        await loginAs(page, 'requester@e2e.test');
        await page.goto('/inventory/bookings');

        await page.getByTestId('booking-scan-button').click();
        await expect(
            page.getByText(
                'Camera scanning requires a secure HTTPS connection. Enter the code manually.',
            ),
        ).toBeVisible();
        await expect(page.getByTestId('booking-asset-select')).toBeVisible();
    });

    test('forecasting exposes mobile cards and registers the PWA shell', async ({
        page,
    }) => {
        await loginAs(page, 'admin@e2e.test');

        await page.goto('/inventory/forecasting');
        await expect(page.getByTestId('forecast-mobile-cards')).toBeVisible();

        const manifestResponse = await page.request.get(
            '/manifest.webmanifest',
        );
        expect(manifestResponse.ok()).toBeTruthy();

        await page.goto('/dashboard');
        await page.waitForFunction(async () => {
            if (!('serviceWorker' in navigator)) {
                return false;
            }

            const registrations =
                await navigator.serviceWorker.getRegistrations();

            return registrations.length > 0;
        });
    });
});
