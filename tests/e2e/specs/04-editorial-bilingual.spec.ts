import path from 'node:path';
import { expect, test } from '@playwright/test';
import { attachErrorTracker } from '../fixtures/panel';
import { getE2EConfig } from '../support/env';

const config = getE2EConfig();

test.describe('Editorial Bilingual Dual-Section Workflow (News)', () => {
    test.use({ storageState: config.storageStatePaths.marketing });

    test('renders Indonesian & English sections side-by-side, auto-generates bilingual slugs, enforces dual-locale validation, and saves', async ({ page }) => {
        test.skip(
            config.isExternalTarget,
            'Write CRUD test is skipped against external Herd/Staging target to keep non-E2E DB clean.',
        );

        const errors = attachErrorTracker(page);
        const suffix = Date.now().toString().slice(-5);
        const titleId = `Berita Uji Bilingual ${suffix}`;
        const titleEn = `Bilingual Test News ${suffix}`;

        await page.goto('/admin/news/create', { waitUntil: 'networkidle' });

        // Pastikan pola dua section (ID & EN) tampil bersamaan tanpa hidden locale switcher.
        const titleIdInput = page.locator('input[id$="title.id"]');
        const slugIdInput = page.locator('input[id$="slug.id"]');
        const categoryIdInput = page.locator('input[id$="category.id"]');
        const shortsIdInput = page.locator('textarea[id$="shorts.id"]');

        const titleEnInput = page.locator('input[id$="title.en"]');
        const slugEnInput = page.locator('input[id$="slug.en"]');
        const categoryEnInput = page.locator('input[id$="category.en"]');
        const shortsEnInput = page.locator('textarea[id$="shorts.en"]');
        const authorInput = page.locator('input[id$="author"]');

        await expect(titleIdInput).toBeVisible();
        await expect(titleEnInput).toBeVisible();

        // Uji auto-slug ID & EN saat onBlur.
        await titleIdInput.fill(titleId);
        await titleIdInput.blur();
        await expect(slugIdInput).toHaveValue(`berita-uji-bilingual-${suffix}`, { timeout: 10_000 });

        await categoryIdInput.fill('Informasi RS');
        await shortsIdInput.fill('Ringkasan singkat berita uji bahasa Indonesia.');

        // Isi RichEditor ID (Tiptap contenteditable).
        const richEditors = page.locator('.fi-fo-rich-editor [contenteditable="true"]');
        await expect(richEditors).toHaveCount(2);
        await richEditors.nth(0).click();
        await richEditors.nth(0).fill('Isi konten lengkap berita uji dalam Bahasa Indonesia.');

        // Coba submit tanpa melengkapi section English -> wajib tertahan di halaman create.
        const createBtn = page.locator('main form button[type="submit"]').first();
        await createBtn.click();
        await expect(page).toHaveURL(/\/admin\/news\/create$/);

        // Lengkapi section English (EN).
        await titleEnInput.fill(titleEn);
        await titleEnInput.blur();
        await expect(slugEnInput).toHaveValue(`bilingual-test-news-${suffix}`, { timeout: 10_000 });

        await categoryEnInput.fill('Hospital Info');
        await shortsEnInput.fill('Short summary of bilingual test news in English.');
        await richEditors.nth(1).click();
        await richEditors.nth(1).fill('Full body content of the bilingual test news in English.');

        await authorInput.fill('Marketing E2E');

        // Unggah gambar sample 16:9 yang dibuat bootstrap-e2e.php.
        const sampleImagePath = path.join(config.authDir, 'sample-banner.png');
        const fileInput = page.locator('input[type="file"]').first();
        await fileInput.setInputFiles(sampleImagePath);

        // Tunggu FilePond menyelesaikan upload sementara ke Livewire sebelum submit.
        await expect(page.locator('.filepond--file-status-main').first()).toContainText(/complete|selesai/i, {
            timeout: 20_000,
        });

        await Promise.all([
            page.waitForURL((url) => /\/admin\/news\/\d+\/edit$|\/admin\/news$/.test(url.pathname), {
                timeout: 25_000,
            }),
            createBtn.click(),
        ]);

        // Verifikasi record tersimpan dan muncul di tabel daftar News.
        await page.goto('/admin/news', { waitUntil: 'networkidle' });
        await expect(page.locator('table')).toContainText(titleId);

        errors.assertZeroErrors();
    });
});
