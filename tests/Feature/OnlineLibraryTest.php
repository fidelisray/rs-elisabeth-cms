<?php

namespace Tests\Feature;

use App\Filament\Resources\OnlineLibraries\Pages\CreateOnlineLibrary;
use App\Models\OnlineLibrary;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
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

    private function actAsMarketing(): User
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::where('email', 'marketing@rs-elisabeth.com')->first();
        $user->update(['must_change_password' => false]);
        $this->actingAs($user);

        return $user;
    }

    public function test_creating_online_library_with_only_indonesian_halts_and_switches_to_en(): void
    {
        $this->actAsMarketing();
        config(['filesystems.media_disk' => 'media-test']);
        Storage::fake('media-test');

        $initialCount = OnlineLibrary::count();
        $file = UploadedFile::fake()->create('panduan.pdf', 100, 'application/pdf');

        Livewire::test(CreateOnlineLibrary::class)
            ->fillForm([
                'title' => 'Safety Working',
                'slug' => 'kesehatan-kerja',
                'category' => 'SOP',
                'description' => '<p>Kesehatan Kerja</p>',
                'file_path' => $file,
                'is_published' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified()
            ->assertSet('activeLocale', 'en');

        $this->assertSame($initialCount, OnlineLibrary::count());
    }

    public function test_creating_online_library_with_both_locales_succeeds(): void
    {
        $this->actAsMarketing();
        config(['filesystems.media_disk' => 'media-test']);
        Storage::fake('media-test');

        $file = UploadedFile::fake()->create('panduan.pdf', 100, 'application/pdf');

        Livewire::test(CreateOnlineLibrary::class)
            ->fillForm([
                'title' => 'Safety Working ID',
                'slug' => 'kesehatan-kerja-id',
                'category' => 'SOP',
                'description' => '<p>Kesehatan Kerja</p>',
                'file_path' => $file,
                'is_published' => true,
            ])
            ->set('activeLocale', 'en')
            ->fillForm([
                'title' => 'Safety Working EN',
                'slug' => 'safety-working-en',
                'category' => 'SOP EN',
                'description' => '<p>Occupational Health</p>',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $library = OnlineLibrary::where('slug->id', 'kesehatan-kerja-id')->first();
        $this->assertNotNull($library);
        $this->assertSame('Safety Working ID', $library->getTranslation('title', 'id'));
        $this->assertSame('Safety Working EN', $library->getTranslation('title', 'en'));
        $this->assertSame('kesehatan-kerja-id', $library->getTranslation('slug', 'id'));
        $this->assertSame('safety-working-en', $library->getTranslation('slug', 'en'));
    }
}
