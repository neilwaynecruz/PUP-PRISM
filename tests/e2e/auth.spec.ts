import { loginAs, logout } from './fixtures/auth';
import { expect, test } from './fixtures/test';

const TEST_USER = {
    email: 'admin@e2e.test',
    password: 'password',
};

test.describe('Authentication Flow', () => {
    test('Login -> Dashboard -> Logout -> Login', async ({ page }) => {
        await loginAs(page, TEST_USER.email, TEST_USER.password);
        await expect(page).toHaveURL(/\/dashboard$/);
        await expect(page.getByTestId('dashboard-page')).toBeVisible();

        await logout(page);

        await loginAs(page, TEST_USER.email, TEST_USER.password);
        await expect(page).toHaveURL(/\/dashboard$/);
        await expect(page.getByTestId('dashboard-page')).toBeVisible();
    });

    test('back button after logout does not restore protected dashboard content', async ({
        page,
    }) => {
        await loginAs(page, TEST_USER.email, TEST_USER.password);
        await expect(page.getByTestId('dashboard-page')).toBeVisible();

        await page.getByTestId('sidebar-menu-button').click();
        await page.getByTestId('logout-button').click();
        await page.waitForURL(/\/login$/);
        await expect(page.getByTestId('login-page')).toBeVisible();

        await page.goBack();

        await expect(page).toHaveURL(/\/login$/);
        await expect(page.getByTestId('login-page')).toBeVisible();
        await expect(page.getByTestId('dashboard-page')).toHaveCount(0);
    });
});
