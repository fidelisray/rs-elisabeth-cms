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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\SeedsTestRolesAndUsers;
use Tests\TestCase;

class FilamentMultiLocaleValidationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsTestRolesAndUsers;

    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.media_disk' => 'media-test']);
        Storage::fake('media-test');
    }

    private function actAsMarketing(): User
    {
        $this->seedDefaultTestUsersAndRoles();

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
                'title.id' => 'Judul Artikel Hanya ID',
                'slug.id' => 'judul-artikel-hanya-id',
                'tags.id' => 'Artikel Kesehatan',
                'author' => 'Dr. Elisabeth',
                'shorts.id' => 'Ringkasan artikel dalam bahasa Indonesia.',
                'content.id' => '<p>Konten artikel lengkap bahasa Indonesia.</p>',
                'image_path' => $file,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['title.en', 'slug.en', 'tags.en', 'shorts.en', 'content.en'])
            ->assertNotified();

        $this->assertSame($initialCount, Article::count());
    }

    public function test_creating_article_with_both_locales_succeeds(): void
    {
        $this->actAsMarketing();
        Storage::fake('public');

        $file = UploadedFile::fake()->image('thumbnail.jpg', 1920, 1080);

        Livewire::test(CreateArticle::class)
            ->fillForm([
                'title.id' => 'Judul Artikel Lengkap ID',
                'slug.id' => 'judul-artikel-lengkap-id',
                'tags.id' => 'Artikel Kesehatan',
                'shorts.id' => 'Ringkasan artikel dalam bahasa Indonesia.',
                'content.id' => '<p>Konten artikel lengkap bahasa Indonesia.</p>',
                'title.en' => 'Complete Article Title EN',
                'slug.en' => 'complete-article-title-en',
                'tags.en' => 'Health Articles',
                'shorts.en' => 'Short summary of the article in English.',
                'content.en' => '<p>Full content of the article in English.</p>',
                'author' => 'Dr. Elisabeth',
                'image_path' => $file,
                'is_active' => true,
            ])
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
                'title.id' => 'Judul Artikel Medis',
                'slug.id' => 'judul-artikel-medis',
                'tags.id' => 'Artikel Kesehatan',
                'shorts.id' => 'Ringkasan artikel dalam bahasa Indonesia.',
                'content.id' => '<p>Konten artikel lengkap bahasa Indonesia.</p>',
                'title.en' => 'Medical Article Title',
                'tags.en' => 'Health Articles',
                'slug.en' => '',
                'shorts.en' => 'English summary.',
                'content.en' => '<p>English content.</p>',
                'author' => 'Dr. Elisabeth',
                'image_path' => $file,
                'is_active' => true,
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
            ->fillForm(['title.en' => ''])
            ->call('save')
            ->assertHasFormErrors(['title.en']);

        $fresh = $article->fresh();
        $this->assertSame('Original Title', $fresh->getTranslation('title', 'en', false));
    }

    public function test_creating_promotion_requires_both_id_and_en(): void
    {
        $this->actAsMarketing();
        $mediaDisk = config('filesystems.media_disk');
        Storage::fake($mediaDisk);

        $file = UploadedFile::fake()->image('promo.jpg', 1200, 1500);

        Livewire::test(CreatePromotion::class)
            ->fillForm([
                'title.id' => 'Promo Medical Checkup ID',
                'slug.id' => 'promo-mcu-id',
                'shorts.id' => 'Diskon 20% pemeriksaan MCU.',
                'description.id' => '<p>Deskripsi promo lengkap ID.</p>',
                'image_path' => $file,
                'confirm_no_end_date' => true,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['title.en', 'slug.en', 'shorts.en', 'description.en'])
            ->assertNotified();

        $this->assertNull(Promotion::where('slug->id', 'promo-mcu-id')->first());

        Livewire::test(CreatePromotion::class)
            ->fillForm([
                'title.id' => 'Promo Medical Checkup ID',
                'slug.id' => 'promo-mcu-id',
                'shorts.id' => 'Diskon 20% pemeriksaan MCU.',
                'description.id' => '<p>Deskripsi promo lengkap ID.</p>',
                'title.en' => 'Medical Checkup Promo EN',
                'slug.en' => 'mcu-promo-en',
                'shorts.en' => '20% off comprehensive MCU.',
                'description.en' => '<p>Full promo description EN.</p>',
                'image_path' => $file,
                'confirm_no_end_date' => true,
                'is_active' => true,
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
                'name.id' => 'Kamar Super VIP',
                'slug.id' => 'kamar-super-vip',
                'tagline.id' => 'Tagline ruangan terbaik.',
                'description.id' => 'Deskripsi lengkap kamar super VIP.',
                'image_path' => $file,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['name.en', 'slug.en', 'tagline.en', 'description.en'])
            ->assertNotified();

        $this->assertNull(RoomFacility::where('slug->id', 'kamar-super-vip')->first());

        Livewire::test(CreateRoomFacility::class)
            ->fillForm([
                'name.id' => 'Kamar Super VIP',
                'slug.id' => 'kamar-super-vip',
                'tagline.id' => 'Tagline ruangan terbaik.',
                'description.id' => 'Deskripsi lengkap kamar super VIP.',
                'name.en' => 'Super VIP Room',
                'slug.en' => 'super-vip-room',
                'tagline.en' => 'Best room tagline.',
                'description.en' => 'Comprehensive description of super VIP room.',
                'image_path' => $file,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $room = RoomFacility::where('slug->id', 'kamar-super-vip')->first();
        $this->assertNotNull($room);
        $this->assertSame('Kamar Super VIP', $room->getTranslation('name', 'id'));
        $this->assertSame('Super VIP Room', $room->getTranslation('name', 'en'));
    }
}
