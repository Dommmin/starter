import { expect, test, type Page } from '@playwright/test';
import { signedInPage, uniqueId } from './support';

type ArticleTranslation = { title: string; slug: string; body: string };

/** English (default, unprefixed) and Polish (`/pl`) versions of one article. */
function syntheticArticle(): {
    en: ArticleTranslation;
    pl: ArticleTranslation;
} {
    const id = uniqueId();

    return {
        en: {
            title: `E2E article ${id}`,
            slug: `e2e-article-${id}`,
            body: `Synthetic body of the E2E article ${id}.`,
        },
        pl: {
            title: `E2E artykul ${id}`,
            slug: `e2e-artykul-${id}`,
            body: `Syntetyczna tresc artykulu E2E ${id}.`,
        },
    };
}

/** Fills the active language tab of the article form. */
async function fillTranslation(
    page: Page,
    translation: ArticleTranslation,
    publishedOn?: string,
): Promise<void> {
    const panel = page.getByRole('tabpanel');

    await panel.getByLabel('Title').fill(translation.title);
    await expect(panel.getByLabel('Slug')).toHaveValue(translation.slug);
    await panel
        .getByRole('textbox', { name: 'Content' })
        .fill(translation.body);
    await panel.getByRole('combobox', { name: 'Status' }).click();
    await page.getByRole('option', { name: 'Published' }).click();

    if (publishedOn !== undefined) {
        await panel.getByLabel('Publication date').fill(publishedOn);
    }
}

async function openNewArticleForm(page: Page): Promise<void> {
    await page.goto('/admin/articles');
    // An empty list also shows "New article" in its empty state; the page
    // header action comes first in the document.
    await page.getByRole('link', { name: 'New article' }).first().click();
    await expect(
        page.getByRole('heading', { name: 'New article', level: 1 }),
    ).toBeVisible();
}

async function saveNewArticle(page: Page): Promise<void> {
    await page
        .getByRole('tabpanel')
        .getByRole('button', { name: 'Save changes' })
        .click();
    await expect(page).toHaveURL(/\/admin\/articles\/\d+\/edit$/);
    await expect(page.getByText('Article created.')).toBeVisible();
}

// One admin page per file, restored from the session saved by auth.setup.ts
// (Fortify allows 5 logins per minute and account).
test.describe('articles', () => {
    test.describe.configure({ mode: 'serial' });

    let page: Page;

    test.beforeAll(async ({ browser }) => {
        page = await signedInPage(browser, 'admin');
    });

    test.afterAll(async () => {
        await page.close();
    });

    test('admin publishes a bilingual article, edits its title and deletes it after confirmation', async ({
        browser,
    }) => {
        const article = syntheticArticle();
        const editedTitle = `${article.en.title} edited`;

        await openNewArticleForm(page);

        await fillTranslation(page, article.en);
        await page.getByRole('tab', { name: /^Polski/ }).click();
        await fillTranslation(page, article.pl);
        await saveNewArticle(page);

        // A guest browser: public visibility must not depend on the admin session.
        const visitor = await browser.newPage();

        await visitor.goto('/articles');
        await expect(
            visitor.getByRole('link', { name: article.en.title }),
        ).toBeVisible();

        let response = await visitor.goto(`/articles/${article.en.slug}`);
        expect(response?.status()).toBe(200);
        await expect(
            visitor.getByRole('heading', { name: article.en.title, level: 1 }),
        ).toBeVisible();
        await expect(visitor.getByText(article.en.body)).toBeVisible();

        response = await visitor.goto(`/pl/articles/${article.pl.slug}`);
        expect(response?.status()).toBe(200);
        await expect(
            visitor.getByRole('heading', { name: article.pl.title, level: 1 }),
        ).toBeVisible();

        // An existing slug is kept when the title changes.
        await page.getByRole('tab', { name: /^English/ }).click();
        const panel = page.getByRole('tabpanel');
        await panel.getByLabel('Title').fill(editedTitle);
        await expect(panel.getByLabel('Slug')).toHaveValue(article.en.slug);
        await panel.getByRole('button', { name: 'Save changes' }).click();
        await expect(page.getByText('Article saved.')).toBeVisible();

        await visitor.goto(`/articles/${article.en.slug}`);
        await expect(
            visitor.getByRole('heading', { name: editedTitle, level: 1 }),
        ).toBeVisible();

        await page.goto(
            `/admin/articles?search=${encodeURIComponent(article.en.slug)}`,
        );
        await page
            .getByRole('button', { name: `Actions for ${editedTitle}` })
            .click();
        await page.getByRole('menuitem', { name: 'Delete' }).click();

        const dialog = page.getByRole('dialog', {
            name: 'Delete this article?',
        });
        await expect(dialog).toBeVisible();
        await dialog
            .getByRole('button', { name: 'Delete permanently' })
            .click();

        await expect(page.getByText('Article deleted.')).toBeVisible();
        await expect(dialog).toBeHidden();
        await expect(page.getByRole('link', { name: editedTitle })).toHaveCount(
            0,
        );

        response = await visitor.goto(`/articles/${article.en.slug}`);
        expect(response?.status()).toBe(404);
        response = await visitor.goto(`/pl/articles/${article.pl.slug}`);
        expect(response?.status()).toBe(404);
        await visitor.goto('/articles');
        await expect(
            visitor.getByRole('link', { name: editedTitle }),
        ).toHaveCount(0);
        await visitor.close();
    });

    test('article scheduled for a future date stays hidden from visitors', async ({
        request,
    }) => {
        const { en: article } = syntheticArticle();

        await openNewArticleForm(page);
        await fillTranslation(page, article, '2099-12-31');
        await saveNewArticle(page);

        // The signed preview renders the unpublished version in a new tab.
        const [preview] = await Promise.all([
            page.waitForEvent('popup'),
            page.getByRole('link', { name: /^Preview: English/ }).click(),
        ]);
        await expect(
            preview.getByRole('heading', { name: article.title, level: 1 }),
        ).toBeVisible();
        await expect(
            preview
                .getByRole('status')
                .filter({ hasText: 'Preview — not public' }),
        ).toContainText('Status: scheduled for');
        await expect(preview.getByText(article.body)).toBeVisible();
        await preview.close();

        await page.goto(
            `/admin/articles?search=${encodeURIComponent(article.slug)}`,
        );
        const row = page
            .getByRole('row')
            .filter({ has: page.getByRole('link', { name: article.title }) });
        await expect(row.getByText('Scheduled')).toBeVisible();

        const response = await request.get(`/articles/${article.slug}`);
        expect(response.status()).toBe(404);
        const list = await (await request.get('/articles')).text();
        expect(list).not.toContain(article.title);

        // Clean up through the edit screen's own delete action.
        await row.getByRole('link', { name: article.title }).click();
        await page.getByRole('button', { name: 'Delete article' }).click();
        await page
            .getByRole('dialog', { name: 'Delete this article?' })
            .getByRole('button', { name: 'Delete permanently' })
            .click();
        await expect(page.getByText('Article deleted.')).toBeVisible();
        await expect(page).toHaveURL(/\/admin\/articles$/);
    });
});
