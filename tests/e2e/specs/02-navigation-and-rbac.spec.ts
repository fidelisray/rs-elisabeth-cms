import { expect, test } from '@playwright/test';
import { attachErrorTracker } from '../fixtures/panel';
import { getE2EConfig } from '../support/env';

const config = getE2EConfig();

const EXPECTED_GROUPS_ID = [
    'Pemasaran & Editorial',
    'Layanan & Kamar',
    'Dokter & Perpustakaan',
    'Masukan Pasien',
    'Kontrol Akses',
];

const EXPECTED_GROUPS_EN = [
    'Marketing & Editorial',
    'Services & Rooms',
    'Doctors & Library',
    'Patient Feedback',
    'Access Control',
];

test.describe('Navigation & RBAC — Super Admin', () => {
    test.use({ storageState: config.storageStatePaths.superAdmin });

    test('sees all 5 navigation groups in default ID locale and can open every registered resource without errors', async ({ page }) => {
        const errors = attachErrorTracker(page);

        await page.goto('/admin', { waitUntil: 'networkidle' });

        const sidebar = page.locator('nav[aria-label*="navigation"], nav[aria-label*="Navigasi"], .fi-sidebar-nav').first();
        await expect(sidebar).toBeVisible();

        for (const groupName of EXPECTED_GROUPS_ID) {
            await expect(sidebar.getByRole('button', { name: groupName, exact: true })).toBeVisible();
        }

        // Kumpulkan seluruh tautan resource di sidebar untuk memastikan tidak ada broken route atau HTTP 500.
        const hrefs = await sidebar
            .locator('a[href*="/admin/"]')
            .evaluateAll((anchors) =>
                Array.from(
                    new Set(
                        anchors
                            .map((a) => a.getAttribute('href') ?? '')
                            .filter((href) => href !== '' && !href.includes('logout')),
                    ),
                ),
            );

        expect(hrefs.length).toBeGreaterThanOrEqual(12);

        for (const href of hrefs) {
            const response = await page.goto(href, { waitUntil: 'networkidle' });
            expect(response?.status(), `Unexpected HTTP status on ${href}`).toBeLessThan(400);
            await expect(page.locator('main')).toBeVisible();
        }

        errors.assertZeroErrors();
    });

    test('toggles panel language dynamically ID <-> EN and persists across page navigation and Livewire updates', async ({ page }) => {
        const errors = attachErrorTracker(page);

        await page.goto('/admin', { waitUntil: 'networkidle' });

        const topbarSwitcher = page.locator('header [data-rse-locale-switcher], .fi-topbar [data-rse-locale-switcher]').first();
        await expect(topbarSwitcher).toBeVisible();
        await expect(topbarSwitcher.locator('button[data-locale-option="id"]')).toHaveAttribute('aria-pressed', 'true');
        await expect(page.locator('h1.fi-header-heading')).toHaveText('Dasbor');

        // Beralih ke English (EN).
        await Promise.all([
            page.waitForLoadState('networkidle'),
            topbarSwitcher.locator('button[data-locale-option="en"]').click(),
        ]);

        await expect(page.locator('h1.fi-header-heading')).toHaveText('Dashboard');
        const sidebar = page.locator('.fi-sidebar-nav').first();
        for (const groupName of EXPECTED_GROUPS_EN) {
            await expect(sidebar.getByRole('button', { name: groupName, exact: true })).toBeVisible();
        }

        // Navigasi ke halaman Articles dan picu Livewire table search untuk membuktikan locale EN tetap persisten.
        await page.goto('/admin/articles', { waitUntil: 'networkidle' });
        await expect(page.locator('h1.fi-header-heading')).toHaveText('Articles');

        const searchInput = page.locator('.fi-ta-search-field input[type="search"], input[type="search"]').first();
        if (await searchInput.isVisible()) {
            await searchInput.fill('tidak-ada-artikel-e2e');
            await page.waitForTimeout(600);
            await expect(page.locator('h1.fi-header-heading')).toHaveText('Articles');
        }

        // Kembalikan ke Bahasa Indonesia (ID) agar state sesi tetap bersih untuk pengujian lain.
        await Promise.all([
            page.waitForLoadState('networkidle'),
            page.locator('header [data-rse-locale-switcher] button[data-locale-option="id"], .fi-topbar [data-rse-locale-switcher] button[data-locale-option="id"]').first().click(),
        ]);

        await expect(page.locator('h1.fi-header-heading')).toHaveText('Artikel');
        await expect(page.locator('.fi-sidebar-nav').first().getByRole('button', { name: 'Pemasaran & Editorial', exact: true })).toBeVisible();

        errors.assertZeroErrors();
    });
});

test.describe('Navigation & RBAC — Marketing', () => {
    test.use({ storageState: config.storageStatePaths.marketing });

    test('cannot see Access Control and receives 403 on User Management', async ({ page }) => {
        const errors = attachErrorTracker(page);

        await page.goto('/admin', { waitUntil: 'networkidle' });

        const sidebar = page.locator('nav[aria-label*="navigation"], nav[aria-label*="Navigasi"], .fi-sidebar-nav').first();
        await expect(sidebar.getByRole('button', { name: 'Pemasaran & Editorial', exact: true })).toBeVisible();
        await expect(sidebar.getByRole('button', { name: 'Kontrol Akses', exact: true })).toHaveCount(0);
        await expect(sidebar.getByRole('link', { name: 'Manajemen Pengguna', exact: true })).toHaveCount(0);

        const forbiddenResp = await page.goto('/admin/users', { waitUntil: 'domcontentloaded' });
        expect(forbiddenResp?.status()).toBe(403);

        // Pastikan tidak ada HTTP 5xx (403 pada halaman terlarang memang diharapkan).
        expect(errors.serverErrors).toEqual([]);
        expect(errors.pageErrors).toEqual([]);
    });
});

test.describe('Navigation & RBAC — Staff IT (Read-Only Content)', () => {
    test.use({ storageState: config.storageStatePaths.staffIt });

    test('can view articles list read-only without Create button and receives 403 on Users', async ({ page }) => {
        const errors = attachErrorTracker(page);

        await page.goto('/admin/articles', { waitUntil: 'networkidle' });
        await expect(page.locator('main')).toBeVisible();
        await expect(page.locator('a[href$="/admin/articles/create"]')).toHaveCount(0);

        const forbiddenResp = await page.goto('/admin/users', { waitUntil: 'domcontentloaded' });
        expect(forbiddenResp?.status()).toBe(403);

        expect(errors.serverErrors).toEqual([]);
        expect(errors.pageErrors).toEqual([]);
    });
});
