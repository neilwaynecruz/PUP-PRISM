import type { Page } from '@playwright/test';
import { loginAs } from './fixtures/auth';
import { expect, test } from './fixtures/test';
import { createUnreadNotification } from './helpers/db';

function collectPageErrors(page: Page): string[] {
    const errors: string[] = [];

    page.on('pageerror', (error) => errors.push(error.message));

    return errors;
}

async function openGlobalSearch(page: Page) {
    await page
        .getByRole('button', { name: /^Search/ })
        .first()
        .click();
    const dialog = page.getByRole('dialog', { name: 'Global search' });
    await expect(dialog).toBeVisible();

    return dialog;
}

test.describe('Global search', () => {
    test('opens with the search field focused and without page errors', async ({
        page,
    }) => {
        const errors = collectPageErrors(page);
        await loginAs(page, 'admin@e2e.test');

        await openGlobalSearch(page);

        await expect(page.getByTestId('global-search-input')).toBeFocused();
        expect(errors).toEqual([]);
    });

    test('Ctrl+K opens search even while typing in a page field', async ({
        page,
    }) => {
        await loginAs(page, 'admin@e2e.test');
        await page.goto('/inventory/products');
        await page.locator('main input').first().click();

        await page.keyboard.press('Control+K');

        await expect(
            page.getByRole('dialog', { name: 'Global search' }),
        ).toBeVisible();
        await expect(page.getByTestId('global-search-input')).toBeFocused();
    });

    test('shows loading instead of a false empty state and ignores superseded results', async ({
        page,
        browserName,
    }) => {
        test.skip(
            browserName === 'webkit' && process.platform === 'win32',
            'Playwright request interception does not reach fetch() in this Windows WebKit build, so delayed and failed responses cannot be simulated.',
        );

        await loginAs(page, 'admin@e2e.test');
        await page.goto('/dashboard');

        await page.route('**/search?**', async (route) => {
            await new Promise((resolve) => setTimeout(resolve, 900));
            await route.continue();
        });

        const dialog = await openGlobalSearch(page);
        const input = page.getByTestId('global-search-input');

        await input.pressSequentially('la');
        await expect(dialog.getByTestId('global-search-loading')).toBeVisible();
        await expect(dialog.getByTestId('global-search-empty')).toBeHidden();

        // A newer term supersedes the request that is still in flight.
        await page.waitForTimeout(400);
        await input.pressSequentially('ptop');
        await expect(dialog.getByTestId('global-search-loading')).toBeVisible();
        await expect(dialog.getByTestId('global-search-empty')).toBeHidden();

        await expect(
            dialog.getByRole('option', { name: /AST-E2E-0001/ }),
        ).toBeVisible();
        await expect(dialog.getByTestId('global-search-loading')).toBeHidden();
    });

    test('arrow keys and Enter open the highlighted record', async ({
        page,
    }) => {
        await loginAs(page, 'admin@e2e.test');
        await page.goto('/dashboard');

        const dialog = await openGlobalSearch(page);
        const input = page.getByTestId('global-search-input');
        await input.fill('  ast-e2e-0001  ');
        await expect(
            dialog.getByRole('option', { name: /AST-E2E-0001/ }),
        ).toBeVisible();

        await input.press('ArrowDown');
        await expect(input).toHaveAttribute(
            'aria-activedescendant',
            /global-search-option-entity-/,
        );
        await input.press('Enter');

        await expect(page).toHaveURL(/\/inventory\/(assets|products)\/\d+$/);
        await expect(dialog).toBeHidden();
    });

    test('a failed search explains the problem and can be retried', async ({
        page,
        browserName,
    }) => {
        test.skip(
            browserName === 'webkit' && process.platform === 'win32',
            'Playwright request interception does not reach fetch() in this Windows WebKit build, so delayed and failed responses cannot be simulated.',
        );

        await loginAs(page, 'admin@e2e.test');
        await page.goto('/dashboard');

        // Every search fails until the user asks to try again.
        let serviceDown = true;
        await page.route('**/search?**', async (route) => {
            if (serviceDown) {
                await route.fulfill({ status: 500, body: '{}' });

                return;
            }

            await route.continue();
        });

        const dialog = await openGlobalSearch(page);
        await page.getByTestId('global-search-input').fill('laptop');

        const error = dialog.getByTestId('global-search-error');
        await expect(error).toBeVisible();
        await expect(error).toHaveAttribute('role', 'alert');

        serviceDown = false;
        await error.getByRole('button', { name: 'Try again' }).click();
        await expect(
            dialog.getByRole('option', { name: /Business Laptop/ }).first(),
        ).toBeVisible();
        await expect(error).toBeHidden();
    });

    test('a query with no matches says so only after the search finishes', async ({
        page,
    }) => {
        await loginAs(page, 'admin@e2e.test');
        await page.goto('/dashboard');

        const dialog = await openGlobalSearch(page);
        await page
            .getByTestId('global-search-input')
            .fill('zzqx-no-such-record');

        await expect(dialog.getByTestId('global-search-empty')).toContainText(
            'zzqx-no-such-record',
        );
    });
});

