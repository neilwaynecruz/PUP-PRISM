import type { Page } from '@playwright/test';
import { verifyEmail } from '../helpers/db';

export async function loginAs(
    page: Page,
    email: string,
    password: string = 'password',
): Promise<void> {
    await page.context().clearCookies();
    await page.goto('/login');
    await page.getByTestId('login-page').waitFor();
    await page.getByTestId('login-email-input').fill(email);
    await page.getByTestId('login-password-input').fill(password);

    await Promise.all([
        page.waitForURL(/\/dashboard$/),
        page.getByTestId('login-button').click(),
    ]);
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
    await page.request.post('/logout', { failOnStatusCode: false });
    await page.context().clearCookies();
    await page.goto('/login');
    await page.waitForLoadState('networkidle');
}
