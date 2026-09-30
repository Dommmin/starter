import { expect, test, type ConsoleMessage, type Page } from '@playwright/test';
import { publishPage, signedInPage, uniqueId } from './support';

/**
 * React reports a server/client mismatch as a (minified in production)
 * recoverable error: #418 hydration failed, #419/#422 fallback to client
 * rendering, #423 recoverable error, #425 text did not match.
 */
const hydrationProblem =
    /hydrat|did not match|recoverable|Minified React error #(418|419|422|423|425)\b/i;

/**
 * Opens a public URL in a guest page and returns the hydration problems
 * reported in the console or as uncaught errors while the server-rendered
 * HTML was hydrated.
 */
async function hydrationProblems(page: Page, url: string): Promise<string[]> {
    const problems: string[] = [];
    const onConsole = (message: ConsoleMessage) => {
        if (
            ['error', 'warning'].includes(message.type()) &&
            hydrationProblem.test(message.text())
        ) {
            problems.push(
                `${url} console.${message.type()}: ${message.text()}`,
            );
        }
    };
    const onPageError = (error: Error) => {
        if (hydrationProblem.test(`${error.message}`)) {
            problems.push(`${url} pageerror: ${error.message}`);
        }
    };

    page.on('console', onConsole);
    page.on('pageerror', onPageError);

    try {
        const response = await page.goto(url);

        // Server-rendered markup: the page content is in the first HTML.
        expect(await response?.text()).toContain('id="main-content"');

        // Hydration has finished once React owns the Inertia root.
        await page.waitForFunction(() => {
            const root = document.getElementById('app');

            return (
                root !== null &&
                Object.keys(root).some((key) =>
                    key.startsWith('__reactContainer'),
                )
            );
        });
        await page.waitForLoadState('networkidle');
    } finally {
        page.off('console', onConsole);
        page.off('pageerror', onPageError);
    }

    return problems;
}

test.describe('server-side rendering', () => {
    test('public pages hydrate without mismatches in every public locale', async ({
        browser,
    }) => {
        // Content that exists only after publishing: a CMS page and an
        // article (without a cover), created through the admin UI.
        const admin = await signedInPage(browser, 'admin');
        const cmsPage = await publishPage(admin);
        const articleSlug = `e2e-article-${uniqueId()}`;

        await admin.goto('/admin/articles');
        await admin.getByRole('link', { name: 'New article' }).first().click();
        const panel = admin.getByRole('tabpanel');
        await panel.getByLabel('Title').fill(articleSlug);
        await expect(panel.getByLabel('Slug')).toHaveValue(articleSlug);
        await panel
            .getByRole('textbox', { name: 'Content' })
            .fill('Synthetic body of the hydration check.');
        await panel.getByRole('combobox', { name: 'Status' }).click();
        await admin.getByRole('option', { name: 'Published' }).click();
        await panel.getByRole('button', { name: 'Save changes' }).click();
        await expect(admin).toHaveURL(/\/admin\/articles\/\d+\/edit$/);
        await admin.close();

        const urls = [
            '/',
            '/pl',
            '/de',
            '/articles',
            '/pl/articles',
            '/articles?page=2',
            `/articles/${articleSlug}`,
            `/${cmsPage.slug}`,
            `/e2e-missing-${uniqueId()}`,
        ];

        const visitor = await browser.newPage();
        const problems: string[] = [];

        for (const url of urls) {
            problems.push(...(await hydrationProblems(visitor, url)));
        }

        await visitor.close();

        expect(problems).toEqual([]);
    });
});
