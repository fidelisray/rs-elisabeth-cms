<?php

namespace Tests\Feature\Api\Cms;

use App\Filament\Resources\BannerPromotions\Pages\ListBannerPromotions;
use App\Jobs\SendCacheInvalidationWebhookJob;
use App\Models\Article;
use App\Models\BannerPromotion;
use App\Models\FacilityService;
use App\Models\News;
use App\Models\Promotion;
use App\Models\RoomFacility;
use App\Models\User;
use App\Support\ApiCacheInvalidator;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\GeneratesHmacHeaders;

class LocaleCacheIsolationTest extends TestCase
{
    use GeneratesHmacHeaders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        config(['filesystems.media_disk' => 'media-test']);
        Storage::fake('media-test');
        $this->travelTo(Carbon::parse('2026-10-21 12:00:00'));
    }

    public static function modules(): array
    {
        return [
            'news' => ['news', News::class, 'news', 'title', 'is_published', true],
            'articles' => ['articles', Article::class, 'articles', 'title', 'is_active', true],
            'promotions' => ['promotions', Promotion::class, 'promotions', 'title', 'is_active', true],
            'facilities' => ['facilities', FacilityService::class, 'facility_services', 'name', 'is_active', false],
            'rooms' => ['room-facilities', RoomFacility::class, 'room_facilities', 'name', 'is_active', false],
            'banners' => ['banner-promotions', BannerPromotion::class, 'banner_promotions', 'title', 'is_active', false],
        ];
    }

    public static function paginatedModules(): array
    {
        return array_intersect_key(self::modules(), array_flip(['news', 'articles', 'promotions']));
    }

    public static function storesAndModules(): array
    {
        $cases = [];
        foreach (self::modules() as $name => $case) {
            foreach (['array', 'database'] as $store) {
                $cases[$name.'-'.$store] = [...$case, $store];
            }
        }

        return $cases;
    }

    private function seedRecord(string $model, string $field, string $active)
    {
        return $model::factory()->create([
            $field => ['id' => 'Konten Indonesia', 'en' => 'English Content'], $active => true,
            ...($model === Promotion::class ? ['start_date' => null, 'end_date' => null] : []),
        ]);
    }

    private function dataKey(string $prefix, string $locale, bool $paginated, int $page = 1): string
    {
        if (! $paginated) {
            return 'rs_web_cms_api_'.$prefix.'_'.$locale;
        }

        $version = Cache::get('rs_web_cms_api_'.$prefix.'_version');

        return 'rs_web_cms_api_'.$prefix.'_'.$locale.'_v'.$version
            .($prefix === 'promotions' ? '_'.Promotion::today() : '').'_page_'.$page;
    }

    private function requestList(string $path, string $locale, string $suffix = '')
    {
        return $this->withHmac()->getJson('/api/v1/cms/'.$path.'?lang='.$locale.$suffix)
            ->assertOk()->assertHeader('Content-Language', $locale);
    }

    #[DataProvider('storesAndModules')]
    public function test_locale_partitions_and_warm_cache_survive_real_serialization(string $path, string $model, string $prefix, string $field, string $active, bool $paginated, string $store): void
    {
        Cache::setDefaultDriver($store);
        $record = $this->seedRecord($model, $field, $active);

        foreach (['en', 'id'] as $locale) {
            $this->requestList($path, $locale)->assertJsonPath('data.0.'.$field, $record->getTranslation($field, $locale, false));
            $this->assertTrue(Cache::has($this->dataKey($prefix, $locale, $paginated)));
        }

        $this->assertNotSame($this->dataKey($prefix, 'id', $paginated), $this->dataKey($prefix, 'en', $paginated));
        $cached = Cache::get($this->dataKey($prefix, 'en', $paginated));
        $this->assertInstanceOf($paginated ? LengthAwarePaginator::class : Collection::class, $cached);
        $this->assertEqualsCanonicalizing(['id', 'en'], array_keys($cached->first()->getTranslations($field)));

        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            foreach (['id', 'en', 'id', 'en'] as $locale) {
                $this->requestList($path, $locale)->assertJsonPath('data.0.'.$field, $record->getTranslation($field, $locale, false));
            }
            $contentQueries = array_filter(DB::getQueryLog(), fn ($entry) => str_contains($entry['query'], 'from "'.$record->getTable().'"'));
            $this->assertSame([], array_values($contentQueries), 'Warm cache must not query the content table.');
        } finally {
            DB::disableQueryLog();
        }
    }

    #[DataProvider('modules')]
    public function test_each_mutation_invalidates_all_locales_without_touching_other_modules(string $path, string $model, string $prefix, string $field, string $active, bool $paginated): void
    {
        $record = $this->seedRecord($model, $field, $active);
        Cache::put('rs_web_cms_api_unrelated_id', 'unrelated');
        foreach (['create', 'update', 'delete', 'restore', 'deactivate'] as $operation) {
            $this->requestList($path, 'id');
            $this->requestList($path, 'en');
            $old = [];
            foreach (['id', 'en'] as $locale) {
                $old[$locale] = $this->dataKey($prefix, $locale, $paginated);
                $this->assertTrue(Cache::has($old[$locale]));
            }

            match ($operation) {
                'create' => $this->seedRecord($model, $field, $active),
                'update' => $record->setTranslation($field, 'en', 'Updated English Content')->save(),
                'delete' => $record->delete(),
                'restore' => $record->restore(),
                'deactivate' => $record->update([$active => false]),
            };

            foreach (['id', 'en'] as $locale) {
                $key = $this->dataKey($prefix, $locale, $paginated);
                if ($paginated) {
                    $this->assertNotSame($old[$locale], $key, 'Mutations in the same second must still change the version.');
                }
                $this->assertFalse(Cache::has($key));
                $response = $this->requestList($path, $locale);
                $ids = array_column($response->json('data'), 'id');
                if (in_array($operation, ['delete', 'deactivate'], true)) {
                    $this->assertNotContains($record->getKey(), $ids);
                } else {
                    $this->assertContains($record->getKey(), $ids);
                }
            }
            $this->assertSame('unrelated', Cache::get('rs_web_cms_api_unrelated_id'));
        }
    }

    #[DataProvider('paginatedModules')]
    public function test_pages_and_links_remain_localized_and_cached_path_is_not_reused_between_hosts(string $path, string $model, string $prefix, string $field, string $active, bool $paginated): void
    {
        $model::factory()->count(14)->create([
            $active => true,
            ...($model === Promotion::class ? ['start_date' => null, 'end_date' => null] : []),
        ]);

        foreach (['id', 'en'] as $locale) {
            $page1 = $this->withHmac()->getJson('http://first.example.test/api/v1/cms/'.$path.'?lang='.$locale)->assertOk();
            $page2 = $this->requestList($path, $locale, '&page=2');
            $page1->assertJsonCount(12, 'data')->assertJsonPath('meta.current_page', 1);
            $page2->assertJsonCount(2, 'data')->assertJsonPath('meta.current_page', 2);
            $this->assertTrue(Cache::has($this->dataKey($prefix, $locale, true, 1)));
            $this->assertTrue(Cache::has($this->dataKey($prefix, $locale, true, 2)));
            parse_str(parse_url($page1->json('links.next'), PHP_URL_QUERY), $query);
            $this->assertSame($locale, $query['lang'] ?? null);

            $warm = $this->withHmac()->getJson('http://second.example.test/api/v1/cms/'.$path.'?lang='.$locale)->assertOk();
            $this->assertSame('second.example.test', parse_url($warm->json('links.next'), PHP_URL_HOST));
            $this->assertSame('first.example.test', parse_url(Cache::get($this->dataKey($prefix, $locale, true))->path(), PHP_URL_HOST));
        }
    }

    #[DataProvider('paginatedModules')]
    public function test_mutation_rotates_every_page_of_both_locales_within_the_same_clock_tick(string $path, string $model, string $prefix, string $field, string $active, bool $paginated): void
    {
        $records = $model::factory()->count(14)->sequence(fn ($sequence) => ['created_at' => now()->subMinutes($sequence->index)])
            ->create([$active => true, ...($model === Promotion::class ? ['start_date' => null, 'end_date' => null] : [])]);
        $previous = [];
        foreach (['id', 'en'] as $locale) {
            foreach ([1, 2] as $page) {
                $this->requestList($path, $locale, '&page='.$page);
                $previous[$locale][$page] = $this->dataKey($prefix, $locale, true, $page);
                $this->assertTrue(Cache::has($previous[$locale][$page]));
            }
        }
        $oldVersion = Cache::get('rs_web_cms_api_'.$prefix.'_version');
        $records->last()->setTranslation($field, 'en', 'Updated last page')->save();
        $newVersion = Cache::get('rs_web_cms_api_'.$prefix.'_version');
        $this->assertTrue(Str::isUuid($newVersion));
        $this->assertNotSame($oldVersion, $newVersion);

        foreach (['id', 'en'] as $locale) {
            foreach ([1, 2] as $page) {
                $current = $this->dataKey($prefix, $locale, true, $page);
                $this->assertNotSame($previous[$locale][$page], $current);
                $this->assertFalse(Cache::has($current));
            }
        }
        $this->requestList($path, 'en', '&page=2')->assertJsonPath('data.1.'.$field, 'Updated last page');
    }

    #[DataProvider('modules')]
    public function test_explicit_invalidation_covers_configured_locales_and_preserves_other_modules(string $path, string $model, string $prefix, string $field, string $active, bool $paginated): void
    {
        config(['app.supported_locales' => ['id', 'en', 'fr']]);
        $module = array_search($prefix, ['news' => 'news', 'articles' => 'articles', 'promotions' => 'promotions', 'facilities' => 'facility_services', 'room_facilities' => 'room_facilities', 'banner_promotions' => 'banner_promotions'], true);
        Cache::put('rs_web_cms_api_other_en', 'other');
        $before = [];
        foreach (['id', 'en', 'fr'] as $locale) {
            $before[$locale] = $this->dataKey($prefix, $locale, $paginated);
            if ($paginated) {
                $before[$locale] = ApiCacheInvalidator::pageKeyForModule($module, 1, $locale, $prefix === 'promotions' ? Promotion::today() : null);
            }
            Cache::put($before[$locale], 'old data');
        }

        Bus::fake();
        ApiCacheInvalidator::forModule($module);

        foreach (['id', 'en', 'fr'] as $locale) {
            $current = $this->dataKey($prefix, $locale, $paginated);
            if ($paginated) {
                $this->assertNotSame($before[$locale], $current);
            }
            $this->assertFalse(Cache::has($current));
        }
        $this->assertSame('other', Cache::get('rs_web_cms_api_other_en'));
        Bus::assertDispatchedTimes(SendCacheInvalidationWebhookJob::class, 1);
        Bus::assertDispatched(SendCacheInvalidationWebhookJob::class, fn ($job) => $job->module === $module);
    }

    public function test_midnight_rotates_promotion_cache_for_both_languages(): void
    {
        $this->travelTo(Carbon::parse('2026-10-21 23:59:00', config('app.timezone')));
        $expired = Promotion::factory()->create(['is_active' => true, 'start_date' => null, 'end_date' => '2026-10-21']);
        $starting = Promotion::factory()->create(['is_active' => true, 'start_date' => '2026-10-22', 'end_date' => null]);

        foreach (['id', 'en'] as $locale) {
            $this->requestList('promotions', $locale)->assertJsonPath('data.0.id', $expired->id)->assertJsonCount(1, 'data');
            $this->assertStringContainsString('2026-10-21', $this->dataKey('promotions', $locale, true));
        }

        $this->travelTo(Carbon::parse('2026-10-22 00:01:00', config('app.timezone')));

        foreach (['id', 'en'] as $locale) {
            $this->requestList('promotions', $locale)->assertJsonPath('data.0.id', $starting->id)->assertJsonCount(1, 'data');
            $this->assertStringContainsString('2026-10-22', $this->dataKey('promotions', $locale, true));
        }
    }

    #[DataProvider('modules')]
    public function test_cache_ttl_is_preserved_for_each_module(string $path, string $model, string $prefix, string $field, string $active, bool $paginated): void
    {
        $record = $this->seedRecord($model, $field, $active);
        $this->requestList($path, 'en')->assertJsonPath('data.0.'.$field, 'English Content');
        DB::table($record->getTable())->where('id', $record->getKey())->update([
            $field => json_encode(['id' => 'Nilai Baru', 'en' => 'New Value'], JSON_THROW_ON_ERROR),
        ]);

        $this->requestList($path, 'en')->assertJsonPath('data.0.'.$field, 'English Content');
        $this->travel(($paginated ? 2 : 6) * 60 * 60 + 1)->seconds();
        $this->requestList($path, 'en')->assertJsonPath('data.0.'.$field, 'New Value');
    }

    public function test_banner_reorder_clears_every_locale_and_emits_one_webhook(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'marketing@rs-elisabeth.com')->firstOrFail();
        $user->update(['must_change_password' => false]);
        $this->actingAs($user);
        $first = $this->seedRecord(BannerPromotion::class, 'title', 'is_active');
        $second = $this->seedRecord(BannerPromotion::class, 'title', 'is_active');
        foreach (['id', 'en'] as $locale) {
            $this->requestList('banner-promotions', $locale);
            $this->assertTrue(Cache::has($this->dataKey('banner_promotions', $locale, false)));
        }
        Bus::fake();
        Livewire::test(ListBannerPromotions::class)->call('reorderTable', [$second->id, $first->id]);
        foreach (['id', 'en'] as $locale) {
            $this->assertFalse(Cache::has($this->dataKey('banner_promotions', $locale, false)));
            $this->requestList('banner-promotions', $locale)->assertJsonPath('data.0.id', $second->id);
        }
        Bus::assertDispatchedTimes(SendCacheInvalidationWebhookJob::class, 1);
    }
}
