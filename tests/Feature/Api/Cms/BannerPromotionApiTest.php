<?php

namespace Tests\Feature\Api\Cms;

use App\Models\BannerPromotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\GeneratesHmacHeaders;

class BannerPromotionApiTest extends TestCase
{
    use GeneratesHmacHeaders, RefreshDatabase;

    public function test_api_returns_401_without_hmac_headers(): void
    {
        $response = $this->getJson('/api/v1/cms/banner-promotions');
        $response->assertStatus(401);
    }

    public function test_api_returns_200_with_valid_hmac(): void
    {
        BannerPromotion::create([
            'title' => 'Test Banner',
            'image_path' => 'banners/test.jpg',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->withHmac()->getJson('/api/v1/cms/banner-promotions');
        $response->assertStatus(200)->assertJsonPath('data.0.title', 'Test Banner');
    }
}
