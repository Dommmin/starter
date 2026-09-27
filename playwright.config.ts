import { defineConfig, devices } from '@playwright/test';

/**
 * Browser E2E suite against the local Docker stack (`make e2e`): production
 * build + SSR server behind nginx. Inside the Playwright container the app is
 * reachable as http://web; a host run (browsers installed on the host) uses
 * APP_PORT on localhost.
 */
const baseURL = process.env.E2E_BASE_URL ?? 'http://localhost:8080';
const isCi = Boolean(process.env.CI);

export default defineConfig({
    testDir: 'tests/e2e',
    // Journeys are independent (unique slugs, own sessions); one worker keeps
    // the web-vitals measurement free of CPU contention.
    fullyParallel: false,
    workers: 1,
    forbidOnly: isCi,
    // At most one diagnostic retry; a test that passes only on retry is
    // reported as flaky and must be fixed (docs/foundation/03).
    retries: isCi ? 1 : 0,
    timeout: 60_000,
    expect: { timeout: 10_000 },
    reporter: [
        ['list'],
        ['html', { open: 'never', outputFolder: 'playwright-report' }],
    ],
    outputDir: 'test-results',
    use: {
        baseURL,
        locale: 'en-US',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'off',
    },
    projects: [
        {
            name: 'chromium',
            testIgnore: /perf\.spec\.ts$/,
            use: { ...devices['Desktop Chrome'] },
        },
        {
            name: 'web-vitals',
            testMatch: /perf\.spec\.ts$/,
            use: { ...devices['Moto G4'] },
        },
    ],
});
