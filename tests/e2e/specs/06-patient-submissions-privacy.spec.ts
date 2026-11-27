import { expect, test } from '@playwright/test';
import { attachErrorTracker } from '../fixtures/panel';
import { getE2EConfig } from '../support/env';

const config = getE2EConfig();

const PII_SENTINELS = {
    ticket: 'FB-20261127-E2E001',
    reporter: 'E2E-PII-REPORTER-SECRET',
    patient: 'E2E-PII-PATIENT-SECRET',
    phone: '089998887776',
};

test.describe('Patient Submissions Privacy & Strict Super Admin Follow-Up', () => {
    test.describe('Super Admin', () => {
        test.use({ storageState: config.storageStatePaths.superAdmin });

        test('Dashboard hides patient PII while Feedback detail allows super_admin follow-up', async ({ page }) => {
            test.skip(
                config.isExternalTarget,
                'PII sentinel & follow-up mutation assertions only run in isolated E2E mode.',
            );

            const errors = attachErrorTracker(page);

            // 1. Dashboard (/admin) TIDAK BOLEH menampilkan nama pelapor, nama pasien, atau nomor telepon.
            await page.goto('/admin', { waitUntil: 'networkidle' });
            const dashboardBody = await page.locator('main').innerText();
            expect(dashboardBody).not.toContain(PII_SENTINELS.reporter);
            expect(dashboardBody).not.toContain(PII_SENTINELS.patient);
            expect(dashboardBody).not.toContain(PII_SENTINELS.phone);

            // 2. Buka daftar Feedback -> klik detail tiket sentinel.
            await page.goto('/admin/feedbacks/feedback', { waitUntil: 'networkidle' });
            const row = page.locator('tbody tr').filter({ hasText: PII_SENTINELS.ticket }).first();
            await expect(row).toBeVisible();

            const viewLink = row.locator('a[href*="/admin/feedbacks/feedback/"]').first();
            await viewLink.click();
            await page.waitForLoadState('networkidle');

            await expect(page.locator('main')).toContainText(PII_SENTINELS.reporter);
            await expect(page.locator('main')).toContainText(PII_SENTINELS.patient);

            // 3. Super Admin memiliki tombol Tindak lanjut / Follow Up.
            const followUpButton = page.getByRole('button', { name: /Tindak lanjut|Follow Up/i }).first();
            await expect(followUpButton).toBeVisible();
            await followUpButton.click();

            const modal = page.locator('.fi-modal-window').last();
            await expect(modal).toBeVisible();

            await modal.locator('select').first().selectOption('diproses');
            await modal.locator('textarea').first().fill('Ditindaklanjuti pada pengujian Playwright E2E.');
            await modal.locator('button[type="submit"]').first().click();

            await expect(page.locator('main')).toContainText('diproses', { timeout: 10_000 });

            errors.assertZeroErrors();
        });
    });

    test.describe('Marketing (Read-Only Feedback Policy)', () => {
        test.use({ storageState: config.storageStatePaths.marketing });

        test('can read Feedback detail but CANNOT see Follow-Up action (Strict Super Admin Control)', async ({ page }) => {
            test.skip(
                config.isExternalTarget,
                'Seeded Feedback sentinel only exists in isolated E2E mode.',
            );

            const errors = attachErrorTracker(page);

            await page.goto('/admin/feedbacks/feedback', { waitUntil: 'networkidle' });
            const row = page.locator('tbody tr').filter({ hasText: PII_SENTINELS.ticket }).first();
            await expect(row).toBeVisible();

            const viewLink = row.locator('a[href*="/admin/feedbacks/feedback/"]').first();
            await viewLink.click();
            await page.waitForLoadState('networkidle');

            await expect(page.locator('main')).toContainText(PII_SENTINELS.ticket);
            await expect(page.getByRole('button', { name: /Tindak lanjut|Follow Up/i })).toHaveCount(0);

            errors.assertZeroErrors();
        });
    });
});
