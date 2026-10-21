<?php

namespace Tests\Feature;

use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Promotions\Pages\CreatePromotion;
use App\Filament\Resources\RoomFacilities\Pages\CreateRoomFacility;
use App\Models\Article;
use App\Models\Promotion;
use App\Models\RoomFacility;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentMultiLocaleValidationTest extends TestCase
{
    use RefreshDatabase;

    private function actAsMarketing(): User
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::where('email', 'marketing@rs-elisabeth.com')->first();
        $user->update(['must_change_password' => false]);
        $this->actingAs($user);

        return $user;
    }

    public function test_creating_article_with_only_indonesian_fails_and_halts(): void
    {
        $this->actAsMarketing();
        Storage::fake('public');

        $initialCount = Article::count();

        $file = UploadedFile::fake()->image('thumbnail.jpg', 1920, 1080);

        Livewire::test(CreateArticle::class)
            ->fillForm([
                'title' => 'Judul Artikel Hanya ID',
                'slug' => 'judul-artikel-hanya-id',
                'tags' => 'Artikel Kesehatan',
                'author' => 'Dr. Elisabeth',
                'shorts' => 'Ringkasan artikel dalam bahasa Indonesia.',
                'content' => '<p>Konten artikel lengkap bahasa Indonesia.</p>',
                'image_path' => $file,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified()
            ->assertSet('activeLocale', 'en');

        // Pastikan tidak ada data yang tersimpan ke database
        $this->assertSame($initialCount, Article::count());
    }

    public function test_creating_article_with_both_locales_succeeds(): void
    {
        $this->actAsMarketing();
        Storage::fake('public');

        $file = UploadedFile::fake()->image('thumbnail.jpg', 1920, 1080);

        Livewire::test(CreateArticle::class)
            // 1. Isi bahasa Indonesia terlebih dahulu
            ->fillForm([
                'title' => 'Judul Artikel Lengkap ID',
                'slug' => 'judul-artikel-lengkap-id',
                'tags' => 'Artikel Kesehatan',
                'author' => 'Dr. Elisabeth',
                'shorts' => 'Ringkasan artikel dalam bahasa Indonesia.',
                'content' => '<p>Konten artikel lengkap bahasa Indonesia.</p>',
                'image_path' => $file,
                'is_active' => true,
            ])
            // 2. Beralih ke tab English (EN)
            ->set('activeLocale', 'en')
            // 3. Isi terjemahan bahasa Inggris
            ->fillForm([
                'title' => 'Complete Article Title EN',
                'slug' => 'complete-article-title-en',
                'tags' => 'Health Articles',
                'shorts' => 'Short summary of the article in English.',
                'content' => '<p>Full content of the article in English.</p>',
            ])
            // 4. Klik simpan
            ->call('create')
            ->assertHasNoFormErrors();

        $article = Article::where('slug->id', 'judul-artikel-lengkap-id')->first();
        $this->assertNotNull($article);
        $this->assertSame('Judul Artikel Lengkap ID', $article->getTranslation('title', 'id'));
        $this->assertSame('Complete Article Title EN', $article->getTranslation('title', 'en'));
        $this->assertSame('judul-artikel-lengkap-id', $article->getTranslation('slug', 'id'));
        $this->assertSame('complete-article-title-en', $article->getTranslation('slug', 'en'));
        $this->assertSame('Ringkasan artikel dalam bahasa Indonesia.', $article->getTranslation('shorts', 'id'));
        $this->assertSame('Short summary of the article in English.', $article->getTranslation('shorts', 'en'));
    }

    public function test_auto_generates_english_slug_when_english_title_is_provided_without_slug(): void
    {
        $this->actAsMarketing();
        Storage::fake('public');

        $file = UploadedFile::fake()->image('thumbnail.jpg', 1920, 1080);

        Livewire::test(CreateArticle::class)
            ->fillForm([
                'title' => 'Judul Artikel Medis',
                'slug' => 'judul-artikel-medis',
                'tags' => 'Artikel Kesehatan',
                'author' => 'Dr. Elisabeth',
                'shorts' => 'Ringkasan artikel dalam bahasa Indonesia.',
                'content' => '<p>Konten artikel lengkap bahasa Indonesia.</p>',
                'image_path' => $file,
                'is_active' => true,
            ])
            ->set('activeLocale', 'en')
            ->fillForm([
                'title' => 'Medical Article Title',
                'tags' => 'Health Articles',
                // slug sengaja tidak diisi pada form
                'slug' => '',
                'shorts' => 'English summary.',
                'content' => '<p>English content.</p>',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $article = Article::where('slug->id', 'judul-artikel-medis')->first();
        $this->assertNotNull($article);
        $this->assertSame('medical-article-title', $article->getTranslation('slug', 'en'));
    }

    public function test_editing_article_without_english_translation_fails_and_halts(): void
    {
        $this->actAsMarketing();
        $mediaDisk = config('filesystems.media_disk');
        Storage::fake($mediaDisk);
        Storage::disk($mediaDisk)->put('articles/test.webp', 'fake image content');

        // Start from a valid bilingual record; remove English in form state, not in the DB.
        $article = Article::withoutEvents(fn () => Article::create([
            'title' => ['id' => 'Judul Asli ID', 'en' => 'Original Title'],
            'slug' => ['id' => 'judul-asli-id', 'en' => 'original-title'],
            'shorts' => ['id' => 'Ringkasan ID', 'en' => 'Original summary'],
            'content' => ['id' => '<p>Konten ID</p>', 'en' => '<p>Original content</p>'],
            'tags' => ['id' => 'Artikel Kesehatan', 'en' => 'Health Articles'],
            'author' => 'Dr. Elisabeth',
            'image_path' => 'articles/test.webp',
            'is_active' => true,
        ]));

        Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
            ->set('activeLocale', 'en')
            ->fillForm(['title' => ''])
            ->call('save')
            ->assertHasFormErrors(['title'])
            ->assertSet('activeLocale', 'en');

        // The invalid form must not overwrite the existing English translation.
        $fresh = $article->fresh();
        $this->assertSame('Original Title', $fresh->getTranslation('title', 'en', false));
    }

    public function test_creating_promotion_requires_both_id_and_en(): void
    {
        $this->actAsMarketing();
        $mediaDisk = config('filesystems.media_disk');
        Storage::fake($mediaDisk);

        $file = UploadedFile::fake()->image('promo.jpg', 1200, 1500);

        // Hanya isi ID
        Livewire::test(CreatePromotion::class)
            ->fillForm([
                'title' => 'Promo Medical Checkup ID',
                'slug' => 'promo-mcu-id',
                'shorts' => 'Diskon 20% pemeriksaan MCU.',
                'description' => '<p>Deskripsi promo lengkap ID.</p>',
                'image_path' => $file,
                'confirm_no_end_date' => true,
                'is_active' => true,
            ])
            ->call('create')
            ->assertNotified()
            ->assertSet('activeLocale', 'en');

        $this->assertNull(Promotion::where('slug->id', 'promo-mcu-id')->first());

        // Sekarang lengkapi EN dan simpan
        Livewire::test(CreatePromotion::class)
            ->fillForm([
                'title' => 'Promo Medical Checkup ID',
                'slug' => 'promo-mcu-id',
                'shorts' => 'Diskon 20% pemeriksaan MCU.',
                'description' => '<p>Deskripsi promo lengkap ID.</p>',
                'image_path' => $file,
                'confirm_no_end_date' => true,
                'is_active' => true,
            ])
            ->set('activeLocale', 'en')
            ->fillForm([
                'title' => 'Medical Checkup Promo EN',
                'slug' => 'mcu-promo-en',
                'shorts' => '20% off comprehensive MCU.',
                'description' => '<p>Full promo description EN.</p>',
                'confirm_no_end_date' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $promo = Promotion::where('slug->id', 'promo-mcu-id')->first();
        $this->assertNotNull($promo);
        $this->assertSame('Promo Medical Checkup ID', $promo->getTranslation('title', 'id'));
        $this->assertSame('Medical Checkup Promo EN', $promo->getTranslation('title', 'en'));
        $this->assertSame('promo-mcu-id', $promo->getTranslation('slug', 'id'));
        $this->assertSame('mcu-promo-en', $promo->getTranslation('slug', 'en'));
    }

    public function test_creating_room_facility_requires_both_id_and_en(): void
    {
        $this->actAsMarketing();
        $mediaDisk = config('filesystems.media_disk');
        Storage::fake($mediaDisk);

        $file = UploadedFile::fake()->image('room.jpg', 1920, 1080);

        Livewire::test(CreateRoomFacility::class)
            ->fillForm([
                'name' => 'Kamar Super VIP',
                'slug' => 'kamar-super-vip',
                'tagline' => 'Tagline ruangan terbaik.',
                'description' => 'Deskripsi lengkap kamar super VIP.',
                'image_path' => $file,
                'is_active' => true,
            ])
            ->call('create')
            ->assertNotified()
            ->assertSet('activeLocale', 'en');

        $this->assertNull(RoomFacility::where('slug->id', 'kamar-super-vip')->first());

        Livewire::test(CreateRoomFacility::class)
            ->fillForm([
                'name' => 'Kamar Super VIP',
                'slug' => 'kamar-super-vip',
                'tagline' => 'Tagline ruangan terbaik.',
                'description' => 'Deskripsi lengkap kamar super VIP.',
                'image_path' => $file,
                'is_active' => true,
            ])
            ->set('activeLocale', 'en')
            ->fillForm([
                'name' => 'Super VIP Room',
                'slug' => 'super-vip-room',
                'tagline' => 'Best room tagline.',
                'description' => 'Comprehensive description of super VIP room.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $room = RoomFacility::where('slug->id', 'kamar-super-vip')->first();
        $this->assertNotNull($room);
        $this->assertSame('Kamar Super VIP', $room->getTranslation('name', 'id'));
        $this->assertSame('Super VIP Room', $room->getTranslation('name', 'en'));
    }
}
