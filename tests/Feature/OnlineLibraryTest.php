<?php

namespace Tests\Feature;

use App\Models\OnlineLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OnlineLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_online_library_auto_fills_created_by_with_system_when_no_user(): void
    {
        $doc = OnlineLibrary::create([
            'title' => ['id' => 'Judul Tes', 'en' => 'Test Title'],
            'slug' => ['id' => 'judul-tes', 'en' => 'test-title'],
            'file_path' => 'online-libraries/test.pdf',
        ]);

        $this->assertEquals('system', $doc->created_by);
        $this->assertEquals('test.pdf', $doc->file_name);
    }

    public function test_online_library_uses_soft_delete(): void
    {
        $doc = OnlineLibrary::factory()->create();
        $id = $doc->id;

        $doc->delete();

        $this->assertNull(OnlineLibrary::find($id));
        $this->assertNotNull(OnlineLibrary::withTrashed()->find($id));
    }

    public function test_online_library_clears_cache_on_saved_and_deleted(): void
    {
        Cache::put('rs_web_cms_api_online_libraries_version', 'data-lama');

        $doc = OnlineLibrary::factory()->create();
        $this->assertNotEquals('data-lama', Cache::get('rs_web_cms_api_online_libraries_version'));

        Cache::put('rs_web_cms_api_online_libraries_version', 'data-lama2');
        $doc->delete();
        $this->assertNotEquals('data-lama2', Cache::get('rs_web_cms_api_online_libraries_version'));
    }

    public function test_online_library_is_published_cast_works_correctly(): void
    {
        $doc = OnlineLibrary::create([
            'title' => ['id' => 'Pedoman Baru', 'en' => 'New Guideline'],
            'slug' => ['id' => 'pedoman-baru', 'en' => 'new-guideline'],
            'file_path' => 'online-libraries/doc.pdf',
            'is_published' => true,
        ]);

        $this->assertDatabaseHas('online_libraries', ['id' => $doc->id, 'is_published' => 1]);
        $this->assertTrue($doc->fresh()->is_published);
    }
}