test.describe('Sidebar navigation search', () => {
    test('the filter clears after navigating so the next page shows the full menu', async ({
        page,
    }) => {
        await loginAs(page, 'admin@e2e.test');
        await page.goto('/dashboard');

        const navSearch = page.getByRole('searchbox', {
            name: 'Search navigation',
        });
        await navSearch.fill('suppl');
        await expect(
            page
                .locator('[data-slot="sidebar-content"]')
                .getByRole('link', { name: 'Dashboard' }),
        ).toHaveCount(0);

        await navSearch.press('Enter');
        await expect(page).toHaveURL(/\/inventory\/suppliers$/);

        await expect(navSearch).toHaveValue('');
        await expect(
            page
                .locator('[data-slot="sidebar-content"]')
                .getByRole('link', { name: 'Dashboard' }),
        ).toBeVisible();
    });
});

test.describe('Header and account menus', () => {
    async function expectPageInteractive(page: Page): Promise<void> {
        await expect(page.locator('body')).not.toHaveCSS(
            'pointer-events',
            'none',
        );
        await expect(page.getByLabel('Open notifications')).toHaveAttribute(
            'aria-expanded',
            'false',
        );

        await page
            .getByRole('button', { name: /^Search/ })
            .first()
            .click();
        await expect(
            page.getByRole('dialog', { name: 'Global search' }),
        ).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(
            page.getByRole('dialog', { name: 'Global search' }),
        ).toBeHidden();

        await page.getByTestId('sidebar-menu-button').click();
        await expect(
            page.getByRole('menuitem', { name: 'Log out' }),
        ).toBeVisible();
        await page.keyboard.press('Escape');
    }

    test('navigating from the notification menu link leaves the page clickable', async ({
        page,
    }) => {
        await loginAs(page, 'admin@e2e.test');
        await page.goto('/dashboard');

        await page.getByLabel('Open notifications').click();
        await page
            .getByRole('link', { name: 'View notification history' })
            .click();
        await expect(page).toHaveURL(/\/notifications$/);

        await expectPageInteractive(page);
    });

    test('opening a notification from the menu leaves the page clickable', async ({
        page,
    }) => {
        createUnreadNotification(
            'custodian@e2e.test',
            'Low stock on bond paper',
            '/inventory/products',
        );
        await loginAs(page, 'custodian@e2e.test');
        await page.goto('/dashboard');

        await page.getByLabel('Open notifications').click();
        const firstNotification = page
            .getByTestId('notification-menu-item')
            .filter({ hasText: 'Low stock on bond paper' });
        await expect(firstNotification).toBeVisible();

        await firstNotification.click();
        await expect(page).toHaveURL(/\/inventory\/products$/);
        await expectPageInteractive(page);
    });
});

