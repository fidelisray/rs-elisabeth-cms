import { expect, test } from '@playwright/test';
import { attachErrorTracker } from '../fixtures/panel';
import { getE2EConfig } from '../support/env';

const config = getE2EConfig();

test.describe('Dynamic Forms 4-Step Wizard, Preview & Share QR Modal', () => {
    test.use({ storageState: config.storageStatePaths.marketing });

    test('navigates wizard steps cleanly without Livewire/JS errors', async ({ page }) => {
        const errors = attachErrorTracker(page);

        await page.goto('/admin/dynamic-forms/create', { waitUntil: 'networkidle' });

        // Pastikan 4 langkah wizard terpasang.
        const wizardSteps = page.locator('.fi-sc-wizard-header-step, [role="tablist"] li');
        await expect(wizardSteps).toHaveCount(4);

        const titleInput = page.locator('input[id$="title"]').first();
        const slugInput = page.locator('input[id$="slug"]').first();

        await expect(titleInput).toBeVisible();
        await titleInput.fill('Survei Uji Wizard Playwright');
        await titleInput.blur();
        await expect(slugInput).toHaveValue('survei-uji-wizard-playwright', { timeout: 10_000 });

        // Lanjut ke Step 2 (Section & Pertanyaan).
        const nextBtn = page.locator('.fi-sc-wizard-footer button').filter({ hasText: /Next|Lanjut|Berikutnya/i }).first();
        await expect(nextBtn).toBeVisible();
        await nextBtn.click();

        // Verifikasi repeater daftar section tampil pada Step 2.
        await expect(page.locator('.fi-fo-repeater').first()).toBeVisible({ timeout: 10_000 });

        errors.assertZeroErrors();
    });

    test('opens Share Link & QR Code modal on published dynamic form', async ({ page }) => {
        test.skip(
            config.isExternalTarget,
            'Seeded published dynamic form fixture only exists in isolated E2E mode.',
        );

        const errors = attachErrorTracker(page);

        await page.goto('/admin/dynamic-forms', { waitUntil: 'networkidle' });
        await expect(page.locator('table')).toContainText('Survei Kepuasan E2E');

        // Klik aksi Share / Bagikan pada baris formulir terpublikasi.
        const shareButton = page
            .locator('tbody tr')
            .filter({ hasText: 'Survei Kepuasan E2E' })
            .getByRole('button', { name: /Bagikan|Share|QR/i })
            .first();

        await expect(shareButton).toBeVisible();
        await shareButton.click();

        const modal = page.locator('.fi-modal-window').last();
        await expect(modal).toBeVisible();
        await expect(modal).toContainText('/formulir/survei-kepuasan-e2e');
        await expect(modal.locator('svg, img').first()).toBeVisible();

        errors.assertZeroErrors();
    });
});
