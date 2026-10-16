<?php

namespace Tests\Feature;

use App\Filament\Resources\BannerPromotions\Pages\ListBannerPromotions;
use App\Filament\Resources\FacilityServices\Pages\ListFacilityServices;
use App\Filament\Resources\RoomFacilities\Pages\ListRoomFacilities;
use App\Jobs\SendCacheInvalidationWebhookJob;
use App\Models\BannerPromotion;
use App\Models\FacilityService;
use App\Models\RoomFacility;
use App\Models\User;
use App\Support\ApiCacheInvalidator;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\GeneratesHmacHeaders;

/**
 * MVP-2.4: drag-and-drop memakai query massal sehingga tidak memicu event model.
 * Tanpa invalidasi eksplisit, urutan baru baru tampil di web setelah TTL cache (6 jam).
 */
class ReorderCacheInvalidationTest extends TestCase
{
    use GeneratesHmacHeaders, RefreshDatabase;

    /**
     * Hanya role dengan permission Reorder yang boleh drag-and-drop. Saat ini hanya
     * super_admin (marketing tidak memegang Reorder), jadi pengujian memakai super_admin.
     */
    private function actAsReorderer(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->artisan('app:super-admin:init', ['--email' => 'admin@rs-elisabeth.com'])->assertExitCode(0);

        $user = User::where('email', 'admin@rs-elisabeth.com')->first();
        $user->update(['must_change_password' => false]);
        $this->actingAs($user);
    }

    private function banners(): array
    {
        return BannerPromotion::withoutEvents(fn () => [
            BannerPromotion::create(['title' => 'A', 'slug' => 'a', 'image_path' => 'a.webp', 'is_active' => true, 'sort_order' => 1]),
            BannerPromotion::create(['title' => 'B', 'slug' => 'b', 'image_path' => 'b.webp', 'is_active' => true, 'sort_order' => 2]),
        ]);
    }

    private function apiOrder(string $path): array
    {
        return collect($this->withHmac()->getJson($path)->assertOk()->json('data'))->pluck('title')->all();
    }

    // -------------------------------------------------- helper (unit)

    public function test_invalidator_forgets_the_key_the_api_actually_reads(): void
    {
        Bus::fake();
        Cache::put('rs_web_cms_api_banner_promotions', 'basi', 3600);

        ApiCacheInvalidator::forModule('banner_promotions');

        $this->assertNull(Cache::get('rs_web_cms_api_banner_promotions'));
    }

    public function test_invalidator_forgets_keys_for_each_reorderable_module(): void
    {
        Bus::fake();

        foreach ([
            'banner_promotions' => 'rs_web_cms_api_banner_promotions',
            'facilities' => 'rs_web_cms_api_facility_services',
            'room_facilities' => 'rs_web_cms_api_room_facilities',
        ] as $module => $key) {
            Cache::put($key, 'basi', 3600);

            ApiCacheInvalidator::forModule($module);

            $this->assertNull(Cache::get($key), "Key {$key} harus terhapus untuk modul {$module}.");
        }
    }

    public function test_invalidator_dispatches_the_webhook_for_the_module(): void
    {
        Bus::fake();

        ApiCacheInvalidator::forModule('facilities');

        Bus::assertDispatched(
            SendCacheInvalidationWebhookJob::class,
            fn ($job) => $job->module === 'facilities' && $job->event === 'content.updated'
        );
    }

    public function test_invalidator_rejects_unknown_module_instead_of_silently_doing_nothing(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ApiCacheInvalidator::forModule('modul_tidak_ada');
    }

    public function test_no_dead_local_cms_cache_keys_remain(): void
    {
        $offenders = [];

        foreach (glob(app_path('Filament/Resources/*/Tables/*.php')) as $file) {
            if (str_contains(file_get_contents($file), 'local_cms_')) {
                $offenders[] = basename($file);
            }
        }

        $this->assertSame([], $offenders, 'Key local_cms_* tidak dipakai API: '.implode(', ', $offenders));
    }

    // ------------------------------- end-to-end lewat tabel Filament

    public function test_reordering_banners_in_admin_changes_what_the_api_returns(): void
    {
        $this->actAsReorderer();
        [$a, $b] = $this->banners();

        $this->assertSame(['A', 'B'], $this->apiOrder('/api/v1/cms/banner-promotions'));

        // Drag-and-drop: B dipindah ke posisi pertama (query massal, tanpa event model).
        Livewire::test(ListBannerPromotions::class)
            ->call('reorderTable', [$b->id, $a->id]);

        $this->assertSame(
            ['B', 'A'],
            $this->apiOrder('/api/v1/cms/banner-promotions'),
            'API harus langsung menampilkan urutan baru, bukan menunggu cache 6 jam habis.'
        );
    }

    public function test_reordering_banners_dispatches_the_webhook(): void
    {
        $this->actAsReorderer();
        [$a, $b] = $this->banners();
        Bus::fake();

        Livewire::test(ListBannerPromotions::class)
            ->call('reorderTable', [$b->id, $a->id]);

        Bus::assertDispatched(SendCacheInvalidationWebhookJob::class, fn ($job) => $job->module === 'banner_promotions');
    }

    public function test_reordering_facilities_invalidates_cache_and_dispatches_webhook(): void
    {
        $this->actAsReorderer();
        [$first, $second] = FacilityService::withoutEvents(fn () => [
            FacilityService::create(['name' => 'F1', 'slug' => 'f1', 'description' => 'd', 'is_active' => true, 'sort_order' => 1]),
            FacilityService::create(['name' => 'F2', 'slug' => 'f2', 'description' => 'd', 'is_active' => true, 'sort_order' => 2]),
        ]);

        $this->assertSame(['F1', 'F2'], collect($this->withHmac()->getJson('/api/v1/cms/facilities')->json('data'))->pluck('name')->all());

        Bus::fake();
        Livewire::test(ListFacilityServices::class)->call('reorderTable', [$second->id, $first->id]);

        $this->assertNull(Cache::get('rs_web_cms_api_facility_services'));
        Bus::assertDispatched(SendCacheInvalidationWebhookJob::class, fn ($job) => $job->module === 'facilities');
    }

    public function test_reordering_rooms_invalidates_cache_and_dispatches_webhook(): void
    {
        $this->actAsReorderer();
        [$first, $second] = RoomFacility::withoutEvents(fn () => [
            RoomFacility::create(['name' => 'R1', 'slug' => 'r1', 'category' => 'premium', 'is_active' => true, 'sort_order' => 1]),
            RoomFacility::create(['name' => 'R2', 'slug' => 'r2', 'category' => 'premium', 'is_active' => true, 'sort_order' => 2]),
        ]);

        $this->assertSame(['R1', 'R2'], collect($this->withHmac()->getJson('/api/v1/cms/room-facilities')->json('data'))->pluck('name')->all());

        Bus::fake();
        Livewire::test(ListRoomFacilities::class)->call('reorderTable', [$second->id, $first->id]);

        $this->assertNull(Cache::get('rs_web_cms_api_room_facilities'));
        Bus::assertDispatched(SendCacheInvalidationWebhookJob::class, fn ($job) => $job->module === 'room_facilities');
    }
}