test.describe('Settings', () => {
    test('theme changes apply immediately and survive a reload', async ({
        page,
    }) => {
        await loginAs(page, 'admin@e2e.test');
        await page.goto('/settings/appearance');

        await page.getByRole('button', { name: 'Dark' }).click();
        await expect(page.locator('html')).toHaveClass(/dark/);
        await expect(
            page.getByRole('button', { name: 'Dark' }),
        ).toHaveAttribute('aria-pressed', 'true');

        await page.reload();
        await expect(page.locator('html')).toHaveClass(/dark/);
        await expect(
            page.getByRole('button', { name: 'Dark' }),
        ).toHaveAttribute('aria-pressed', 'true');

        await page.getByRole('button', { name: 'Light' }).click();
        await expect(page.locator('html')).not.toHaveClass(/dark/);
    });

    test('profile validation keeps input beside the field and valid changes persist', async ({
        page,
    }) => {
        await loginAs(page, 'admin@e2e.test');
        await page.goto('/settings/profile');

        const name = page.locator('#name');
        const email = page.locator('#email');
        const originalEmail = await email.inputValue();

        await name.fill('Adrian M. Mercado');
        await email.fill('not-an-email');
        await email.evaluate((element: HTMLInputElement) =>
            element.setAttribute('type', 'text'),
        );
        await page.locator('[data-test="update-profile-button"]').click();

        await expect(page.getByText(/valid email/i)).toBeVisible();
        await expect(name).toHaveValue('Adrian M. Mercado');

        await email.fill(originalEmail);
        const successfulUpdate = page.waitForResponse(
            (response) =>
                new URL(response.url()).pathname === '/settings/profile' &&
                response.request().method() === 'POST' &&
                new URL(response.url()).searchParams.get('_method') ===
                    'PATCH' &&
                response.status() === 303,
        );
        await page.locator('[data-test="update-profile-button"]').click();
        await successfulUpdate;
        await expect(page.getByText(/valid email/i)).toBeHidden();

        await page.reload({ waitUntil: 'domcontentloaded' });
        await expect(page.locator('#name')).toHaveValue('Adrian M. Mercado');
    });

    test('notification preferences are saved and reloaded', async ({
        page,
    }) => {
        await loginAs(page, 'admin@e2e.test');
        await page.goto('/settings/notifications');

        const firstMail = page
            .getByRole('checkbox', { name: /^Email notifications for/ })
            .first();
        const before = await firstMail.getAttribute('data-state');
        await firstMail.click();
        const after = before === 'checked' ? 'unchecked' : 'checked';
        await expect(firstMail).toHaveAttribute('data-state', after);

        await page.getByRole('button', { name: 'Save preferences' }).click();
        await expect(
            page.getByText('Notification preferences updated.'),
        ).toBeVisible();
        await page.reload({ waitUntil: 'domcontentloaded' });

        await expect(
            page
                .getByRole('checkbox', { name: /^Email notifications for/ })
                .first(),
        ).toHaveAttribute('data-state', after);
    });

    test('every settings link opens its page and marks it active', async ({
        page,
    }) => {
        await loginAs(page, 'admin@e2e.test');
        await page.goto('/settings/profile');

        const settingsNav = page.getByRole('navigation', { name: 'Settings' });

        for (const [label, path] of [
            ['Security', '/settings/security'],
            ['API Tokens', '/settings/api-tokens'],
            ['Appearance', '/settings/appearance'],
            ['Notifications', '/settings/notifications'],
            ['Profile', '/settings/profile'],
        ] as const) {
            await settingsNav.getByRole('link', { name: label }).click();

            // Security settings correctly ask for the password again before opening.
            if (label === 'Security') {
                await expect(page).toHaveURL(/\/user\/confirm-password$/);
                await page.locator('#password').fill('password');
                await page
                    .locator('[data-test="confirm-password-button"]')
                    .click();
            }

            await expect(page).toHaveURL(new RegExp(`${path}$`));
            await expect(
                settingsNav.getByRole('link', { name: label }),
            ).toHaveAttribute('aria-current', 'page');
        }
    });
});

const viewports = [
    { width: 320, height: 720 },
    { width: 360, height: 800 },
    { width: 390, height: 844 },
    { width: 430, height: 932 },
    { width: 844, height: 390 },
    { width: 768, height: 1024 },
    { width: 834, height: 1112 },
    { width: 1024, height: 768 },
    { width: 1280, height: 800 },
    { width: 1440, height: 900 },
];

test.describe('Responsive navigation', () => {
    for (const viewport of viewports) {
        test(`navigation toggle, search, and layout work at ${viewport.width}x${viewport.height}`, async ({
            page,
        }) => {
            const errors = collectPageErrors(page);
            await page.setViewportSize(viewport);
            await loginAs(page, 'admin@e2e.test');
            await page.goto('/inventory/products');

            const trigger = page
                .locator('main [data-sidebar="trigger"]')
                .first();
            await expect(trigger).toBeVisible();

            if (viewport.width < 768) {
                await expect(trigger).toHaveAttribute('aria-expanded', 'false');
                // Keyboard activation: WebKit does not focus buttons on click, so focus
                // restoration is verified for keyboard users, who depend on it.
                await trigger.focus();
                await page.keyboard.press('Enter');
                await expect(trigger).toHaveAttribute('aria-expanded', 'true');
                await expect(
                    page.getByRole('searchbox', { name: 'Search navigation' }),
                ).toBeVisible();

                await page.keyboard.press('Escape');
                await expect(trigger).toHaveAttribute('aria-expanded', 'false');
                await expect(trigger).toBeFocused();
            } else {
                await expect(trigger).toHaveAttribute('aria-expanded', 'true');
                await trigger.click();
                await expect(trigger).toHaveAttribute('aria-expanded', 'false');
                await trigger.click();
                await expect(trigger).toHaveAttribute('aria-expanded', 'true');
            }

            await page.keyboard.press('Control+K');
            await expect(
                page.getByRole('dialog', { name: 'Global search' }),
            ).toBeVisible();
            await page.keyboard.press('Escape');

            const overflow = await page.evaluate(
                () => document.documentElement.scrollWidth - window.innerWidth,
            );
            expect(overflow).toBeLessThanOrEqual(1);
            expect(errors).toEqual([]);
        });
    }
});
