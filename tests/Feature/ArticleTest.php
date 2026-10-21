<?php

namespace Tests\Feature;

use App\Filament\Resources\Articles\ArticleResource;
use App\Models\Article;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ArticleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    // =========================================================================
    // HAPPY PATH
    // =========================================================================

    /**
     * [Happy Path] Memastikan 'created_by' terisi otomatis dengan 'system' saat tidak ada user login.
     */
    public function test_article_auto_fills_created_by_with_system_when_no_user(): void
    {
        // 1. Arrange & Act: Buat artikel tanpa login (simulasi system)
        $article = Article::create([
            'title' => ['id' => 'Artikel Kesehatan Jantung', 'en' => 'Heart Health Article'],
            'slug' => ['id' => 'artikel-kesehatan-jantung', 'en' => 'heart-health-article'],
            'content' => ['id' => 'Konten panjang artikel.', 'en' => 'Long article content.'],
        ]);

        // 2. Assert: created_by harus terisi 'system'
        $this->assertEquals('system', $article->created_by);
    }

    /**
     * [Happy Path] Memastikan artikel mendukung Soft Delete (data tidak benar-benar hilang).
     */
    public function test_article_uses_soft_delete(): void
    {
        // 1. Arrange
        $article = Article::create([
            'title' => ['id' => 'Artikel Soft Delete', 'en' => 'Soft Delete Article'],
            'slug' => ['id' => 'artikel-soft-delete', 'en' => 'soft-delete-article'],
            'content' => ['id' => 'Konten artikel.', 'en' => 'Article content.'],
        ]);
        $articleId = $article->id;

        // 2. Act: Hapus artikel
        $article->delete();

        // 3. Assert: Tidak ada di query biasa (tanpa withTrashed)
        $this->assertNull(Article::find($articleId));

        // 4. Assert: Tapi masih ada di database (soft deleted)
        $this->assertNotNull(Article::withTrashed()->find($articleId));
        $this->assertNotNull(Article::withTrashed()->find($articleId)->deleted_at);
    }

    /**
     * [Happy Path] Memastikan cache dibersihkan saat artikel disimpan.
     */
    public function test_article_clears_cache_on_saved(): void
    {
        // 1. Arrange
        Cache::put('rs_web_cms_api_articles_version', 'data-lama');
        $this->assertTrue(Cache::has('rs_web_cms_api_articles_version'));

        // 2. Act
        Article::create([
            'title' => ['id' => 'Artikel Baru', 'en' => 'New Article'],
            'slug' => ['id' => 'artikel-baru', 'en' => 'new-article'],
            'content' => ['id' => 'Konten.', 'en' => 'Content.'],
        ]);

        // 3. Assert
        $this->assertNotEquals('data-lama', Cache::get('rs_web_cms_api_articles_version'), 'Cache version harus diperbarui!');
    }

    /**
     * [Happy Path] Memastikan cache dibersihkan saat artikel di-soft-delete.
     */
    public function test_article_clears_cache_on_deleted(): void
    {
        // 1. Arrange
        $article = Article::create([
            'title' => ['id' => 'Artikel Lama', 'en' => 'Old Article'],
            'slug' => ['id' => 'artikel-lama', 'en' => 'old-article'],
            'content' => ['id' => 'Konten.', 'en' => 'Content.'],
        ]);
        Cache::put('rs_web_cms_api_articles_version', 'data-lama');
        $this->assertTrue(Cache::has('rs_web_cms_api_articles_version'));

        // 2. Act
        $article->delete();

        // 3. Assert
        $this->assertNotEquals('data-lama', Cache::get('rs_web_cms_api_articles_version'), 'Cache version harus diperbarui!');
    }

    /**
     * [Happy Path] Memastikan cast 'is_active' bekerja: true tersimpan sebagai 'yes' di DB.
     */
    public function test_article_is_active_cast_works_correctly(): void
    {
        // 1. Arrange & Act: Set is_active = true (boolean)
        $article = Article::create([
            'title' => ['id' => 'Artikel Aktif', 'en' => 'Active Article'],
            'slug' => ['id' => 'artikel-aktif', 'en' => 'active-article'],
            'content' => ['id' => 'Konten.', 'en' => 'Content.'],
            'is_active' => true,
        ]);

        // 2. Assert: Nilai di DB haruslah string 'yes'
        $this->assertDatabaseHas('articles', ['id' => $article->id, 'is_active' => 'yes']);

        // 3. Assert: Nilai yang dikembalikan model haruslah boolean true
        $this->assertTrue($article->fresh()->is_active);
    }

    /**
     * [Happy Path] Memastikan ArticleResource membaca kolom title untuk judul record dan Global Search.
     */
    public function test_article_resource_record_title_and_global_search_use_title_column(): void
    {
        $this->seed(DatabaseSeeder::class);
        $marketing = User::where('email', 'marketing@rs-elisabeth.com')->firstOrFail();
        $marketing->update(['must_change_password' => false]);
        $this->actingAs($marketing);

        $article = Article::create([
            'title' => ['id' => 'Panduan Deteksi Dini Stroke', 'en' => 'Early Stroke Detection Guide'],
            'slug' => ['id' => 'panduan-deteksi-dini-stroke', 'en' => 'early-stroke-detection-guide'],
            'content' => ['id' => 'Konten artikel.', 'en' => 'Article content.'],
            'is_active' => true,
        ]);

        $this->assertSame('Panduan Deteksi Dini Stroke', ArticleResource::getRecordTitle($article));

        $results = ArticleResource::getGlobalSearchResults('Deteksi Dini Stroke');
        $this->assertCount(1, $results);
        $this->assertSame('Panduan Deteksi Dini Stroke', $results->first()->title);
    }

    // =========================================================================
    // SAD PATH
    // =========================================================================

    /**
     * [Sad Path] Memastikan artikel tanpa 'title' ditolak database (kolom wajib, I18N-1.3).
     */
    public function test_article_title_is_required_at_db_level(): void
    {
        $this->expectException(QueryException::class);

        Article::create([
            'slug' => ['id' => 'tanpa-judul', 'en' => 'no-title'],
            'content' => ['id' => 'Konten tanpa judul.', 'en' => 'Content without title.'],
        ]);
    }
}
