import { registerUser, loginAs, bypassEmailVerification, logout } from './fixtures/auth';
import { expect, test } from './fixtures/test';

const TEST_USER = {
    name: 'E2E Test User',
    email: 'e2e-register-flow@test.local',
    password: 'password',
};

test.describe('Authentication Flow', () => {
    test('Registration -> Email Verification -> Login -> Dashboard', async ({ page }) => {
        await registerUser(page, TEST_USER.name, TEST_USER.email, TEST_USER.password);
        await expect(page).toHaveURL(/\/email\/verify$/);
        await expect(page.getByTestId('email-verification-page')).toBeVisible();

        bypassEmailVerification(TEST_USER.email);

        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/dashboard$/);
        await expect(page.getByTestId('dashboard-page')).toBeVisible();

        await logout(page);

        await loginAs(page, TEST_USER.email, TEST_USER.password);
        await expect(page).toHaveURL(/\/dashboard$/);
        await expect(page.getByTestId('dashboard-page')).toBeVisible();
    });
});
