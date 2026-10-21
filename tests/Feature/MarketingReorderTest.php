<?php

namespace Tests\Feature;

use App\Filament\Resources\BannerPromotions\Pages\ListBannerPromotions;
use App\Filament\Resources\FacilityServices\Pages\ListFacilityServices;
use App\Filament\Resources\RoomFacilities\Pages\ListRoomFacilities;
use App\Jobs\SendCacheInvalidationWebhookJob;
use App\Models\BannerPromotion;
use App\Models\FacilityService;
use App\Models\Role;
use App\Models\RoomFacility;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * MVP-2.5: role marketing harus dapat mengatur urutan konten yang menjadi
 * tanggung jawabnya melalui drag-and-drop Filament.
 */
class MarketingReorderTest extends TestCase
{
    use RefreshDatabase;

    private User $marketing;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.media_disk' => 'media-test']);
        Storage::fake('media-test');

        $this->seed(DatabaseSeeder::class);
        $this->marketing = User::where('email', 'marketing@rs-elisabeth.com')->first();
        $this->marketing->update(['must_change_password' => false]);
        $this->actingAs($this->marketing);
    }

    public function test_marketing_has_reorder_permissions_for_all_reorderable_modules(): void
    {
        $role = Role::findByName('marketing', 'web');

        foreach (['BannerPromotion', 'FacilityService', 'RoomFacility'] as $subject) {
            $this->assertTrue($role->hasPermissionTo("Reorder:{$subject}"));
        }
    }

    public function test_marketing_can_reorder_banner_promotions(): void
    {
        [$a, $b] = BannerPromotion::withoutEvents(fn () => [
            BannerPromotion::create(['title' => ['id' => 'A', 'en' => 'A EN'], 'slug' => ['id' => 'a', 'en' => 'a-en'], 'image_path' => 'a.webp', 'is_active' => true, 'sort_order' => 1]),
            BannerPromotion::create(['title' => ['id' => 'B', 'en' => 'B EN'], 'slug' => ['id' => 'b', 'en' => 'b-en'], 'image_path' => 'b.webp', 'is_active' => true, 'sort_order' => 2]),
        ]);

        $component = Livewire::test(ListBannerPromotions::class);
        $this->assertTrue($component->instance()->getTable()->isReorderable());

        $component->call('reorderTable', [$b->id, $a->id]);

        $this->assertSame(['B', 'A'], BannerPromotion::orderBy('sort_order')->pluck('title')->all());
    }

    public function test_marketing_can_reorder_facilities(): void
    {
        [$a, $b] = FacilityService::withoutEvents(fn () => [
            FacilityService::create(['name' => ['id' => 'A', 'en' => 'A EN'], 'slug' => ['id' => 'a', 'en' => 'a-en'], 'tagline' => ['id' => 'Tag', 'en' => 'Tag EN'], 'description' => ['id' => 'd', 'en' => 'd EN'], 'is_active' => true, 'sort_order' => 1]),
            FacilityService::create(['name' => ['id' => 'B', 'en' => 'B EN'], 'slug' => ['id' => 'b', 'en' => 'b-en'], 'tagline' => ['id' => 'Tag', 'en' => 'Tag EN'], 'description' => ['id' => 'd', 'en' => 'd EN'], 'is_active' => true, 'sort_order' => 2]),
        ]);

        $component = Livewire::test(ListFacilityServices::class);
        $this->assertTrue($component->instance()->getTable()->isReorderable());
        $component->call('reorderTable', [$b->id, $a->id]);

        $this->assertSame(['B', 'A'], FacilityService::orderBy('sort_order')->pluck('name')->all());
    }

    public function test_marketing_can_reorder_room_facilities(): void
    {
        [$a, $b] = RoomFacility::withoutEvents(fn () => [
            RoomFacility::create(['name' => ['id' => 'A', 'en' => 'A EN'], 'slug' => ['id' => 'a', 'en' => 'a-en'], 'tagline' => ['id' => 'Tag', 'en' => 'Tag EN'], 'description' => ['id' => 'Desc', 'en' => 'Desc EN'], 'is_active' => true, 'sort_order' => 1]),
            RoomFacility::create(['name' => ['id' => 'B', 'en' => 'B EN'], 'slug' => ['id' => 'b', 'en' => 'b-en'], 'tagline' => ['id' => 'Tag', 'en' => 'Tag EN'], 'description' => ['id' => 'Desc', 'en' => 'Desc EN'], 'is_active' => true, 'sort_order' => 2]),
        ]);

        $component = Livewire::test(ListRoomFacilities::class);
        $this->assertTrue($component->instance()->getTable()->isReorderable());
        $component->call('reorderTable', [$b->id, $a->id]);

        $this->assertSame(['B', 'A'], RoomFacility::orderBy('sort_order')->pluck('name')->all());
    }

    public function test_marketing_reorder_dispatches_the_correct_webhook(): void
    {
        [$a, $b] = BannerPromotion::withoutEvents(fn () => [
            BannerPromotion::create(['title' => ['id' => 'A', 'en' => 'A EN'], 'slug' => ['id' => 'a', 'en' => 'a-en'], 'image_path' => 'a.webp', 'is_active' => true, 'sort_order' => 1]),
            BannerPromotion::create(['title' => ['id' => 'B', 'en' => 'B EN'], 'slug' => ['id' => 'b', 'en' => 'b-en'], 'image_path' => 'b.webp', 'is_active' => true, 'sort_order' => 2]),
        ]);
        Bus::fake();

        Livewire::test(ListBannerPromotions::class)->call('reorderTable', [$b->id, $a->id]);

        Bus::assertDispatched(
            SendCacheInvalidationWebhookJob::class,
            fn ($job) => $job->module === 'banner_promotions' && $job->event === 'content.updated'
        );
    }
}
