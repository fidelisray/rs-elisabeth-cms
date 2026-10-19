<?php

namespace Tests\Feature;

use App\Filament\Resources\Articles\ArticleResource;
use App\Models\Article;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
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
            'title' => 'Artikel Kesehatan Jantung',
            'content' => 'Konten panjang artikel.',
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
            'title' => 'Artikel Soft Delete',
            'content' => 'Konten artikel.',
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
        Article::create(['title' => 'Artikel Baru', 'content' => 'Konten.']);

        // 3. Assert
        $this->assertNotEquals('data-lama', Cache::get('rs_web_cms_api_articles_version'), 'Cache version harus diperbarui!');
    }

    /**
     * [Happy Path] Memastikan cache dibersihkan saat artikel di-soft-delete.
     */
    public function test_article_clears_cache_on_deleted(): void
    {
        // 1. Arrange
        $article = Article::create(['title' => 'Artikel Lama', 'content' => 'Konten.']);
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
            'title' => 'Artikel Aktif',
            'content' => 'Konten.',
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
            'title' => 'Panduan Deteksi Dini Stroke',
            'slug' => 'panduan-deteksi-dini-stroke',
            'content' => 'Konten artikel.',
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
     * [Sad Path] Memastikan artikel dengan 'title' null tetap tersimpan (kolom nullable),
     * namun nilai 'title' tersebut memang null di database.
     *
     * Catatan: Validasi 'required' dilakukan di layer Filament Form, bukan di DB.
     * Test ini mendokumentasikan bahwa DB layer bersifat lenient (nullable).
     */
    public function test_article_title_is_nullable_at_db_level(): void
    {
        // 1. Act: Buat artikel tanpa title (DB mengizinkan karena nullable)
        $article = Article::create(['content' => 'Konten tanpa judul.']);

        // 2. Assert: Data tersimpan tapi title memang null
        $this->assertDatabaseHas('articles', ['id' => $article->id]);
        $this->assertNull($article->title);
    }
}
