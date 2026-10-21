<?php

namespace Tests\Feature\Api\Cms;

use App\Filament\Resources\FacilityServices\Pages\ListFacilityServices;
use App\Filament\Resources\RoomFacilities\Pages\ListRoomFacilities;
use App\Jobs\SendCacheInvalidationWebhookJob;
use App\Models\FacilityService;
use App\Models\RoomFacility;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\GeneratesHmacHeaders;

class LocalizedListSortingTest extends TestCase
{
    use GeneratesHmacHeaders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        config(['filesystems.media_disk' => 'media-test']);
        Storage::fake('media-test');
    }

    public static function modules(): array
    {
        return [
            'facilities' => [FacilityService::class, 'facilities', 'rs_web_cms_api_facility_services', ListFacilityServices::class],
            'rooms' => [RoomFacility::class, 'room-facilities', 'rs_web_cms_api_room_facilities', ListRoomFacilities::class],
        ];
    }

    private function records(string $model): array
    {
        return [
            $model::factory()->create(['name' => ['id' => 'Alpha Indonesia', 'en' => 'Zulu English'], 'sort_order' => 10, 'is_active' => true]),
            $model::factory()->create(['name' => ['id' => 'Zulu Indonesia', 'en' => 'Alpha English'], 'sort_order' => 10, 'is_active' => true]),
        ];
    }

    private function order(string $path, string $locale): array
    {
        return array_column($this->withHmac()->getJson('/api/v1/cms/'.$path.'?lang='.$locale)
            ->assertOk()->assertHeader('Content-Language', $locale)->json('data'), 'id');
    }

    #[DataProvider('modules')]
    public function test_sort_order_has_priority_and_ties_use_the_requested_language_even_on_cache_hits(string $model, string $path, string $cache, string $page): void
    {
        [$first, $second] = $this->records($model);
        $priority = $model::factory()->create(['name' => ['id' => 'Priority', 'en' => 'Priority EN'], 'sort_order' => 1, 'is_active' => true]);
        $model::factory()->create(['sort_order' => 0, 'is_active' => false]);
        $deleted = $model::factory()->create(['sort_order' => -1, 'is_active' => true]);
        $deleted->delete();

        $this->assertSame([$priority->id, $first->id, $second->id], $this->order($path, 'id'));
        $this->assertSame([$priority->id, $second->id, $first->id], $this->order($path, 'en'));
        $this->assertTrue(Cache::has($cache.'_id'));
        $this->assertTrue(Cache::has($cache.'_en'));

        DB::enableQueryLog();
        DB::flushQueryLog();

        try {
            $this->assertSame([$priority->id, $second->id, $first->id], $this->order($path, 'en'));
            $this->assertSame([$priority->id, $first->id, $second->id], $this->order($path, 'id'));
            $this->assertSame([], DB::getQueryLog(), 'Warm locale caches should not execute database queries.');
        } finally {
            DB::disableQueryLog();
        }
    }

    #[DataProvider('modules')]
    public function test_equal_translated_names_use_id_as_a_stable_tiebreaker(string $model, string $path, string $cache, string $page): void
    {
        $first = $model::factory()->create(['name' => ['id' => 'Sama', 'en' => 'Same'], 'sort_order' => 5, 'is_active' => true]);
        $second = $model::factory()->create(['name' => ['id' => 'Sama', 'en' => 'Same'], 'sort_order' => 5, 'is_active' => true]);

        foreach (['id', 'en'] as $locale) {
            $this->assertSame([$first->id, $second->id], $this->order($path, $locale));
        }
    }

    #[DataProvider('modules')]
    public function test_update_delete_and_restore_clear_both_locale_caches(string $model, string $path, string $cache, string $page): void
    {
        [$first, $second] = $this->records($model);
        Cache::put('rs_web_cms_api_banner_promotions', 'unrelated');

        foreach (['update', 'delete', 'restore'] as $operation) {
            $this->order($path, 'id');
            $this->order($path, 'en');
            $this->assertTrue(Cache::has($cache.'_id'));
            $this->assertTrue(Cache::has($cache.'_en'));

            match ($operation) {
                'update' => $first->setTranslation('name', 'en', 'Aardvark English')->save(),
                'delete' => $first->delete(),
                'restore' => $first->restore(),
            };

            $this->assertFalse(Cache::has($cache.'_id'));
            $this->assertFalse(Cache::has($cache.'_en'));
            $this->assertSame('unrelated', Cache::get('rs_web_cms_api_banner_promotions'));
        }
    }

    #[DataProvider('modules')]
    public function test_actual_reorder_invalidates_both_locales_and_sends_one_webhook(string $model, string $path, string $cache, string $page): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'marketing@rs-elisabeth.com')->firstOrFail();
        $user->update(['must_change_password' => false]);
        $this->actingAs($user);
        [$first, $second] = $this->records($model);
        $this->order($path, 'id');
        $this->order($path, 'en');
        Bus::fake();

        Livewire::test($page)->call('reorderTable', [$second->id, $first->id]);

        $this->assertFalse(Cache::has($cache.'_id'));
        $this->assertFalse(Cache::has($cache.'_en'));
        Bus::assertDispatchedTimes(SendCacheInvalidationWebhookJob::class, 1);

        foreach (['id', 'en'] as $locale) {
            $this->assertSame([$second->id, $first->id], $this->order($path, $locale));
        }
    }
}
