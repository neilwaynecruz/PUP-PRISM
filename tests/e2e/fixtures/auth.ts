import type { Page } from '@playwright/test';
import { confirmTwoFactor, verifyEmail } from '../helpers/db';

type LoginOptions = {
    skipPrivilegedTwoFactorAutoconfirm?: boolean;
};

export async function loginAs(
    page: Page,
    email: string,
    password: string = 'password',
    options: LoginOptions = {},
): Promise<void> {
    if (
        !options.skipPrivilegedTwoFactorAutoconfirm &&
        ['admin@e2e.test', 'supply@e2e.test'].includes(email)
    ) {
        confirmTwoFactor(email);
    }

    await page.context().clearCookies();
    await page.goto('/login');
    await page.getByTestId('login-page').waitFor();
    await page.getByTestId('login-email-input').fill(email);
    await page.getByTestId('login-password-input').fill(password);

    await Promise.all([
        page.waitForURL(/\/(dashboard|user\/confirm-password|two-factor-challenge)$/),
        page.getByTestId('login-button').click(),
    ]);

    if (page.url().includes('/two-factor-challenge')) {
        await page.getByRole('button', { name: /recovery code/i }).click();
        await page.locator('input[name="recovery_code"]').fill(
            'e2e-recovery-code',
        );

        await Promise.all([
            page.waitForURL(/\/(dashboard|user\/confirm-password)$/),
            page.getByRole('button', { name: 'Continue' }).click(),
        ]);
    }

    if (page.url().includes('/user/confirm-password')) {
        await page.locator('input[name="password"]').fill(password);

        await Promise.all([
            page.waitForURL(
                (url) =>
                    !url.pathname.endsWith('/user/confirm-password') &&
                    !url.pathname.endsWith('/login'),
            ),
            page.getByRole('button', { name: 'Confirm password' }).click(),
        ]);
    }
}

export async function registerUser(
    page: Page,
    name: string,
    email: string,
    password: string = 'password',
): Promise<void> {
    await page.goto('/register');
    await page.getByTestId('register-page').waitFor();
    await page.getByTestId('register-name-input').fill(name);
    await page.getByTestId('register-email-input').fill(email);
    await page.getByTestId('register-password-input').fill(password);
    await page.getByTestId('register-password-confirmation-input').fill(password);

    await Promise.all([
        page.waitForURL(/\/email\/verify$/),
        page.getByTestId('register-user-button').click(),
    ]);
}

export function bypassEmailVerification(email: string): void {
    verifyEmail(email);
}

export async function logout(page: Page): Promise<void> {
    await page.getByTestId('sidebar-menu-button').click();
    await page.getByTestId('logout-button').click();
    await page.waitForURL(/\/login$/);
    await page.waitForLoadState('networkidle');
}
