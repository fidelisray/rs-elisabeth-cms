import { expect, test as setup } from '@playwright/test';
import { loginViaFilament } from './fixtures/panel';
import { getE2EConfig } from './support/env';

const config = getE2EConfig();

setup('authenticate as super_admin', async ({ page }) => {
    await loginViaFilament(page, config.credentials.superAdmin.email, config.credentials.superAdmin.password);
    await expect(page).toHaveURL(/\/admin$/);
    await page.context().storageState({ path: config.storageStatePaths.superAdmin });
});

setup('authenticate as marketing', async ({ page }) => {
    await loginViaFilament(page, config.credentials.marketing.email, config.credentials.marketing.password);
    await expect(page).toHaveURL(/\/admin$/);
    await page.context().storageState({ path: config.storageStatePaths.marketing });
});

setup('authenticate as staff_it', async ({ page }) => {
    await loginViaFilament(page, config.credentials.staffIt.email, config.credentials.staffIt.password);
    await expect(page).toHaveURL(/\/admin$/);
    await page.context().storageState({ path: config.storageStatePaths.staffIt });
});
