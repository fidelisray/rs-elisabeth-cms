<?php

namespace Tests\Feature;

use App\Models\Article;
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
            'judul' => 'Artikel Kesehatan Jantung',
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
            'judul' => 'Artikel Soft Delete',
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
        Article::create(['judul' => 'Artikel Baru', 'content' => 'Konten.']);

        // 3. Assert
        $this->assertNotEquals('data-lama', Cache::get('rs_web_cms_api_articles_version'), 'Cache version harus diperbarui!');
    }

    /**
     * [Happy Path] Memastikan cache dibersihkan saat artikel di-soft-delete.
     */
    public function test_article_clears_cache_on_deleted(): void
    {
        // 1. Arrange
        $article = Article::create(['judul' => 'Artikel Lama', 'content' => 'Konten.']);
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
            'judul' => 'Artikel Aktif',
            'content' => 'Konten.',
            'is_active' => true,
        ]);

        // 2. Assert: Nilai di DB haruslah string 'yes'
        $this->assertDatabaseHas('articles', ['id' => $article->id, 'is_active' => 'yes']);

        // 3. Assert: Nilai yang dikembalikan model haruslah boolean true
        $this->assertTrue($article->fresh()->is_active);
    }

    // =========================================================================
    // SAD PATH
    // =========================================================================

    /**
     * [Sad Path] Memastikan artikel dengan 'judul' null tetap tersimpan (kolom nullable),
     * namun nilai 'judul' tersebut memang null di database.
     *
     * Catatan: Validasi 'required' dilakukan di layer Filament Form, bukan di DB.
     * Test ini mendokumentasikan bahwa DB layer bersifat lenient (nullable).
     */
    public function test_article_judul_is_nullable_at_db_level(): void
    {
        // 1. Act: Buat artikel tanpa judul (DB mengizinkan karena nullable)
        $article = Article::create(['content' => 'Konten tanpa judul.']);

        // 2. Assert: Data tersimpan tapi judul memang null
        $this->assertDatabaseHas('articles', ['id' => $article->id]);
        $this->assertNull($article->judul);
    }
}
