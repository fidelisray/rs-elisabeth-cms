import { expect, test } from '@playwright/test';
import { attachErrorTracker } from '../fixtures/panel';
import { getE2EConfig } from '../support/env';

const config = getE2EConfig();

const EXPECTED_GROUPS = [
    'Marketing & Editorial',
    'Services & Rooms',
    'Doctors & Library',
    'Patient Feedback',
    'Access Control',
];

test.describe('Navigation & RBAC — Super Admin', () => {
    test.use({ storageState: config.storageStatePaths.superAdmin });

    test('sees all 5 navigation groups and can open every registered resource without errors', async ({ page }) => {
        const errors = attachErrorTracker(page);

        await page.goto('/admin', { waitUntil: 'networkidle' });

        const sidebar = page.locator('nav[aria-label*="navigation"], .fi-sidebar-nav').first();
        await expect(sidebar).toBeVisible();

        for (const groupName of EXPECTED_GROUPS) {
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
});

test.describe('Navigation & RBAC — Marketing', () => {
    test.use({ storageState: config.storageStatePaths.marketing });

    test('cannot see Access Control and receives 403 on User Management', async ({ page }) => {
        const errors = attachErrorTracker(page);

        await page.goto('/admin', { waitUntil: 'networkidle' });

        const sidebar = page.locator('nav[aria-label*="navigation"], .fi-sidebar-nav').first();
        await expect(sidebar.getByRole('button', { name: 'Marketing & Editorial', exact: true })).toBeVisible();
        await expect(sidebar.getByRole('button', { name: 'Access Control', exact: true })).toHaveCount(0);
        await expect(sidebar.getByRole('link', { name: 'User Management', exact: true })).toHaveCount(0);

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
