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

            // 1. Dashboard (/admin) merender KPI strip & Tabbed Patient Voice dan TIDAK BOLEH menampilkan PII pasien.
            await page.goto('/admin', { waitUntil: 'networkidle' });
            await expect(page.locator('[data-rse-dashboard-kpi-strip]')).toBeVisible();
            await expect(page.locator('[data-rse-patient-voice-workspace]')).toBeVisible();

            // Uji perpindahan 3 tab Patient Voice secara interaktif tanpa error.
            for (const tabKey of ['feedback', 'questionnaires', 'dynamic_forms']) {
                const tabButton = page.locator(`[data-rse-pv-tab="${tabKey}"]`);
                await expect(tabButton).toBeVisible();
                await tabButton.click();
                await expect(tabButton).toHaveAttribute('aria-selected', 'true');
            }

            const dashboardBody = await page.locator('main').innerText();
            expect(dashboardBody).not.toContain(PII_SENTINELS.reporter);
            expect(dashboardBody).not.toContain(PII_SENTINELS.patient);
            expect(dashboardBody).not.toContain(PII_SENTINELS.phone);

            // 2. Buka daftar Feedback -> pastikan aksi Ekspor Data tampil & modal ekspor terbuka bersih, lalu klik detail tiket sentinel.
            await page.goto('/admin/feedbacks/feedback', { waitUntil: 'networkidle' });
            const feedbackExportButton = page.getByRole('button', { name: /Ekspor Data|Export Data/i }).first();
            await expect(feedbackExportButton).toBeVisible();
            await feedbackExportButton.click();

            const exportModal = page.locator('.fi-modal-window').last();
            await expect(exportModal).toBeVisible();
            await expect(exportModal).toContainText(/Mode Anonim|Anonymized Mode/i);
            await page.keyboard.press('Escape');
            await expect(exportModal).toBeHidden();

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

            await expect(page.locator('main')).toContainText(/Diproses|In Progress/i, { timeout: 10_000 });

            // 4. Super Admin juga melihat aksi Ekspor Data pada Kuesioner Kepuasan.
            await page.goto('/admin/questionnaires', { waitUntil: 'networkidle' });
            await expect(page.getByRole('button', { name: /Ekspor Data|Export Data/i }).first()).toBeVisible();

            errors.assertZeroErrors();
        });
    });

    test.describe('Marketing (Read-Only Feedback Policy)', () => {
        test.use({ storageState: config.storageStatePaths.marketing });

        test('can read Feedback detail but CANNOT see Follow-Up or Export actions without explicit permissions', async ({ page }) => {
            test.skip(
                config.isExternalTarget,
                'Seeded Feedback sentinel only exists in isolated E2E mode.',
            );

            const errors = attachErrorTracker(page);

            await page.goto('/admin/feedbacks/feedback', { waitUntil: 'networkidle' });
            await expect(page.getByRole('button', { name: /Ekspor Data|Export Data/i })).toHaveCount(0);

            const row = page.locator('tbody tr').filter({ hasText: PII_SENTINELS.ticket }).first();
            await expect(row).toBeVisible();

            const viewLink = row.locator('a[href*="/admin/feedbacks/feedback/"]').first();
            await viewLink.click();
            await page.waitForLoadState('networkidle');

            await expect(page.locator('main')).toContainText(PII_SENTINELS.ticket);
            await expect(page.getByRole('button', { name: /Tindak lanjut|Follow Up/i })).toHaveCount(0);

            // Tanpa Export:QuestionnaireResponse, tombol Ekspor Data juga tersembunyi di Kuesioner Kepuasan.
            await page.goto('/admin/questionnaires', { waitUntil: 'networkidle' });
            await expect(page.getByRole('button', { name: /Ekspor Data|Export Data/i })).toHaveCount(0);

            errors.assertZeroErrors();
        });
    });
});
