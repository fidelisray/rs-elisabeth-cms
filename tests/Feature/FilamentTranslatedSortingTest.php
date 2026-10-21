<?php

namespace Tests\Feature;

use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Filament\Resources\BannerPromotions\Pages\ListBannerPromotions;
use App\Filament\Resources\FacilityServices\Pages\ListFacilityServices;
use App\Filament\Resources\News\Pages\ListNews;
use App\Filament\Resources\Promotions\Pages\ListPromotions;
use App\Filament\Resources\RoomFacilities\Pages\ListRoomFacilities;
use App\Models\Article;
use App\Models\BannerPromotion;
use App\Models\FacilityService;
use App\Models\News;
use App\Models\Promotion;
use App\Models\RoomFacility;
use App\Models\User;
use App\Support\TranslatedContentOrder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FilamentTranslatedSortingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        config(['filesystems.media_disk' => 'media-test']);
        Storage::fake('media-test');
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'marketing@rs-elisabeth.com')->firstOrFail();
        $user->update(['must_change_password' => false]);
        $this->actingAs($user);
    }

    public static function modules(): array
    {
        return [
            'news' => [News::class, ListNews::class, 'title'],
            'articles' => [Article::class, ListArticles::class, 'title'],
            'promotions' => [Promotion::class, ListPromotions::class, 'title'],
            'facilities' => [FacilityService::class, ListFacilityServices::class, 'name'],
            'rooms' => [RoomFacility::class, ListRoomFacilities::class, 'name'],
            'banners' => [BannerPromotion::class, ListBannerPromotions::class, 'title'],
        ];
    }

    public static function reorderableModules(): array
    {
        return array_intersect_key(self::modules(), array_flip(['facilities', 'rooms', 'banners']));
    }

    private function records(string $model, string $attribute): array
    {
        return [
            $model::factory()->create([
                $attribute => ['id' => 'Alpha Indonesia', 'en' => 'Zulu English'],
                'slug' => ['id' => 'alpha-id', 'en' => 'zulu-en'],
            ]),
            $model::factory()->create([
                $attribute => ['id' => 'Zulu Indonesia', 'en' => 'Alpha English'],
                'slug' => ['id' => 'zulu-id', 'en' => 'alpha-en'],
            ]),
        ];
    }

    #[DataProvider('modules')]
    public function test_text_and_slug_sorting_follow_locale_in_both_directions(string $model, string $page, string $attribute): void
    {
        [$first, $second] = $this->records($model, $attribute);
        $this->get($page::getUrl())->assertOk();
        $component = Livewire::test($page)->assertTableColumnExists($attribute, fn ($column) => $column->isSortable());

        foreach (['id', 'en'] as $locale) {
            $component->set('activeLocale', $locale);

            foreach (['asc', 'desc'] as $direction) {
                $expected = $locale === 'id' ? [$first, $second] : [$second, $first];
                $expected = $direction === 'asc' ? $expected : array_reverse($expected);
                $component->call('sortTable', $attribute, $direction)->assertCanSeeTableRecords($expected, inOrder: true);
            }
        }

        $component->assertTableColumnExists('slug', fn ($column) => $column->isSortable() && $column->isToggleable() && $column->isToggledHiddenByDefault())
            ->assertCanNotRenderTableColumn('slug');
        $state = array_map(function (array $column): array {
            if ($column['name'] === 'slug') {
                $column['isToggled'] = true;
            }

            return $column;
        }, $component->instance()->tableColumns);
        $component->call('applyTableColumnManager', $state)->assertCanRenderTableColumn('slug');

        foreach (['id', 'en'] as $locale) {
            $component->set('activeLocale', $locale);

            foreach (['asc', 'desc'] as $direction) {
                $expected = $locale === 'id' ? [$first, $second] : [$second, $first];
                $component->call('sortTable', 'slug', $direction)
                    ->assertCanSeeTableRecords($direction === 'asc' ? $expected : array_reverse($expected), inOrder: true);
            }
        }
    }

    #[DataProvider('modules')]
    public function test_locale_switch_reapplies_the_current_sort(string $model, string $page, string $attribute): void
    {
        [$first, $second] = $this->records($model, $attribute);
        Livewire::test($page)->call('sortTable', $attribute, 'asc')
            ->assertCanSeeTableRecords([$first, $second], inOrder: true)
            ->set('activeLocale', 'en')
            ->assertSet('tableSort', $attribute.':asc')
            ->assertCanSeeTableRecords([$second, $first], inOrder: true)
            ->set('activeLocale', 'id')
            ->assertCanSeeTableRecords([$first, $second], inOrder: true);
    }

    #[DataProvider('modules')]
    public function test_equal_indonesian_text_does_not_sort_by_english_text(string $model, string $page, string $attribute): void
    {
        [$first, $second] = $this->records($model, $attribute);
        $first->setTranslation($attribute, 'id', 'Nama Sama')->save();
        $second->setTranslation($attribute, 'id', 'Nama Sama')->save();

        if (array_key_exists('sort_order', $first->getAttributes())) {
            DB::table($first->getTable())->update(['sort_order' => 1]);
        }

        Livewire::test($page)->call('sortTable', $attribute, 'asc')
            ->assertCanSeeTableRecords([$first, $second], inOrder: true)
            ->set('activeLocale', 'en')
            ->assertCanSeeTableRecords([$second, $first], inOrder: true);
    }

    #[DataProvider('modules')]
    public function test_missing_or_unsupported_sort_locale_falls_back_to_id(string $model, string $page, string $attribute): void
    {
        [$first, $second] = $this->records($model, $attribute);

        foreach ([null, 'unsupported-locale', ['en']] as $locale) {
            $this->assertSame(
                [$first->getKey(), $second->getKey()],
                TranslatedContentOrder::apply($model::query(), $attribute, $locale)->pluck('id')->all()
            );
        }
    }

    #[DataProvider('reorderableModules')]
    public function test_reordering_mode_uses_sort_order_instead_of_text_sort(string $model, string $page, string $attribute): void
    {
        [$first, $second] = $this->records($model, $attribute);
        DB::table($first->getTable())->where('id', $first->getKey())->update(['sort_order' => 2]);
        DB::table($second->getTable())->where('id', $second->getKey())->update(['sort_order' => 1]);

        Livewire::test($page)->call('sortTable', $attribute, 'asc')
            ->assertCanSeeTableRecords([$first, $second], inOrder: true)
            ->call('toggleTableReordering')
            ->assertCanSeeTableRecords([$second, $first], inOrder: true);
    }
}
