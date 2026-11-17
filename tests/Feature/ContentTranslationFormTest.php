<?php

namespace Tests\Feature;

use App\Filament\Resources\FacilityServices\Pages\CreateFacilityService;
use App\Filament\Resources\News\Pages\ListNews;
use App\Models\FacilityService;
use App\Models\News;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ContentTranslationFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.media_disk' => 'media-test']);
        Storage::fake('media-test');
        $this->seed(DatabaseSeeder::class);
        $marketing = User::where('email', 'marketing@rs-elisabeth.com')->firstOrFail();
        $marketing->update(['must_change_password' => false]);
        $this->actingAs($marketing);
    }

    public static function modules(): array
    {
        return [
            'news' => ['News', 'News', 'title', 'content', ['shorts' => 'Ringkasan', 'category' => 'Kategori bebas baru', 'author' => 'Editorial']],
            'articles' => ['Articles', 'Article', 'title', 'content', ['shorts' => 'Ringkasan', 'tags' => 'Kategori bebas baru', 'author' => 'Editorial']],
            'promotions' => ['Promotions', 'Promotion', 'title', 'description', ['shorts' => 'Ringkasan', 'confirm_no_end_date' => true]],
            'facilities' => ['FacilityServices', 'FacilityService', 'name', 'description', ['short_description' => 'Ringkasan', 'category' => 'Layanan Medis']],
            'rooms' => ['RoomFacilities', 'RoomFacility', 'name', 'description', ['tagline' => 'Tagline']],
            'banners' => ['BannerPromotions', 'BannerPromotion', 'title', null, []],
        ];
    }

    private function states(string $titleField, ?string $bodyField, array $extra): array
    {
        $id = ["{$titleField}.id" => 'Konten Indonesia', 'slug.id' => 'konten-indonesia'];
        $en = ["{$titleField}.en" => 'English Content', 'slug.en' => 'english-content'];

        if ($bodyField) {
            $id["{$bodyField}.id"] = '<p>Konten Indonesia</p>';
            $en["{$bodyField}.en"] = '<p>English content</p>';
        }

        foreach (['shorts', 'tags', 'tagline', 'short_description'] as $field) {
            if (array_key_exists($field, $extra)) {
                $id["{$field}.id"] = $extra[$field];
                $en["{$field}.en"] = 'English '.$field;
            }
        }

        if (array_key_exists('category', $extra)) {
            if ($titleField === 'title') {
                $id['category.id'] = $extra['category'];
                $en['category.en'] = 'New free-text category';
            } else {
                $id['category'] = $extra['category'];
            }
        }

        foreach (['author', 'confirm_no_end_date'] as $sharedField) {
            if (array_key_exists($sharedField, $extra)) {
                $id[$sharedField] = $extra[$sharedField];
            }
        }

        $id['image_path'] = UploadedFile::fake()->image('content.png', 640, 640);

        return [$id, $en];
    }

    #[DataProvider('modules')]
    public function test_create_rejects_incomplete_language_and_saves_complete_translations(string $resource, string $model, string $titleField, ?string $bodyField, array $extra): void
    {
        $page = "App\\Filament\\Resources\\{$resource}\\Pages\\Create{$model}";
        $modelClass = "App\\Models\\{$model}";
        [$id, $en] = $this->states($titleField, $bodyField, $extra);

        $component = Livewire::test($page)->fillForm($id)->call('create')
            ->assertHasFormErrors(["{$titleField}.en", 'slug.en'])
            ->assertNotified();

        $this->assertSame(0, $modelClass::count());

        $component->fillForm($en)->call('create')->assertHasNoFormErrors();
        $record = $modelClass::query()->sole();
        $this->assertSame('Konten Indonesia', $record->getTranslation($titleField, 'id', false));
        $this->assertSame('English Content', $record->getTranslation($titleField, 'en', false));

        foreach (['tags', 'category'] as $field) {
            if ($record->isTranslatableAttribute($field)) {
                $this->assertSame($id["{$field}.id"], $record->getTranslation($field, 'id', false));
                $this->assertSame($en["{$field}.en"], $record->getTranslation($field, 'en', false));
            }
        }
    }

    #[DataProvider('modules')]
    public function test_edit_cannot_remove_required_english_translation(string $resource, string $model, string $titleField, ?string $bodyField, array $extra): void
    {
        $page = "App\\Filament\\Resources\\{$resource}\\Pages\\Edit{$model}";
        $modelClass = "App\\Models\\{$model}";
        $record = $modelClass::factory()->create(['image_path' => 'content/test.webp']);
        Storage::disk('media-test')->put('content/test.webp', 'test image');
        $original = $record->getTranslations($titleField);

        Livewire::test($page, ['record' => $record->getRouteKey()])
            ->fillForm(["{$titleField}.en" => ''])
            ->call('save')
            ->assertHasFormErrors(["{$titleField}.en"]);

        $this->assertEquals($original, $record->fresh()->getTranslations($titleField));

        Livewire::test($page, ['record' => $record->getRouteKey()])
            ->fillForm(["{$titleField}.en" => 'Updated English Content'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($original['id'], $record->fresh()->getTranslation($titleField, 'id', false));
        $this->assertSame('Updated English Content', $record->fresh()->getTranslation($titleField, 'en', false));
    }

    public function test_news_free_text_category_filter_uses_the_selected_language(): void
    {
        $included = News::factory()->create(['category' => ['id' => 'Pelayanan Pasien', 'en' => 'Patient Care']]);
        $excluded = News::factory()->create(['category' => ['id' => 'Edukasi', 'en' => 'Education']]);

        Livewire::test(ListNews::class)
            ->filterTable('category', 'Pelayanan Pasien')
            ->assertCanSeeTableRecords([$included])
            ->assertCanNotSeeTableRecords([$excluded]);

        Livewire::test(ListNews::class)
            ->set('activeLocale', 'en')
            ->filterTable('category', 'Patient Care')
            ->assertCanSeeTableRecords([$included])
            ->assertCanNotSeeTableRecords([$excluded]);
    }

    public function test_optional_whatsapp_text_requires_both_languages_when_used(): void
    {
        [$id, $en] = $this->states('name', 'description', ['short_description' => 'Ringkasan', 'category' => 'Layanan Medis']);
        $id['wa_link_text.id'] = 'Hubungi Kami';
        $id['wa_prefilled_message.id'] = 'Mohon informasi layanan';

        $component = Livewire::test(CreateFacilityService::class)
            ->fillForm([...$id, ...$en])->call('create')
            ->assertNotified();

        $this->assertSame(0, FacilityService::count());

        $component->fillForm(['wa_link_text.en' => 'Contact Us', 'wa_prefilled_message.en' => 'Please send service information'])
            ->call('create')->assertHasNoFormErrors();

        $record = FacilityService::query()->sole();
        $this->assertSame('Hubungi Kami', $record->getTranslation('wa_link_text', 'id', false));
        $this->assertSame('Contact Us', $record->getTranslation('wa_link_text', 'en', false));
    }
}
