import { test as setup } from '@playwright/test';
import { accounts, authStatePath, logIn } from './support';

for (const account of Object.keys(accounts) as (keyof typeof accounts)[]) {
    setup(`sign in as ${account}`, async ({ page }) => {
        await logIn(page, account);
        await page.context().storageState({ path: authStatePath(account) });
    });
}
