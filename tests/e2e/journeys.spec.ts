import { expect, test, type Page } from '@playwright/test';
import { e2ePassword, logIn, publishPage, uniqueId } from './support';

/** Mail sink of the local stack (Mailpit API). */
const mailpitUrl = process.env.E2E_MAILPIT_URL ?? 'http://localhost:8025';

function contactForm(page: Page) {
    return page
        .locator('form')
        .filter({ has: page.getByRole('button', { name: 'Send message' }) });
}

test.describe('public website', () => {
    test('home page is server-rendered with SEO meta, contact form and language switcher', async ({
        page,
        request,
    }) => {
        const response = await request.get('/');
        expect(response.status()).toBe(200);

        // First HTML, before any JavaScript runs.
        const html = await response.text();
        expect(html).toMatch(/<title[^>]*>[^<]*Punkt Startowy[^<]*<\/title>/);
        expect(html).toMatch(/<meta name="description" content="[^"]+"/);
        expect(html).toMatch(/<link rel="canonical" href="[^"]+"/);
        expect(html).toContain('Websites and apps that work for your business');
        expect(html).toContain('Send message');
        expect(html).toContain('aria-label="Select language: English"');

        await page.goto('/');
        await expect(page).toHaveTitle(/Punkt Startowy/);
        await expect(page.locator('html')).toHaveAttribute('lang', 'en');

        const contact = contactForm(page);
        await expect(contact.getByLabel('Name')).toBeVisible();
        await expect(contact.getByLabel('Email')).toBeVisible();
        await expect(contact.getByLabel('Message')).toBeVisible();

        await page.getByRole('button', { name: 'Select language' }).click();
        await page.getByRole('menuitem', { name: /Polski/ }).click();
        await expect(page).toHaveURL(/\/pl$/);
        await expect(page.locator('html')).toHaveAttribute('lang', 'pl');
        await expect(
            page.getByRole('button', { name: 'Wyślij wiadomość' }),
        ).toBeVisible();
    });

    test('contact form submission shows the success message and is delivered to the mail sink', async ({
        page,
        request,
    }) => {
        const token = `e2e${uniqueId().replace(/[^a-z0-9]/g, '')}`;

        await page.goto('/');
        // The server silently discards submissions faster than
        // contact.min_fill_seconds (3 s), so delivery proves the guard passed.
        const renderedAt = Date.now();

        const contact = contactForm(page);
        await contact.getByLabel('Name').fill('E2E Visitor');
        await contact
            .getByLabel('Email')
            .fill(`e2e-contact-${uniqueId()}@example.test`);
        await contact
            .getByLabel('Message')
            .fill(`Synthetic message ${token} sent by the browser E2E suite.`);

        await page.waitForTimeout(
            Math.max(0, 3_500 - (Date.now() - renderedAt)),
        );
        await contact.getByRole('button', { name: 'Send message' }).click();

        await expect(page.getByText('Message received')).toBeVisible();

        await expect
            .poll(
                async () => {
                    const response = await request.get(
                        `${mailpitUrl}/api/v1/search?query=${token}`,
                    );

                    return response.ok()
                        ? (
                              (await response.json()) as {
                                  messages_count: number;
                              }
                          ).messages_count
                        : 0;
                },
                { timeout: 20_000, message: 'contact mail in Mailpit' },
            )
            .toBe(1);
    });

    test('unknown slug renders the 404 page', async ({ page }) => {
        const response = await page.goto(`/e2e-missing-${uniqueId()}`);

        expect(response?.status()).toBe(404);
        await expect(
            page.getByRole('heading', { name: '404 — Page Not Found' }),
        ).toBeVisible();
        await expect(page.locator('meta[name="robots"]')).toHaveAttribute(
            'content',
            /noindex/,
        );
    });
});

test.describe('administration', () => {
    test('admin publishes a page that is served on its public URL', async ({
        page,
        request,
    }) => {
        await logIn(page, 'admin');
        await page
            .getByRole('link', { name: 'Pages', exact: true })
            .first()
            .click();
        await expect(page).toHaveURL(/\/admin\/pages$/);

        const published = await publishPage(page);

        const publicPage = await page.context().newPage();
        const response = await publicPage.goto(`/${published.slug}`);
        expect(response?.status()).toBe(200);
        await expect(
            publicPage.getByRole('heading', {
                name: published.title,
                level: 1,
            }),
        ).toBeVisible();
        await expect(publicPage.getByText(published.body)).toBeVisible();
        await expect(
            publicPage.locator('link[rel="canonical"]'),
        ).toHaveAttribute('href', new RegExp(`/${published.slug}$`));

        // Guests get the same content in the first, server-rendered HTML.
        const html = await (await request.get(`/${published.slug}`)).text();
        expect(html).toContain(published.body);
        expect(html).toMatch(
            new RegExp(`<link rel="canonical" href="[^"]*/${published.slug}"`),
        );
    });

    test('editor has no audit or user management and gets 403 on the audit log', async ({
        page,
    }) => {
        await logIn(page, 'editor');

        const navigation = page.getByRole('navigation').first();
        await expect(
            page.getByRole('link', { name: 'Pages' }).first(),
        ).toBeVisible();
        await expect(page.getByRole('link', { name: 'Audit log' })).toHaveCount(
            0,
        );
        await expect(page.getByRole('link', { name: 'Users' })).toHaveCount(0);
        await expect(navigation).toBeVisible();

        const response = await page.goto('/admin/audit');
        expect(response?.status()).toBe(403);
        await expect(page.getByRole('heading', { level: 1 })).toContainText(
            '403',
        );
    });

    test('password confirmation dialog loads on the first 423 and resumes the visit', async ({
        page,
    }) => {
        const dialogChunks: string[] = [];
        page.on('request', (request) => {
            if (request.url().includes('password-confirmation-dialog')) {
                dialogChunks.push(request.url());
            }
        });

        await logIn(page, 'editor');
        await page.goto('/settings/profile');
        expect(dialogChunks).toHaveLength(0);

        await page.getByRole('link', { name: 'Security' }).first().click();

        const dialog = page.getByRole('dialog', { name: 'Confirm password' });
        await expect(dialog).toBeVisible();
        expect(dialogChunks).toHaveLength(1);
        await expect(
            dialog.getByRole('textbox', { name: 'Password' }),
        ).toBeFocused();

        await dialog
            .getByRole('textbox', { name: 'Password' })
            .fill('not-the-password');
        await dialog.getByRole('button', { name: 'Confirm password' }).click();
        await expect(dialog.getByText(/incorrect/i)).toBeVisible();
        await expect(page).toHaveURL(/\/settings\/profile$/);

        await dialog
            .getByRole('textbox', { name: 'Password' })
            .fill(e2ePassword());
        await dialog.getByRole('button', { name: 'Confirm password' }).click();
        await expect(dialog).toBeHidden();
        await expect(page).toHaveURL(/\/settings\/security$/);
    });
});
