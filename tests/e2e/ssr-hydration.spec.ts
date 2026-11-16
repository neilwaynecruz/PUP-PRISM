import type { ChildProcess } from 'child_process';
import { spawn } from 'child_process';
import { existsSync } from 'fs';
import type { Page } from '@playwright/test';
import { loginAs } from './fixtures/auth';
import { expect, test } from './fixtures/test';
import { e2eSsrBundlePath } from './server-environment';

/*
 * The isolated E2E app server sets INERTIA_SSR_ENABLED=false for fast test runs.
 * These tests render pages through the built Inertia SSR bundle (`bootstrap/ssr/app.js`),
 * serve the SSR markup to Chromium, Firefox, and WebKit in dark mode, and fail on any
 * hydration mismatch because mismatches corrupt Vue's DOM bookkeeping on subsequent visits.
 */
const ssrServerUrl = 'http://127.0.0.1:13714';

test.use({ colorScheme: 'dark', serviceWorkers: 'block' });

let ssrProcess: ChildProcess | null = null;
let startedSsrServer = false;

async function isSsrServerHealthy(): Promise<boolean> {
    try {
        const response = await fetch(`${ssrServerUrl}/health`, {
            signal: AbortSignal.timeout(1_000),
        });

        return response.ok;
    } catch {
        return false;
    }
}

test.beforeAll(async () => {
    if (await isSsrServerHealthy()) {
        return;
    }

    if (!existsSync(e2eSsrBundlePath)) {
        throw new Error(
            'The Inertia SSR bundle is missing. Run `npx vite build --mode=e2e --ssr` before running SSR hydration tests.',
        );
    }

    ssrProcess = spawn(process.execPath, [e2eSsrBundlePath], {
        stdio: 'ignore',
        windowsHide: true,
    });
    startedSsrServer = true;

    const deadline = Date.now() + 15_000;

    while (Date.now() < deadline) {
        if (await isSsrServerHealthy()) {
            return;
        }

        await new Promise((resolvePromise) => setTimeout(resolvePromise, 100));
    }

    ssrProcess.kill();
    ssrProcess = null;
    startedSsrServer = false;

    throw new Error('The Inertia SSR server did not become healthy in time.');
});

test.afterAll(async () => {
    if (!startedSsrServer) {
        return;
    }

    await fetch(`${ssrServerUrl}/shutdown`).catch(() => {});
    ssrProcess?.kill();
    ssrProcess = null;
    startedSsrServer = false;
});

async function serveServerRenderedPage(
    page: Page,
    path: string,
): Promise<void> {
    await page.route(`**${path}`, async (route) => {
        if (route.request().resourceType() !== 'document') {
            await route.continue();

            return;
        }

        const response = await route.fetch();
        const html = await response.text();
        const pageJson = html.match(
            /<script[^>]*data-page="app"[^>]*>([\s\S]*?)<\/script>/,
        )?.[1];

        expect(
            pageJson,
            'Inertia page data should be embedded in the document',
        ).toBeTruthy();

        const ssr = await page.request.post(`${ssrServerUrl}/render`, {
            data: JSON.parse(pageJson as string),
        });
        expect(
            ssr.ok(),
            'The built SSR server should render the page',
        ).toBeTruthy();

        const { body } = (await ssr.json()) as { body: string };
        expect(body).toContain('data-server-rendered');

        const ssrHtml = html
            .replace(/<script[^>]*data-page="app"[^>]*>[\s\S]*?<\/script>/, '')
            .replace(/<div id="app"><\/div>/, body);

        await route.fulfill({ response, body: ssrHtml });
    });
}

function collectHydrationProblems(page: Page): string[] {
    const problems: string[] = [];

    page.on('console', (message) => {
        const text = message.text();

        if (
            /Hydration|Unhandled error during execution|insertBefore|__vnode/.test(
                text,
            )
        ) {
            problems.push(text.replace(/\s+/g, ' ').slice(0, 400));
        }
    });
    page.on('pageerror', (error) =>
        problems.push(`pageerror: ${error.message}`),
    );

    return problems;
}

for (const appearance of ['system', 'dark', 'light'] as const) {
    test(`settings pages hydrate cleanly with the ${appearance} theme on a dark OS and keep navigating`, async ({
        page,
        context,
    }) => {
        await loginAs(page, 'admin@e2e.test', 'password', {
            redirectTo: false,
        });
        await context.addCookies([
            {
                name: 'appearance',
                value: appearance,
                url: 'http://127.0.0.1:8001',
            },
        ]);
        await page.addInitScript(
            (value) => localStorage.setItem('appearance', value),
            appearance,
        );
        const problems = collectHydrationProblems(page);

        await serveServerRenderedPage(page, '/settings/profile');
        await page.goto('/settings/profile');
        await expect(page.locator('#app[data-server-rendered]')).toHaveCount(1);
        // Guard against a vacuous pass: the client must actually boot and hydrate.
        await expect
            .poll(() =>
                page.evaluate(
                    () =>
                        '__vue_app__' in (document.getElementById('app') ?? {}),
                ),
            )
            .toBe(true);

        const settingsNav = page.getByRole('navigation', { name: 'Settings' });
        await settingsNav.getByRole('link', { name: 'Appearance' }).click();
        await expect(page).toHaveURL(/\/settings\/appearance$/);
        await expect(
            page
                .getByRole('main')
                .getByRole('group', { name: 'Appearance theme' })
                .getByRole('button', { name: 'System' }),
        ).toBeVisible();

        await settingsNav.getByRole('link', { name: 'Security' }).click();
        await expect(page).toHaveURL(
            /\/(settings\/security|user\/confirm-password)$/,
        );

        expect(problems).toEqual([]);
    });
}
