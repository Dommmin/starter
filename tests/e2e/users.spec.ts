import { expect, test, type Page } from '@playwright/test';
import { accounts, e2ePassword, logIn, uniqueId } from './support';

/** Mail sink of the local stack (Mailpit API). */
const mailpitUrl = process.env.E2E_MAILPIT_URL ?? 'http://localhost:8025';

async function openUserRow(page: Page, email: string, name: string) {
    await page.goto(`/admin/users?search=${encodeURIComponent(email)}`);
    await page.getByRole('button', { name: `Actions for ${name}` }).click();
}

// One admin session per file: Fortify allows 5 logins per minute and
// account, and the whole suite signs in as the same admin.
test.describe('user management', () => {
    test.describe.configure({ mode: 'serial' });

    let page: Page;

    test.beforeAll(async ({ browser }) => {
        page = await browser.newPage();
        await logIn(page, 'admin');
    });

    test.afterAll(async () => {
        await page.close();
    });

    test('admin invites an editor, changes the role and deletes the account after confirmation', async ({
        request,
    }) => {
        const id = uniqueId();
        const user = {
            name: `E2E User ${id}`,
            email: `e2e-user-${id}@example.test`,
        };

        await page.goto('/admin/users');
        await page.getByRole('link', { name: 'New user' }).click();
        await expect(
            page.getByRole('heading', { name: 'New user', level: 1 }),
        ).toBeVisible();

        await page.getByLabel('Name').fill(user.name);
        await page.getByLabel('Email').fill(user.email);
        await page.getByRole('combobox', { name: 'Role' }).click();
        await page.getByRole('option', { name: 'Editor' }).click();
        await page.getByRole('button', { name: 'Save changes' }).click();

        // Account management requires a recent password confirmation; the
        // dialog resumes the pending request (fresh session = always asked).
        const confirmation = page.getByRole('dialog', {
            name: 'Confirm password',
        });
        await confirmation
            .getByRole('textbox', { name: 'Password' })
            .fill(e2ePassword());
        await confirmation
            .getByRole('button', { name: 'Confirm password' })
            .click();

        await expect(page).toHaveURL(/\/admin\/users\/\d+\/edit$/);
        await expect(
            page.getByText('User created. An invitation email is on its way.'),
        ).toBeVisible();
        await expect(page.getByRole('combobox', { name: 'Role' })).toHaveText(
            'Editor',
        );

        // The queued invitation (set-password link) reaches the mail sink.
        await expect
            .poll(
                async () => {
                    const response = await request.get(
                        `${mailpitUrl}/api/v1/search?query=${encodeURIComponent(`to:"${user.email}"`)}`,
                    );

                    return response.ok()
                        ? (
                              (await response.json()) as {
                                  messages_count: number;
                              }
                          ).messages_count
                        : 0;
                },
                { timeout: 20_000, message: 'invitation mail in Mailpit' },
            )
            .toBe(1);

        await page.getByRole('combobox', { name: 'Role' }).click();
        await page.getByRole('option', { name: 'Administrator' }).click();
        await page.getByRole('button', { name: 'Save changes' }).click();
        await expect(page.getByText('User saved.')).toBeVisible();

        await page.reload();
        await expect(page.getByRole('combobox', { name: 'Role' })).toHaveText(
            'Administrator',
        );

        await page.getByRole('button', { name: 'Delete user' }).click();
        const dialog = page.getByRole('dialog', { name: 'Delete this user?' });
        await expect(dialog).toBeVisible();
        await dialog
            .getByRole('button', { name: 'Delete permanently' })
            .click();

        await expect(page).toHaveURL(/\/admin\/users$/);
        await expect(page.getByText('User deleted.')).toBeVisible();

        await page.goto(
            `/admin/users?search=${encodeURIComponent(user.email)}`,
        );
        await expect(page.getByText('No users found')).toBeVisible();
    });

    test('admin can neither delete nor demote their own account', async () => {
        await openUserRow(page, accounts.admin, 'E2E Admin');
        await expect(
            page.getByRole('menuitem', { name: 'Edit' }),
        ).toBeVisible();
        await expect(
            page.getByRole('menuitem', { name: 'Delete' }),
        ).toHaveCount(0);

        await page.getByRole('menuitem', { name: 'Edit' }).click();
        await expect(page).toHaveURL(/\/admin\/users\/\d+\/edit$/);
        const ownId = /\/admin\/users\/(\d+)\/edit$/.exec(page.url())?.[1];

        await expect(
            page.getByRole('button', { name: 'Delete user' }),
        ).toHaveCount(0);
        await expect(
            page.getByRole('combobox', { name: 'Role' }),
        ).toBeDisabled();
        await expect(
            page.getByText('You cannot change your own role.'),
        ).toBeVisible();

        // The backend denies the request the UI does not offer.
        const xsrf = (await page.context().cookies()).find(
            (cookie) => cookie.name === 'XSRF-TOKEN',
        );
        expect(xsrf).toBeDefined();
        const response = await page.request.delete(`/admin/users/${ownId}`, {
            headers: {
                'X-XSRF-TOKEN': decodeURIComponent(xsrf?.value ?? ''),
                Accept: 'text/html',
            },
            maxRedirects: 0,
        });
        expect(response.status()).toBe(403);

        await page.reload();
        await expect(
            page.getByRole('heading', { name: 'Edit user', level: 1 }),
        ).toBeVisible();
    });
});
