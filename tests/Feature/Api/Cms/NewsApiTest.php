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
            'title' => ['id' => 'Test News', 'en' => 'Test News EN'],
            'slug' => ['id' => 'test-news', 'en' => 'test-news-en'],
            'content' => ['id' => 'Content here', 'en' => 'English content'],
            'is_published' => true,
        ]);

        $response = $this->withHmac()->getJson('/api/v1/cms/news');
        $response->assertStatus(200)->assertJsonPath('data.0.title', 'Test News');
    }

    public function test_api_returns_detail_data(): void
    {
        News::create([
            'title' => ['id' => 'Detail News', 'en' => 'Detail News EN'],
            'slug' => ['id' => 'detail-news', 'en' => 'detail-news-en'],
            'content' => ['id' => 'Content here', 'en' => 'English content'],
            'is_published' => true,
        ]);

        $response = $this->withHmac()->getJson('/api/v1/cms/news/detail-news');
        $response->assertStatus(200)->assertJsonPath('data.title', 'Detail News');
    }
}
