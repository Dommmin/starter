import { expect, type Browser, type Page } from '@playwright/test';

/** Synthetic accounts prepared by `php artisan app:e2e-prepare` (config/e2e.php). */
export const accounts = {
    admin: 'e2e-admin@example.test',
    editor: 'e2e-editor@example.test',
} as const;

export function e2ePassword(): string {
    const password = process.env.E2E_PASSWORD;

    if (!password) {
        throw new Error(
            'E2E_PASSWORD is not set. Run the suite with `make e2e`, which prepares the accounts.',
        );
    }

    return password;
}

/** Unique, run-scoped identifier; slugs keep the `e2e-` prefix cleaned up by app:e2e-prepare. */
export function uniqueId(): string {
    return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`;
}

/**
 * Signed-in browser state saved once per run by `auth.setup.ts`, so the suite
 * stays well below Fortify's 5 logins per minute for an account and IP.
 * The directory is git-ignored.
 */
export function authStatePath(account: keyof typeof accounts): string {
    return `playwright/.auth/${account}.json`;
}

/**
 * Opens the admin panel in a new context restored from the saved session.
 * Closing the page closes its context.
 */
export async function signedInPage(
    browser: Browser,
    account: keyof typeof accounts,
): Promise<Page> {
    // A dedicated context (not browser.newPage) so a journey can open more
    // tabs in the same signed-in session through page.context().newPage().
    const context = await browser.newContext({
        storageState: authStatePath(account),
    });
    const page = await context.newPage();
    await page.goto('/admin');
    await expect(page).toHaveURL(/\/admin$/);

    return page;
}

export async function logIn(
    page: Page,
    account: keyof typeof accounts,
): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('Email address').fill(accounts[account]);
    // The required mark (*) is aria-hidden but still part of the label text
    // Playwright matches; anchor the name so "Show password" never matches.
    await page.getByLabel(/^Password\*?$/).fill(e2ePassword());
    await page.getByRole('button', { name: 'Log in', exact: true }).click();
    // Signing in lands on the home page; the panel is a separate visit.
    await expect(page).toHaveURL(/\/$/);
    await page.goto('/admin');
    await expect(page).toHaveURL(/\/admin$/);
}

export type PublishedPage = { title: string; slug: string; body: string };

/** Creates and publishes an English page through the admin UI. */
export async function publishPage(page: Page): Promise<PublishedPage> {
    const id = uniqueId();
    const published: PublishedPage = {
        title: `E2E page ${id}`,
        slug: `e2e-page-${id}`,
        body: `Synthetic body of the E2E page ${id}.`,
    };

    await page.goto('/admin/pages/create');
    await expect(
        page.getByRole('heading', { name: 'New page', level: 1 }),
    ).toBeVisible();

    await page.getByLabel('Title').fill(published.title);
    await expect(page.getByLabel('Slug')).toHaveValue(published.slug);
    await page.getByRole('textbox', { name: 'Content' }).fill(published.body);
    await page.getByRole('combobox', { name: 'Status' }).click();
    await page.getByRole('option', { name: 'Published' }).click();
    await page.getByRole('button', { name: 'Save changes' }).click();

    await expect(page).toHaveURL(/\/admin\/pages\/\d+\/edit$/);
    await expect(page.getByText('Page created.')).toBeVisible();

    return published;
}
