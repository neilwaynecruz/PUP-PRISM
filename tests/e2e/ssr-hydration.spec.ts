import { existsSync, readFileSync } from 'fs';
import { resolve } from 'path';
import type { Page } from '@playwright/test';
import { loginAs } from './fixtures/auth';
import { expect, test } from './fixtures/test';

/*
 * The isolated E2E server does not server-render pages, but local development does
 * (through the Vite dev server). These tests render pages with that same SSR endpoint,
 * serve the result to a dark-mode browser, and fail on any hydration mismatch, because a
 * mismatch corrupts Vue's DOM bookkeeping and later Inertia visits stop rendering.
 */
const hotFile = resolve(process.cwd(), 'public', 'hot');

test.use({ colorScheme: 'dark', serviceWorkers: 'block' });

test.skip(
    ({ browserName }) => browserName !== 'chromium',
    'Hydration checks rely on Vue development warnings; one engine is sufficient.',
);
test.skip(
    () => !existsSync(hotFile),
    'Requires the Vite dev server (public/hot), which provides the SSR endpoint.',
);

async function serveServerRenderedPage(
    page: Page,
    path: string,
): Promise<void> {
    const hotUrl = readFileSync(hotFile, 'utf8').trim();

    // Chrome blocks a 127.0.0.1 page from loading scripts from the localhost dev server,
    // so dev-server requests are fetched by Playwright and handed to the page directly.
    await page.route(`${hotUrl}/**`, async (route) => {
        const response = await route.fetch();
        await route.fulfill({
            response,
            headers: {
                ...response.headers(),
                'access-control-allow-origin': '*',
            },
        });
    });

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

        const ssr = await page.request.post(`${hotUrl}/__inertia_ssr`, {
            data: JSON.parse(pageJson as string),
        });
        expect(
            ssr.ok(),
            'The dev SSR endpoint should render the page',
        ).toBeTruthy();

        const { body } = (await ssr.json()) as { body: string };
        // Load the development client from the Vite dev server, as local development does,
        // so hydration runs against the same source as the SSR render and reports details.
        const developmentHtml = html
            .replace(/<script[^>]*data-page="app"[^>]*>[\s\S]*?<\/script>/, '')
            .replace(
                /<script type="module" src="[^"]*\/build\/assets\/[^"]+"><\/script>/g,
                '',
            )
            .replace(
                /<link rel="(?:modulepreload|stylesheet|preload)"[^>]*\/build\/assets\/[^>]*>/g,
                '',
            )
            .replace(
                '</head>',
                `<script type="module" src="${hotUrl}/@vite/client"></script><script type="module" src="${hotUrl}/resources/js/app.ts"></script></head>`,
            )
            .replace(/<div id="app"><\/div>/, body);

        await route.fulfill({ response, body: developmentHtml });
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
        await loginAs(page, 'admin@e2e.test');
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
        await page.waitForLoadState('networkidle');

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
