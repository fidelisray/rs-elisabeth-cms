import { expect, test } from '@playwright/test';
import { attachErrorTracker, loginViaFilament } from '../fixtures/panel';
import { getE2EConfig } from '../support/env';

const config = getE2EConfig();

test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Authentication & Security Guards', () => {
    test('login page renders clean English UI without locale toggle or Super Admin recovery link', async ({ page }) => {
        const errors = attachErrorTracker(page);

        await page.goto('/admin/login', { waitUntil: 'networkidle' });
        await expect(page.locator('input[type="email"], input[id*="email"]').first()).toBeVisible();
        await expect(page.locator('input[type="password"], input[id*="password"]').first()).toBeVisible();

        // Halaman login wajib tampil bersih dalam Bahasa Inggris tanpa toggle bahasa maupun tautan recovery Super Admin.
        const mainBody = page.locator('main, body').first();
        await expect(mainBody).toContainText('Sign in');
        await expect(mainBody).toContainText('Email address');
        await expect(mainBody).toContainText('Password');
        await expect(mainBody).toContainText('Need help signing in? Contact your IT Administrator.');
        await expect(page.locator('[data-rse-locale-switcher]')).toHaveCount(0);
        await expect(page.locator('a[href*="recovery-login"]')).toHaveCount(0);

        // Pure CLI-Only Break-Glass: endpoint web recovery-login wajib mengembalikan 404.
        const recoveryResponse = await page.goto('/admin/recovery-login', { waitUntil: 'domcontentloaded' });
        expect(recoveryResponse?.status()).toBe(404);

        expect(errors.serverErrors).toEqual([]);
        expect(errors.pageErrors).toEqual([]);
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

        // Setelah login, Topbar wajib menampilkan switcher ID | EN dengan default ID aktif.
        const topbarSwitcher = page.locator('[data-rse-locale-switcher="topbar"]').first();
        await expect(topbarSwitcher).toBeVisible();
        await expect(topbarSwitcher.locator('button[data-locale-option="id"]')).toHaveAttribute('aria-pressed', 'true');

        // Buka menu profil user Filament lalu klik tombol logout.
        const userMenuButton = page.locator('.fi-user-menu-trigger, button[aria-label*="User"], button[aria-label*="Pengguna"]').first();
        await userMenuButton.click();

        const logoutButton = page.locator('form[action*="logout"] button[type="submit"]').first();
        await expect(logoutButton).toBeVisible();

        await Promise.all([
            page.waitForURL(/\/admin\/login$/),
            logoutButton.click(),
        ]);

        // Kembali ke halaman login -> bersih Bahasa Inggris tanpa switcher.
        await expect(page.locator('[data-rse-locale-switcher]')).toHaveCount(0);
        await expect(page.locator('main, body').first()).toContainText('Sign in');

        errors.assertZeroErrors();
    });
});
