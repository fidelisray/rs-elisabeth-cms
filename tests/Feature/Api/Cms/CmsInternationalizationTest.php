<?php

namespace Tests\Feature\Api\Cms;

use App\Models\News;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\GeneratesHmacHeaders;

class CmsInternationalizationTest extends TestCase
{
    use GeneratesHmacHeaders, RefreshDatabase;

    public function test_api_defaults_to_indonesian_locale_and_sets_content_language_header(): void
    {
        News::create([
            'title' => ['id' => 'Judul Berita Indonesia', 'en' => 'English News Title'],
            'slug' => ['id' => 'judul-berita-indonesia', 'en' => 'english-news-title'],
            'shorts' => ['id' => 'Ringkasan Berita', 'en' => 'News Summary'],
            'content' => ['id' => '<p>Konten Berita</p>', 'en' => '<p>News Content</p>'],
            'category' => 'Kesehatan',
            'author' => 'Redaksi',
            'is_published' => true,
        ]);

        $response = $this->withHmac()
            ->withServerVariables(['HTTP_ACCEPT_LANGUAGE' => 'id-ID,id;q=0.9'])
            ->getJson('/api/v1/cms/news');

        $response->assertStatus(200);
        $response->assertHeader('Content-Language', 'id');
        $data = $response->json('data.0');
        $this->assertSame('Judul Berita Indonesia', $data['title']);
        $this->assertSame('judul-berita-indonesia', $data['slug']);
        $this->assertSame('Ringkasan Berita', $data['excerpt']);
        $this->assertSame(['id' => 'judul-berita-indonesia', 'en' => 'english-news-title'], $data['alternate_slugs']);
    }

    public function test_api_returns_english_content_when_lang_query_param_is_en(): void
    {
        News::create([
            'title' => ['id' => 'Judul Berita Indonesia', 'en' => 'English News Title'],
            'slug' => ['id' => 'judul-berita-indonesia', 'en' => 'english-news-title'],
            'content' => ['id' => '<p>Konten</p>', 'en' => '<p>Content</p>'],
            'is_published' => true,
        ]);

        $response = $this->withHmac()->getJson('/api/v1/cms/news?lang=en');

        $response->assertStatus(200);
        $response->assertHeader('Content-Language', 'en');
        $data = $response->json('data.0');
        $this->assertSame('English News Title', $data['title']);
        $this->assertSame('english-news-title', $data['slug']);
    }

    public function test_api_returns_english_content_via_accept_language_header(): void
    {
        News::create([
            'title' => ['id' => 'Judul Berita Indonesia', 'en' => 'English News Title'],
            'slug' => ['id' => 'judul-berita-indonesia', 'en' => 'english-news-title'],
            'content' => ['id' => '<p>Konten</p>', 'en' => '<p>Content</p>'],
            'is_published' => true,
        ]);

        $response = $this->withHmac()
            ->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
            ->getJson('/api/v1/cms/news');

        $response->assertStatus(200);
        $response->assertHeader('Content-Language', 'en');
        $data = $response->json('data.0');
        $this->assertSame('English News Title', $data['title']);
    }

    public function test_api_falls_back_to_indonesian_when_translation_is_missing(): void
    {
        News::create([
            'title' => ['id' => 'Judul Hanya Bahasa Indonesia'],
            'slug' => ['id' => 'judul-hanya-bahasa-indonesia'],
            'content' => ['id' => '<p>Konten</p>'],
            'is_published' => true,
        ]);

        // Permintaan dalam bahasa Inggris, tetapi record hanya memiliki bahasa Indonesia
        $response = $this->withHmac()->getJson('/api/v1/cms/news?lang=en');

        $response->assertStatus(200);
        $data = $response->json('data.0');
        $this->assertSame('Judul Hanya Bahasa Indonesia', $data['title']);
    }

    public function test_detail_endpoint_resolves_record_by_english_slug(): void
    {
        News::create([
            'title' => ['id' => 'Judul Berita', 'en' => 'English News Headline'],
            'slug' => ['id' => 'judul-berita', 'en' => 'english-news-headline'],
            'content' => ['id' => '<p>Konten</p>', 'en' => '<p>Content</p>'],
            'is_published' => true,
        ]);

        // Cari menggunakan slug bahasa Inggris dengan query parameter lang=en
        $response = $this->withHmac()->getJson('/api/v1/cms/news/english-news-headline?lang=en');

        $response->assertStatus(200);
        $this->assertSame('English News Headline', $response->json('data.title'));
        $this->assertSame('english-news-headline', $response->json('data.slug'));
        $this->assertSame('judul-berita', $response->json('data.alternate_slugs.id'));
        $this->assertSame('english-news-headline', $response->json('data.alternate_slugs.en'));
    }

    public function test_unsupported_language_falls_back_to_id(): void
    {
        News::create([
            'title' => ['id' => 'Judul Berita Indonesia', 'en' => 'English News Title'],
            'slug' => ['id' => 'judul-berita-indonesia', 'en' => 'english-news-title'],
            'content' => ['id' => '<p>Konten</p>', 'en' => '<p>Content</p>'],
            'is_published' => true,
        ]);

        $response = $this->withHmac()->getJson('/api/v1/cms/news?lang=de');

        $response->assertStatus(200);
        $response->assertHeader('Content-Language', 'id');
        $this->assertSame('Judul Berita Indonesia', $response->json('data.0.title'));
    }
}
