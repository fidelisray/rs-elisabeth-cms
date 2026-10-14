<?php

namespace Tests\Feature\Api\Cms;

use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\GeneratesHmacHeaders;

class ArticleApiTest extends TestCase
{
    use GeneratesHmacHeaders, RefreshDatabase;

    public function test_api_returns_401_without_hmac_headers(): void
    {
        // Act: Request tanpa header HMAC
        $response = $this->getJson('/api/v1/cms/articles');

        // Assert
        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors', 'Missing required headers: X-Cons-ID, X-Timestamp, X-Signature.');
    }

    public function test_api_returns_200_with_valid_hmac(): void
    {
        // Arrange
        Article::create([
            'judul' => 'Test Article',
            'content' => 'Content here',
            'is_active' => true,
        ]);

        // Act: Request DENGAN header HMAC dari trait
        $response = $this->withHmac()->getJson('/api/v1/cms/articles');

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.0.title', 'Test Article');
    }

    public function test_api_returns_detail_data(): void
    {
        // Arrange
        $article = Article::create([
            'judul' => 'Detail Article',
            'slug' => 'detail-article',
            'content' => 'Content here',
            'is_active' => true,
        ]);

        // Act
        $response = $this->withHmac()->getJson('/api/v1/cms/articles/'.$article->id);

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'Detail Article');
    }

    public function test_api_returns_detail_data_by_slug(): void
    {
        // Arrange
        Article::create([
            'judul' => 'Cegah Stunting Pada Anak',
            'slug' => 'cegah-stunting-pada-anak',
            'content' => 'Content here',
            'is_active' => true,
        ]);

        // Act: lookup via slug (string), bukan ID numerik
        $response = $this->withHmac()->getJson('/api/v1/cms/articles/cegah-stunting-pada-anak');

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.slug', 'cegah-stunting-pada-anak')
            ->assertJsonPath('data.title', 'Cegah Stunting Pada Anak');
    }

    public function test_api_returns_404_for_unknown_slug(): void
    {
        // Act
        $response = $this->withHmac()->getJson('/api/v1/cms/articles/slug-ngawur-tidak-ada');

        // Assert: harus 404 eksplisit, BUKAN 500
        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('status_code', 404);
    }

    public function test_api_returns_404_for_unknown_numeric_id(): void
    {
        // Act
        $response = $this->withHmac()->getJson('/api/v1/cms/articles/99999');

        // Assert
        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('status_code', 404);
    }

    public function test_api_does_not_return_inactive_article_by_slug(): void
    {
        // Arrange
        Article::create([
            'judul' => 'Draft Article',
            'slug' => 'draft-article',
            'content' => 'Content here',
            'is_active' => false,
        ]);

        // Act
        $response = $this->withHmac()->getJson('/api/v1/cms/articles/draft-article');

        // Assert
        $response->assertStatus(404);
    }
}
