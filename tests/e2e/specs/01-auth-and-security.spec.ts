import { expect, test } from '@playwright/test';
import { attachErrorTracker, loginViaFilament } from '../fixtures/panel';
import { getE2EConfig } from '../support/env';

const config = getE2EConfig();

test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Authentication & Security Guards', () => {
    test('login page renders cleanly and links to Super Admin recovery login', async ({ page }) => {
        const errors = attachErrorTracker(page);

        await page.goto('/admin/login', { waitUntil: 'networkidle' });
        await expect(page.locator('input[type="email"], input[id*="email"]').first()).toBeVisible();
        await expect(page.locator('input[type="password"], input[id*="password"]').first()).toBeVisible();

        const recoveryLink = page.locator('a[href*="recovery-login"]');
        await expect(recoveryLink).toBeVisible();

        await recoveryLink.click();
        await expect(page).toHaveURL(/\/admin\/recovery-login$/);
        await expect(page.locator('input[autocomplete="one-time-code"]')).toBeVisible();

        errors.assertZeroErrors();
    });

    test('account with must_change_password=true is locked to force-password-change page', async ({ page }) => {
        test.skip(
            config.isExternalTarget,
            'Forced password change fixture account only runs in isolated E2E mode.',
        );

        const errors = attachErrorTracker(page);

        await loginViaFilament(
            page,
            config.credentials.forcedPasswordChange.email,
            config.credentials.forcedPasswordChange.password,
        );

        await expect(page).toHaveURL(/\/admin\/force-password-change$/);

        // EnforcePasswordChange harus memblokir upaya mengakses halaman admin lain di sisi server.
        await page.goto('/admin', { waitUntil: 'networkidle' });
        await expect(page).toHaveURL(/\/admin\/force-password-change$/);

        await page.goto('/admin/articles', { waitUntil: 'networkidle' });
        await expect(page).toHaveURL(/\/admin\/force-password-change$/);

        errors.assertZeroErrors();
    });

    test('marketing user can sign in and sign out cleanly', async ({ page }) => {
        const errors = attachErrorTracker(page);

        await loginViaFilament(page, config.credentials.marketing.email, config.credentials.marketing.password);
        await expect(page).toHaveURL(/\/admin$/);

        // Buka menu profil user Filament lalu klik tombol logout.
        const userMenuButton = page.locator('.fi-user-menu-trigger, button[aria-label*="User"], button[aria-label*="Pengguna"]').first();
        await userMenuButton.click();

        const logoutButton = page.locator('form[action*="logout"] button[type="submit"]').first();
        await expect(logoutButton).toBeVisible();

        await Promise.all([
            page.waitForURL(/\/admin\/login$/),
            logoutButton.click(),
        ]);

        errors.assertZeroErrors();
    });
});
