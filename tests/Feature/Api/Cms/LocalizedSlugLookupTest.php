<?php

namespace Tests\Feature\Api\Cms;

use App\Models\Article;
use App\Models\BannerPromotion;
use App\Models\FacilityService;
use App\Models\News;
use App\Models\Promotion;
use App\Models\RoomFacility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\GeneratesHmacHeaders;

class LocalizedSlugLookupTest extends TestCase
{
    use GeneratesHmacHeaders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        config(['filesystems.media_disk' => 'media-test']);
        Storage::fake('media-test');
    }

    public static function detailModules(): array
    {
        return [
            'news' => ['news', News::class, 'title', 'is_published'],
            'articles' => ['articles', Article::class, 'title', 'is_active'],
            'promotions' => ['promotions', Promotion::class, 'title', 'is_active'],
            'facilities' => ['facilities', FacilityService::class, 'name', 'is_active'],
            'rooms' => ['room-facilities', RoomFacility::class, 'name', 'is_active'],
        ];
    }

    public static function allModules(): array
    {
        return [...self::detailModules(), 'banners' => ['banner-promotions', BannerPromotion::class, 'title', 'is_active']];
    }

    public static function numericModules(): array
    {
        return array_intersect_key(self::detailModules(), array_flip(['articles', 'promotions']));
    }

    private function record(string $model, string $titleField, string $activeField, array $overrides = [])
    {
        return $model::factory()->create([
            $titleField => ['id' => 'Judul Indonesia', 'en' => 'English Title'],
            'slug' => ['id' => 'judul-indonesia', 'en' => 'english-title'],
            $activeField => true,
            ...$overrides,
        ]);
    }

    #[DataProvider('detailModules')]
    public function test_detail_matches_only_the_requested_language(string $path, string $model, string $titleField, string $activeField): void
    {
        $record = $this->record($model, $titleField, $activeField);

        foreach (['id' => 'judul-indonesia', 'en' => 'english-title'] as $locale => $slug) {
            $this->withHmac()->getJson('/api/v1/cms/'.$path.'/'.$slug.'?lang='.$locale)
                ->assertOk()->assertHeader('Content-Language', $locale)
                ->assertJsonPath('data.id', $record->getKey())
                ->assertJsonPath('data.'.$titleField, $record->getTranslation($titleField, $locale, false))
                ->assertJsonPath('data.slug', $slug)
                ->assertJsonPath('data.alternate_slugs', ['id' => 'judul-indonesia', 'en' => 'english-title']);
        }
    }

    #[DataProvider('detailModules')]
    public function test_slug_from_the_other_language_is_not_found(string $path, string $model, string $titleField, string $activeField): void
    {
        $this->record($model, $titleField, $activeField);

        $this->withHmac()->getJson('/api/v1/cms/'.$path.'/judul-indonesia?lang=en')
            ->assertNotFound()->assertJsonPath('success', false)->assertJsonPath('status_code', 404);
        $this->withHmac()->getJson('/api/v1/cms/'.$path.'/english-title?lang=id')
            ->assertNotFound()->assertJsonPath('success', false)->assertJsonPath('status_code', 404);
    }

    #[DataProvider('detailModules')]
    public function test_cross_locale_collision_resolves_the_exact_record(string $path, string $model, string $titleField, string $activeField): void
    {
        $first = $this->record($model, $titleField, $activeField, ['slug' => ['id' => 'shared-slug', 'en' => 'first-en']]);
        $second = $this->record($model, $titleField, $activeField, ['slug' => ['id' => 'second-id', 'en' => 'shared-slug']]);

        $this->withHmac()->getJson('/api/v1/cms/'.$path.'/shared-slug?lang=en')
            ->assertOk()->assertJsonPath('data.id', $second->getKey())
            ->assertJsonPath('data.alternate_slugs', ['id' => 'second-id', 'en' => 'shared-slug']);
        $this->withHmac()->getJson('/api/v1/cms/'.$path.'/shared-slug?lang=id')
            ->assertOk()->assertJsonPath('data.id', $first->getKey())
            ->assertJsonPath('data.alternate_slugs', ['id' => 'shared-slug', 'en' => 'first-en']);
    }

    #[DataProvider('detailModules')]
    public function test_locale_query_and_headers_follow_the_documented_precedence(string $path, string $model, string $titleField, string $activeField): void
    {
        $record = $this->record($model, $titleField, $activeField);
        $base = '/api/v1/cms/'.$path.'/';

        $this->withHmac()->getJson($base.'english-title?lang=en&locale=id')
            ->assertOk()->assertHeader('Content-Language', 'en')->assertJsonPath('data.id', $record->getKey());
        $this->withHmac()->getJson($base.'english-title?locale=en')
            ->assertOk()->assertHeader('Content-Language', 'en');
        $this->withHmac()->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])->getJson($base.'english-title')
            ->assertOk()->assertHeader('Content-Language', 'en');
        $this->withHmac()->withHeaders(['Accept-Language' => ''])->getJson($base.'judul-indonesia')
            ->assertOk()->assertHeader('Content-Language', 'id');
    }

    #[DataProvider('detailModules')]
    public function test_unsupported_locale_falls_back_to_indonesian_not_to_another_slug(string $path, string $model, string $titleField, string $activeField): void
    {
        $this->record($model, $titleField, $activeField);

        $this->withHmac()->withHeaders(['Accept-Language' => 'en'])->getJson('/api/v1/cms/'.$path.'/judul-indonesia?lang=de')
            ->assertOk()->assertHeader('Content-Language', 'id')->assertJsonPath('data.slug', 'judul-indonesia');
        $this->withHmac()->getJson('/api/v1/cms/'.$path.'/english-title?lang=de')->assertNotFound();
    }

    #[DataProvider('detailModules')]
    public function test_language_selection_cannot_expose_inactive_or_deleted_records(string $path, string $model, string $titleField, string $activeField): void
    {
        $record = $this->record($model, $titleField, $activeField, [$activeField => false]);

        foreach (['id' => 'judul-indonesia', 'en' => 'english-title'] as $locale => $slug) {
            $this->withHmac()->getJson('/api/v1/cms/'.$path.'/'.$slug.'?lang='.$locale)->assertNotFound();
        }

        $record->update([$activeField => true]);
        $record->delete();

        foreach (['id' => 'judul-indonesia', 'en' => 'english-title'] as $locale => $slug) {
            $this->withHmac()->getJson('/api/v1/cms/'.$path.'/'.$slug.'?lang='.$locale)->assertNotFound();
        }
    }

    #[DataProvider('numericModules')]
    public function test_numeric_id_lookup_remains_available_in_both_languages(string $path, string $model, string $titleField, string $activeField): void
    {
        $record = $this->record($model, $titleField, $activeField);

        foreach (['id', 'en'] as $locale) {
            $this->withHmac()->getJson('/api/v1/cms/'.$path.'/'.$record->getKey().'?lang='.$locale)
                ->assertOk()->assertJsonPath('data.id', $record->getKey())
                ->assertJsonPath('data.slug', $record->getTranslation('slug', $locale, false));
        }
    }

    #[DataProvider('numericModules')]
    public function test_numeric_slugs_do_not_override_the_existing_id_lookup_contract(string $path, string $model, string $titleField, string $activeField): void
    {
        $record = $this->record($model, $titleField, $activeField, ['slug' => ['id' => '900000000', 'en' => '900000001']]);

        $this->withHmac()->getJson('/api/v1/cms/'.$path.'/900000000?lang=id')->assertNotFound();
        $this->withHmac()->getJson('/api/v1/cms/'.$path.'/900000001?lang=en')->assertNotFound();
        $this->withHmac()->getJson('/api/v1/cms/'.$path.'/'.$record->getKey().'?lang=en')
            ->assertOk()->assertJsonPath('data.id', $record->getKey());
    }

    #[DataProvider('allModules')]
    public function test_list_responses_include_original_alternate_slugs_for_both_languages(string $path, string $model, string $titleField, string $activeField): void
    {
        $this->record($model, $titleField, $activeField);

        foreach (['id', 'en'] as $locale) {
            $this->withHmac()->getJson('/api/v1/cms/'.$path.'?lang='.$locale)
                ->assertOk()->assertJsonPath('data.0.alternate_slugs', ['id' => 'judul-indonesia', 'en' => 'english-title']);
        }
    }

    #[DataProvider('allModules')]
    public function test_alternate_slugs_do_not_invent_a_translation_on_an_unsaved_model(string $path, string $model, string $titleField, string $activeField): void
    {
        // Partial models are not persisted: PostgreSQL rightly forbids incomplete stored slugs.
        $record = new $model(['slug' => ['id' => 'indonesian-only']]);
        $this->assertSame(['id' => 'indonesian-only'], $record->getAlternateSlugs());

        $record = new $model(['slug' => ['en' => 'english-only']]);
        $this->assertSame(['en' => 'english-only'], $record->getAlternateSlugs());
    }

    #[DataProvider('allModules')]
    public function test_scope_honours_explicit_supported_locale_and_rejects_unknown_locale(string $path, string $model, string $titleField, string $activeField): void
    {
        $record = $this->record($model, $titleField, $activeField);
        app()->setLocale('id');

        $this->assertSame($record->getKey(), $model::query()->whereTranslatedSlug('english-title', 'en')->firstOrFail()->getKey());
        $this->assertNull($model::query()->whereTranslatedSlug('judul-indonesia', 'en')->first());
        $this->assertNull($model::query()->whereTranslatedSlug('judul-indonesia', 'de')->first());
    }
}
