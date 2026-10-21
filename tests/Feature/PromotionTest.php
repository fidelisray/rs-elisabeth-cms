<?php

namespace Tests\Feature;

use App\Models\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PromotionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_promotion_auto_fills_created_by_with_system_when_no_user(): void
    {
        $promo = Promotion::create([
            'title' => ['id' => 'Test Promo', 'en' => 'Test Promo EN'],
            'slug' => ['id' => 'test-promo', 'en' => 'test-promo-en'],
            'description' => ['id' => 'Desc', 'en' => 'Desc EN'],
        ]);

        $this->assertEquals('system', $promo->created_by);
    }

    public function test_promotion_uses_soft_delete(): void
    {
        $promo = Promotion::create(['title' => ['id' => 'Title', 'en' => 'Title EN'], 'slug' => ['id' => 'title', 'en' => 'title-en'], 'description' => ['id' => 'Desc', 'en' => 'Desc EN']]);
        $id = $promo->id;

        $promo->delete();

        $this->assertNull(Promotion::find($id));
        $this->assertNotNull(Promotion::withTrashed()->find($id));
    }

    public function test_promotion_clears_cache_on_saved_and_deleted(): void
    {
        Cache::put('rs_web_cms_api_promotions_version', 'data-lama');

        $promo = Promotion::create(['title' => ['id' => 'Title', 'en' => 'Title EN'], 'slug' => ['id' => 'title', 'en' => 'title-en'], 'description' => ['id' => 'Desc', 'en' => 'Desc EN']]);
        $this->assertNotEquals('data-lama', Cache::get('rs_web_cms_api_promotions_version'));

        Cache::put('rs_web_cms_api_promotions_version', 'data-lama2');
        $promo->delete();
        $this->assertNotEquals('data-lama2', Cache::get('rs_web_cms_api_promotions_version'));
    }

    public function test_promotion_is_active_cast_works_correctly(): void
    {
        $promo = Promotion::create([
            'title' => ['id' => 'Title', 'en' => 'Title EN'],
            'slug' => ['id' => 'title', 'en' => 'title-en'],
            'description' => ['id' => 'Desc', 'en' => 'Desc EN'],
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('promotions', ['id' => $promo->id, 'is_active' => 1]);
        $this->assertTrue($promo->fresh()->is_active);
    }
}
