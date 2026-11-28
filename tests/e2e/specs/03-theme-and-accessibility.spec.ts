import { expect, test } from '@playwright/test';
import { attachErrorTracker, auditWcagAA } from '../fixtures/panel';
import { getE2EConfig } from '../support/env';

const config = getE2EConfig();

test.describe('Theme Branding, Responsiveness & WCAG AA Accessibility', () => {
    test.use({ storageState: config.storageStatePaths.superAdmin });

    test('applies RS Elisabeth Medical Navy tokens, brand lockup, favicon, and supports dark mode without overflow', async ({ page }) => {
        const errors = attachErrorTracker(page);

        await page.goto('/admin', { waitUntil: 'networkidle' });

        // 1. Verifikasi stylesheet brand token & nilai warna Medical Navy (#095988).
        await expect(page.locator('style[data-rse-brand-tokens]')).toHaveCount(1);

        const lightTokens = await page.evaluate(() => {
            const rootStyle = getComputedStyle(document.documentElement);
            return {
                brand600: rootStyle.getPropertyValue('--rse-brand-600').trim().toLowerCase(),
                brandAccent: rootStyle.getPropertyValue('--rse-brand-accent').trim().toLowerCase(),
                secondary500: rootStyle.getPropertyValue('--rse-secondary-500').trim().toLowerCase(),
                scrollWidth: document.documentElement.scrollWidth,
                innerWidth: window.innerWidth,
            };
        });

        expect(lightTokens.brand600).toBe('#095988');
        expect(lightTokens.brandAccent).toBe('#095988');
        expect(lightTokens.secondary500).toBe('#159e74');
        expect(
            lightTokens.scrollWidth,
            `Horizontal overflow detected (${lightTokens.scrollWidth} > ${lightTokens.innerWidth})`,
        ).toBeLessThanOrEqual(lightTokens.innerWidth + 2);

        // 2. Verifikasi brand lockup (visible pada Desktop sidebar maupun Tablet topbar) & favicon.
        await expect(page.locator('.rse-brand-lockup:visible').first()).toBeVisible();
        const faviconHref = await page.locator('link[rel*="icon"]').first().getAttribute('href');
        expect(faviconHref ?? '').toContain('web-icon.webp');

        // 3. Verifikasi kalibrasi token Dark Mode (.dark).
        const darkAccent = await page.evaluate(() => {
            document.documentElement.classList.add('dark');
            const accent = getComputedStyle(document.documentElement)
                .getPropertyValue('--rse-brand-accent')
                .trim()
                .toLowerCase();
            document.documentElement.classList.remove('dark');
            return accent;
        });

        expect(darkAccent).toBe('#1997e6');

        // 4. Audit WCAG 2.0/2.1 AA pada halaman Dashboard.
        await auditWcagAA(page, 'Admin Dashboard (/admin)');

        errors.assertZeroErrors();
    });

    test('passes WCAG AA accessibility audit on News table page', async ({ page }) => {
        const errors = attachErrorTracker(page);

        await page.goto('/admin/news', { waitUntil: 'networkidle' });
        await expect(page.locator('main')).toBeVisible();

        await auditWcagAA(page, 'News Resource List (/admin/news)');

        errors.assertZeroErrors();
    });

    test('passes WCAG AA accessibility audit on System Monitor and Audit Logs pages', async ({ page }) => {
        const errors = attachErrorTracker(page);

        await page.goto('/admin/system-monitor', { waitUntil: 'networkidle' });
        await expect(page.locator('[data-rse-system-monitor]')).toBeVisible();
        await auditWcagAA(page, 'System & Integrations Monitor (/admin/system-monitor)');

        await page.goto('/admin/audit-logs', { waitUntil: 'networkidle' });
        await expect(page.locator('[data-rse-audit-logs]')).toBeVisible();
        await auditWcagAA(page, 'Audit Logs Viewer (/admin/audit-logs)');

        errors.assertZeroErrors();
    });
});
