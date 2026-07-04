import { beginTwoFactorSetup } from './helpers/db';
import { loginAs } from './fixtures/auth';
import { expect, test } from './fixtures/test';

const REQUESTER_USER = {
    email: 'requester@e2e.test',
    password: 'password',
};

test.describe('Security Settings', () => {
    test('two-factor setup payload loads after enabling two-factor authentication', async ({
        page,
    }) => {
        await loginAs(page, REQUESTER_USER.email, REQUESTER_USER.password);

        await page.goto('/settings/security');

        if (page.url().includes('/user/confirm-password')) {
            await page.locator('input[name="password"]').fill('password');

            await Promise.all([
                page.waitForURL(/\/settings\/security$/),
                page.getByRole('button', { name: 'Confirm password' }).click(),
            ]);
        }

        await expect(page.getByTestId('security-settings-page')).toBeVisible();

        const setupDataResponsePromise = page.waitForResponse(
            (response) =>
                response.url().includes(
                    '/settings/security/two-factor/setup-data',
                ) &&
                response.request().method() === 'GET',
        );

        await page.getByRole('button', { name: 'Enable 2FA' }).click();

        const setupDataResponse = await setupDataResponsePromise;

        expect(setupDataResponse.ok()).toBeTruthy();

        await expect(
            page.getByRole('heading', {
                name: 'Enable two-factor authentication',
            }),
        ).toBeVisible();
        await expect(page.getByText('Failed to fetch a setup key')).toHaveCount(
            0,
        );
        await expect(page.locator('input[readonly]')).toHaveValue(
            /^[A-Z0-9]{16,}$/,
        );
    });

    test('pending two-factor setup can be resumed from the security page', async ({
        page,
    }) => {
        beginTwoFactorSetup(REQUESTER_USER.email);

        await loginAs(page, REQUESTER_USER.email, REQUESTER_USER.password);

        await page.goto('/settings/security');

        if (page.url().includes('/user/confirm-password')) {
            await page.locator('input[name="password"]').fill('password');

            await Promise.all([
                page.waitForURL(/\/settings\/security$/),
                page.getByRole('button', { name: 'Confirm password' }).click(),
            ]);
        }

        const setupDataResponsePromise = page.waitForResponse(
            (response) =>
                response.url().includes(
                    '/settings/security/two-factor/setup-data',
                ) &&
                response.request().method() === 'GET',
        );

        await page
            .getByRole('button', { name: 'Continue setup' })
            .click();

        const setupDataResponse = await setupDataResponsePromise;

        expect(setupDataResponse.ok()).toBeTruthy();

        await expect(
            page.getByRole('heading', {
                name: 'Enable two-factor authentication',
            }),
        ).toBeVisible();
        await expect(page.getByText('Failed to fetch a setup key')).toHaveCount(
            0,
        );
        await expect(page.locator('input[readonly]')).toHaveValue(
            'e2e-secret',
        );
    });

    test('admin users with pending two-factor setup can resume setup and load the QR data', async ({
        page,
    }) => {
        beginTwoFactorSetup('admin@e2e.test');

        await loginAs(page, 'admin@e2e.test', 'password', {
            skipPrivilegedTwoFactorAutoconfirm: true,
        });

        await page.goto('/settings/security');

        if (page.url().includes('/user/confirm-password')) {
            await page.locator('input[name="password"]').fill('password');

            await Promise.all([
                page.waitForURL(/\/settings\/security$/),
                page.getByRole('button', { name: 'Confirm password' }).click(),
            ]);
        }

        await expect(page.getByTestId('security-settings-page')).toBeVisible();

        const setupDataResponsePromise = page.waitForResponse(
            (response) =>
                response.url().includes(
                    '/settings/security/two-factor/setup-data',
                ) &&
                response.request().method() === 'GET',
        );

        await page
            .getByRole('button', { name: 'Continue setup' })
            .click();

        const setupDataResponse = await setupDataResponsePromise;

        expect(setupDataResponse.ok()).toBeTruthy();

        await expect(
            page.getByRole('heading', {
                name: 'Enable two-factor authentication',
            }),
        ).toBeVisible();
        await expect(page.getByText('Failed to load two-factor setup data')).toHaveCount(
            0,
        );
        await expect(page.locator('input[readonly]')).toHaveValue(
            'e2e-secret',
        );
    });
});
