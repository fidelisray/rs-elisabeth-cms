<?php

namespace Tests\Feature\Api\Cms;

use App\Models\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\GeneratesHmacHeaders;

class PromotionApiTest extends TestCase
{
    use GeneratesHmacHeaders, RefreshDatabase;

    public function test_api_returns_401_without_hmac_headers(): void
    {
        $response = $this->getJson('/api/v1/cms/promotions');
        $response->assertStatus(401);
    }

    public function test_api_returns_200_with_valid_hmac(): void
    {
        Promotion::create([
            'title' => 'Test Promo',
            'description' => 'Desc',
            'is_active' => true,
        ]);

        $response = $this->withHmac()->getJson('/api/v1/cms/promotions');
        $response->assertStatus(200)->assertJsonPath('data.0.title', 'Test Promo');
    }

    public function test_api_returns_detail_data(): void
    {
        $promo = Promotion::create([
            'title' => 'Detail Promo',
            'description' => 'Desc',
            'is_active' => true,
        ]);

        $response = $this->withHmac()->getJson('/api/v1/cms/promotions/'.$promo->id);
        $response->assertStatus(200)->assertJsonPath('data.title', 'Detail Promo');
    }

    public function test_api_returns_detail_data_by_slug(): void
    {
        // Arrange
        Promotion::create([
            'title' => 'Promo Berhasil',
            'slug' => 'promo-berhasil',
            'description' => 'Desc',
            'is_active' => true,
        ]);

        // Act: lookup via slug (string), bukan ID numerik
        $response = $this->withHmac()->getJson('/api/v1/cms/promotions/promo-berhasil');

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.slug', 'promo-berhasil')
            ->assertJsonPath('data.title', 'Promo Berhasil');
    }

    public function test_api_returns_404_for_unknown_slug(): void
    {
        // Act
        $response = $this->withHmac()->getJson('/api/v1/cms/promotions/slug-ngawur-tidak-ada');

        // Assert: harus 404 eksplisit, BUKAN 500
        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('status_code', 404);
    }

    public function test_api_does_not_return_inactive_promotion_by_slug(): void
    {
        // Arrange
        Promotion::create([
            'title' => 'Draft Promo',
            'slug' => 'draft-promo',
            'description' => 'Desc',
            'is_active' => false,
        ]);

        // Act
        $response = $this->withHmac()->getJson('/api/v1/cms/promotions/draft-promo');

        // Assert
        $response->assertStatus(404);
    }
}
