<?php

namespace Tests\Feature\Api\Cms;

use App\Models\BannerPromotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\GeneratesHmacHeaders;

class BannerPromotionApiTest extends TestCase
{
    use GeneratesHmacHeaders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.media_disk' => 'media-test']);
        Storage::fake('media-test');
    }

    public function test_api_returns_401_without_hmac_headers(): void
    {
        $response = $this->getJson('/api/v1/cms/banner-promotions');
        $response->assertStatus(401);
    }

    public function test_api_returns_200_with_valid_hmac(): void
    {
        BannerPromotion::create([
            'title' => ['id' => 'Test Banner', 'en' => 'Test Banner EN'],
            'slug' => ['id' => 'test-banner', 'en' => 'test-banner-en'],
            'image_path' => 'banners/test.jpg',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->withHmac()->getJson('/api/v1/cms/banner-promotions');
        $response->assertStatus(200)->assertJsonPath('data.0.title', 'Test Banner');
    }
}
