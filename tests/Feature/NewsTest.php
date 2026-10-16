<?php

namespace Tests\Feature;

use App\Models\News;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class NewsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_news_auto_fills_created_by_with_system_when_no_user(): void
    {
        $news = News::create([
            'title' => 'Test Title',
            'slug' => 'test-title',
            'content' => 'Test Content',
        ]);

        $this->assertEquals('system', $news->created_by);
    }

    public function test_news_uses_soft_delete(): void
    {
        $news = News::create(['title' => 'Title', 'slug' => 'title', 'content' => 'Content']);
        $id = $news->id;

        $news->delete();

        $this->assertNull(News::find($id));
        $this->assertNotNull(News::withTrashed()->find($id));
    }

    public function test_news_clears_cache_on_saved_and_deleted(): void
    {
        Cache::put('rs_web_cms_api_news_version', 'data-lama');

        $news = News::create(['title' => 'Title', 'slug' => 'title', 'content' => 'Content']);
        $this->assertNotEquals('data-lama', Cache::get('rs_web_cms_api_news_version'));

        Cache::put('rs_web_cms_api_news_version', 'data-lama2');
        $news->delete();
        $this->assertNotEquals('data-lama2', Cache::get('rs_web_cms_api_news_version'));
    }

    public function test_news_is_published_cast_works_correctly(): void
    {
        $news = News::create([
            'title' => 'Title',
            'slug' => 'title-2',
            'content' => 'Content',
            'is_published' => true,
        ]);

        $this->assertDatabaseHas('news', ['id' => $news->id, 'is_published' => 1]);
        $this->assertTrue($news->fresh()->is_published);
    }
}
