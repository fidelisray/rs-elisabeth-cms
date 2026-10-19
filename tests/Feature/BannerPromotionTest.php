<?php

namespace Tests\Feature;

use App\Models\BannerPromotion;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BannerPromotionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['filesystems.media_disk' => 'media-test']);
        Storage::fake('media-test');
    }

    // =========================================================================
    // HAPPY PATH
    // =========================================================================

    /**
     * [Happy Path] Memastikan 'sort_order' terisi otomatis secara berurutan.
     */
    public function test_banner_auto_increments_sort_order(): void
    {
        // 1. Arrange & Act: Buat 3 banner tanpa mengisi sort_order secara manual
        $banner1 = BannerPromotion::create(['title' => 'Banner A', 'image_path' => 'banners/a.jpg']);
        $banner2 = BannerPromotion::create(['title' => 'Banner B', 'image_path' => 'banners/b.jpg']);
        $banner3 = BannerPromotion::create(['title' => 'Banner C', 'image_path' => 'banners/c.jpg']);

        // 2. Assert: Setiap banner harus mendapat sort_order yang berurutan
        $this->assertEquals(1, $banner1->sort_order);
        $this->assertEquals(2, $banner2->sort_order);
        $this->assertEquals(3, $banner3->sort_order);
    }

    /**
     * [Happy Path] Memastikan 'created_by' terisi 'system' saat tidak ada user yang login.
     */
    public function test_banner_auto_fills_created_by_with_system(): void
    {
        // 1. Arrange & Act
        $banner = BannerPromotion::create(['title' => 'Banner Sistem', 'image_path' => 'banners/sistem.jpg']);

        // 2. Assert
        $this->assertEquals('system', $banner->created_by);
    }

    /**
     * [Happy Path] Memastikan Banner mendukung Soft Delete.
     */
    public function test_banner_uses_soft_delete(): void
    {
        // 1. Arrange
        $banner = BannerPromotion::create(['title' => 'Banner Soft Delete', 'image_path' => 'banners/softdelete.jpg']);
        $bannerId = $banner->id;

        // 2. Act
        $banner->delete();

        // 3. Assert: Tidak muncul di query normal
        $this->assertNull(BannerPromotion::find($bannerId));

        // 4. Assert: Masih ada di database (soft deleted)
        $this->assertNotNull(BannerPromotion::withTrashed()->find($bannerId));
    }

    /**
     * [Happy Path] Memastikan cache dibersihkan saat banner disimpan.
     */
    public function test_banner_clears_cache_on_saved(): void
    {
        // 1. Arrange
        Cache::put('rs_web_cms_api_banner_promotions', 'data-lama');
        $this->assertTrue(Cache::has('rs_web_cms_api_banner_promotions'));

        // 2. Act
        BannerPromotion::create(['title' => 'Banner Baru', 'image_path' => 'banners/baru.jpg']);

        // 3. Assert
        $this->assertFalse(
            Cache::has('rs_web_cms_api_banner_promotions'),
            'Cache harus terhapus setelah banner disimpan!'
        );
    }

    /**
     * [Happy Path] Memastikan cache dibersihkan saat banner dihapus.
     */
    public function test_banner_clears_cache_on_deleted(): void
    {
        // 1. Arrange
        $banner = BannerPromotion::create(['title' => 'Banner Lama', 'image_path' => 'banners/lama.jpg']);
        Cache::put('rs_web_cms_api_banner_promotions', 'data-lama');
        $this->assertTrue(Cache::has('rs_web_cms_api_banner_promotions'));

        // 2. Act
        $banner->delete();

        // 3. Assert
        $this->assertFalse(
            Cache::has('rs_web_cms_api_banner_promotions'),
            'Cache harus terhapus setelah banner dihapus!'
        );
    }

    // =========================================================================
    // SAD PATH
    // =========================================================================

    /**
     * [Sad Path] Memastikan banner TIDAK bisa dibuat tanpa field 'title'.
     */
    public function test_banner_creation_fails_without_title(): void
    {
        $this->expectException(QueryException::class);

        BannerPromotion::create(['image_path' => 'path/to/image.jpg']);
    }
}
