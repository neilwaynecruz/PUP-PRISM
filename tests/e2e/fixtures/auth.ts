import type { Page } from '@playwright/test';
import { confirmTwoFactor, verifyEmail } from '../helpers/db';

type LoginOptions = {
    skipPrivilegedTwoFactorAutoconfirm?: boolean;
    viaUi?: boolean;
    redirectTo?: string | false;
};

async function readXsrfToken(page: Page): Promise<string> {
    const cookies = await page.context().cookies();
    const xsrfCookie = cookies.find((cookie) => cookie.name === 'XSRF-TOKEN');

    if (!xsrfCookie?.value) {
        throw new Error('Missing XSRF-TOKEN cookie after requesting /login.');
    }

    return decodeURIComponent(xsrfCookie.value);
}

async function loginViaUi(
    page: Page,
    email: string,
    password: string,
): Promise<void> {
    await page.goto('/login');
    await page.getByTestId('login-page').waitFor();
    await page.getByTestId('login-email-input').fill(email);
    await page.getByTestId('login-password-input').fill(password);

    await Promise.all([
        page.waitForURL(
            /\/(dashboard|user\/confirm-password|two-factor-challenge)$/,
            {
                waitUntil: 'domcontentloaded',
            },
        ),
        page.getByTestId('login-button').click(),
    ]);

    if (page.url().includes('/two-factor-challenge')) {
        await page.getByRole('button', { name: /recovery code/i }).click();
        await page
            .locator('input[name="recovery_code"]')
            .fill('e2e-recovery-code');

        await Promise.all([
            page.waitForURL(/\/(dashboard|user\/confirm-password)$/, {
                waitUntil: 'domcontentloaded',
            }),
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
                { waitUntil: 'domcontentloaded' },
            ),
            page.getByRole('button', { name: 'Confirm password' }).click(),
        ]);
    }
}

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

    if (options.viaUi || options.skipPrivilegedTwoFactorAutoconfirm) {
        await loginViaUi(page, email, password);

        return;
    }

    const request = page.context().request;
    const loginPageResponse = await request.get('/login');

    if (!loginPageResponse.ok()) {
        throw new Error(
            `Failed to load /login session bootstrap (${loginPageResponse.status()}).`,
        );
    }

    let response = await request.post('/login', {
        maxRedirects: 0,
        headers: {
            Accept: 'text/html,application/xhtml+xml',
            'X-XSRF-TOKEN': await readXsrfToken(page),
        },
        form: {
            email,
            password,
        },
    });
    let location = response.headers()['location'] ?? '';

    if (location.includes('/two-factor-challenge')) {
        response = await request.post('/two-factor-challenge', {
            maxRedirects: 0,
            headers: {
                Accept: 'text/html,application/xhtml+xml',
                'X-XSRF-TOKEN': await readXsrfToken(page),
            },
            form: {
                recovery_code: 'e2e-recovery-code',
            },
        });
        location = response.headers()['location'] ?? '';
    }

    if (location.includes('/user/confirm-password')) {
        response = await request.post('/user/confirm-password', {
            maxRedirects: 0,
            headers: {
                Accept: 'text/html,application/xhtml+xml',
                'X-XSRF-TOKEN': await readXsrfToken(page),
            },
            form: {
                password,
            },
        });
        location = response.headers()['location'] ?? '';
    }

    if (
        response.status() >= 400 ||
        location.includes('/login') ||
        location.includes('/two-factor-challenge')
    ) {
        await page.context().clearCookies();
        await loginViaUi(page, email, password);

        return;
    }

    if (options.redirectTo !== false) {
        await page.goto(options.redirectTo ?? '/dashboard');
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
    await page
        .getByTestId('register-password-confirmation-input')
        .fill(password);

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
    await page.getByTestId('login-page').waitFor();
}
