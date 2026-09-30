import { devices, expect, test, type Page } from '@playwright/test';
import { publishPage, signedInPage } from './support';

/**
 * Lab web vitals of public pages on an emulated mid-range phone (Moto G4
 * viewport, 4x CPU slowdown, DevTools "Slow 4G": 562.5 ms RTT, ~1.4 Mbps
 * down), cold cache, production build with SSR. Targets from
 * docs/foundation/05: LCP ≤ 2.5 s, CLS ≤ 0.1 (lab approximation of field
 * p75). Each URL is loaded RUNS times and the median is compared.
 */
const LCP_BUDGET_MS = 2_500;
const CLS_BUDGET = 0.1;
const RUNS = 3;

const SLOW_4G = {
    offline: false,
    latency: 562.5,
    downloadThroughput: (1.44 * 1024 * 1024) / 8,
    uploadThroughput: (675 * 1024) / 8,
};

type Vitals = { lcp: number; cls: number };

declare global {
    interface Window {
        __e2eVitals?: {
            lcp: number;
            shifts: { value: number; startTime: number }[];
        };
    }
}

/** Collects LCP and layout shifts from the first byte (buffered observers). */
function observeVitals(): void {
    const vitals = {
        lcp: 0,
        shifts: [] as { value: number; startTime: number }[],
    };
    window.__e2eVitals = vitals;

    new PerformanceObserver((list) => {
        for (const entry of list.getEntries()) {
            vitals.lcp = entry.startTime;
        }
    }).observe({ type: 'largest-contentful-paint', buffered: true });

    new PerformanceObserver((list) => {
        for (const entry of list.getEntries() as (PerformanceEntry & {
            value: number;
            hadRecentInput: boolean;
        })[]) {
            if (!entry.hadRecentInput) {
                vitals.shifts.push({
                    value: entry.value,
                    startTime: entry.startTime,
                });
            }
        }
    }).observe({ type: 'layout-shift', buffered: true });
}

/** CLS as the largest session window (gap < 1 s, window ≤ 5 s). */
function cumulativeLayoutShift(
    shifts: { value: number; startTime: number }[],
): number {
    let largest = 0;
    let current = 0;
    let windowStart = 0;
    let previous = 0;

    for (const shift of shifts) {
        if (
            current > 0 &&
            (shift.startTime - previous >= 1_000 ||
                shift.startTime - windowStart >= 5_000)
        ) {
            current = 0;
        }

        if (current === 0) {
            windowStart = shift.startTime;
        }

        current += shift.value;
        previous = shift.startTime;
        largest = Math.max(largest, current);
    }

    return largest;
}

async function measure(page: Page, path: string): Promise<Vitals> {
    const cdp = await page.context().newCDPSession(page);
    await cdp.send('Network.enable');
    await cdp.send('Network.setCacheDisabled', { cacheDisabled: true });
    await cdp.send('Network.emulateNetworkConditions', SLOW_4G);
    await cdp.send('Emulation.setCPUThrottlingRate', { rate: 4 });

    await page.addInitScript(observeVitals);
    await page.goto(path, { waitUntil: 'load' });
    await page.waitForLoadState('networkidle');
    // Let hydration settle so late layout shifts are counted.
    await page.waitForTimeout(1_000);

    const raw = await page.evaluate(() => window.__e2eVitals);
    await cdp.detach();

    expect(raw, 'PerformanceObserver did not report').toBeDefined();

    return {
        lcp: Math.round(raw!.lcp),
        cls: Math.round(cumulativeLayoutShift(raw!.shifts) * 1_000) / 1_000,
    };
}

function median(values: number[]): number {
    const sorted = [...values].sort((a, b) => a - b);

    return sorted[Math.floor(sorted.length / 2)];
}

test.describe('web vitals (mobile, Slow 4G, 4x CPU)', () => {
    let contentPath = '';

    test.beforeAll(async ({ browser }) => {
        const page = await signedInPage(browser, 'admin');
        contentPath = `/${(await publishPage(page)).slug}`;
        await page.close();
    });

    for (const target of ['home', 'content page'] as const) {
        test(`${target} meets LCP and CLS budgets`, async ({
            browser,
        }, testInfo) => {
            test.setTimeout(180_000);
            const path = target === 'home' ? '/' : contentPath;
            const runs: Vitals[] = [];

            for (let run = 0; run < RUNS; run++) {
                const { defaultBrowserType: _browserType, ...phone } =
                    devices['Moto G4'];
                const context = await browser.newContext({
                    ...phone,
                    baseURL: testInfo.project.use.baseURL,
                });
                runs.push(await measure(await context.newPage(), path));
                await context.close();
            }

            const result = {
                path,
                lcpMs: median(runs.map((run) => run.lcp)),
                cls: median(runs.map((run) => run.cls)),
                runs,
            };

            console.log(`web-vitals ${target}: ${JSON.stringify(result)}`);
            testInfo.annotations.push({
                type: 'web-vitals',
                description: JSON.stringify(result),
            });
            await testInfo.attach('web-vitals.json', {
                body: JSON.stringify(result, null, 2),
                contentType: 'application/json',
            });

            expect(result.lcpMs, 'LCP (ms, median)').toBeLessThanOrEqual(
                LCP_BUDGET_MS,
            );
            expect(result.cls, 'CLS (median)').toBeLessThanOrEqual(CLS_BUDGET);
        });
    }
});
