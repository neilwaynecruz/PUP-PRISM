import type { Page } from '@playwright/test';
import { loginAs } from './fixtures/auth';
import { expect, test } from './fixtures/test';

const viewports = [
    { name: 'phone-320', width: 320, height: 720 },
    { name: 'phone-375', width: 375, height: 812 },
    { name: 'tablet-768', width: 768, height: 1024 },
    { name: 'laptop-1024', width: 1024, height: 768 },
    { name: 'desktop-1440', width: 1440, height: 900 },
] as const;

const views = [
    {
        name: 'forecast',
        label: 'Demand Forecasting',
        panel: '#forecast-panel',
    },
    {
        name: 'optimization',
        label: 'Optimization & ABC Analysis',
        panel: '#optimization-panel',
    },
    {
        name: 'expirations',
        label: 'Expiration & FEFO Tracker',
        panel: '#expirations-panel',
    },
] as const;

function monitorUnexpectedBrowserErrors(page: Page): string[] {
    const errors: string[] = [];

    page.on('pageerror', (error: Error) => errors.push(error.message));
    page.on('console', (message) => {
        const text = message.text();
        const isExpectedTestEnvironmentFailure =
            text.includes('net::ERR_NETWORK_ACCESS_DENIED') ||
            (text.includes('WebSocket connection to') &&
                text.includes('localhost:8080'));

        const isHydrationWarning =
            message.type() === 'warning' &&
            text.toLowerCase().includes('hydration');

        if (
            (message.type() === 'error' || isHydrationWarning) &&
            !isExpectedTestEnvironmentFailure
        ) {
            errors.push(`[${message.type()}] ${text}`);
        }
    });

    return errors;
}

test('Phase 5 forecasting views remain accessible and balanced across supported viewports', async ({
    page,
}, testInfo) => {
    test.setTimeout(180_000);
    const browserErrors = monitorUnexpectedBrowserErrors(page);

    await loginAs(page, 'supply@e2e.test');

    for (const appearance of ['light', 'dark'] as const) {
        await page.evaluate((value) => {
            localStorage.setItem('appearance', value);
        }, appearance);

        for (const viewport of viewports) {
            await page.setViewportSize(viewport);
            await page.goto('/inventory/forecasting');
            await expect(
                page.getByRole('heading', {
                    level: 1,
                    name: 'Forecasting & Inventory Optimization',
                }),
            ).toBeVisible();
            await expect(page.getByRole('tab')).toHaveCount(3);

            if (appearance === 'dark') {
                await expect(page.locator('html')).toHaveClass(/dark/);
            } else {
                await expect(page.locator('html')).not.toHaveClass(/dark/);
            }

            for (const view of views) {
                const tab = page.getByRole('tab', { name: view.label });
                await tab.click();
                await expect(tab).toHaveAttribute('aria-selected', 'true');
                await expect(page.locator(view.panel)).toBeVisible();

                const overflow = await page.evaluate(
                    () =>
                        document.documentElement.scrollWidth -
                        document.documentElement.clientWidth,
                );

                expect(
                    overflow,
                    `${view.name} overflows at ${viewport.width}px in ${appearance} mode`,
                ).toBeLessThanOrEqual(1);

                await page.screenshot({
                    path: testInfo.outputPath(
                        `phase5-${view.name}-${viewport.name}-${appearance}.png`,
                    ),
                    fullPage: true,
                });
            }
        }
    }

    expect(browserErrors).toEqual([]);
});
