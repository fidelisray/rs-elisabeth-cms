<?php

namespace Tests\Feature\Api\Cms;

use App\Models\News;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\GeneratesHmacHeaders;

class NewsApiTest extends TestCase
{
    use GeneratesHmacHeaders, RefreshDatabase;

    public function test_api_returns_401_without_hmac_headers(): void
    {
        $response = $this->getJson('/api/v1/cms/news');
        $response->assertStatus(401);
    }

    public function test_api_returns_200_with_valid_hmac(): void
    {
        News::create([
            'title' => 'Test News',
            'slug' => 'test-news',
            'content' => 'Content here',
            'is_published' => true,
        ]);

        $response = $this->withHmac()->getJson('/api/v1/cms/news');
        $response->assertStatus(200)->assertJsonPath('data.0.title', 'Test News');
    }

    public function test_api_returns_detail_data(): void
    {
        News::create([
            'title' => 'Detail News',
            'slug' => 'detail-news',
            'content' => 'Content here',
            'is_published' => true,
        ]);

        $response = $this->withHmac()->getJson('/api/v1/cms/news/detail-news');
        $response->assertStatus(200)->assertJsonPath('data.title', 'Detail News');
    }
}
