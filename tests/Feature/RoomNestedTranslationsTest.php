<?php

namespace Tests\Feature;

use App\Filament\Resources\RoomFacilities\Pages\CreateRoomFacility;
use App\Filament\Resources\RoomFacilities\Pages\EditRoomFacility;
use App\Jobs\SendCacheInvalidationWebhookJob;
use App\Models\RoomFacility;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\GeneratesHmacHeaders;

class RoomNestedTranslationsTest extends TestCase
{
    use GeneratesHmacHeaders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        config(['filesystems.media_disk' => 'media-test']);
        Storage::fake('media-test');
    }

    private function nested(): array
    {
        return [
            'amenities' => [
                ['group' => ['id' => 'Fasilitas Kamar', 'en' => 'Room Facilities'], 'items' => [
                    ['name' => ['id' => 'Tempat Tidur', 'en' => 'Bed'], 'value' => ['id' => 'Elektrik', 'en' => 'Electric'], 'show_in_comparison' => true],
                    ['name' => ['id' => 'Televisi', 'en' => 'Television'], 'value' => null, 'show_in_comparison' => false],
                ]],
                ['group' => ['id' => 'Fasilitas Tambahan', 'en' => 'Additional Facilities'], 'items' => [
                    ['name' => ['id' => 'Kursi', 'en' => 'Chair'], 'value' => ['id' => '', 'en' => ''], 'show_in_comparison' => false],
                ]],
            ],
            'highlight_tags' => [
                ['icon' => 'fa-solid fa-bed', 'label' => ['id' => 'Kamar Nyaman', 'en' => 'Comfortable Room']],
                ['icon' => 'fa-solid fa-tv', 'label' => ['id' => 'Hiburan', 'en' => 'Entertainment']],
            ],
            'whatsapp_text' => ['id' => 'Halo, mohon informasi kamar.', 'en' => 'Hello, please send room information.'],
        ];
    }

    private function actAsMarketing(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'marketing@rs-elisabeth.com')->firstOrFail();
        $user->update(['must_change_password' => false]);
        $this->actingAs($user);
    }

    private function createForm(?array $payload = null)
    {
        $this->actAsMarketing();
        $payload ??= $this->nested();

        return Livewire::test(CreateRoomFacility::class)->fillForm([
            'name' => 'Kamar Uji', 'slug' => 'kamar-uji', 'tagline' => 'Tagline ID', 'description' => 'Deskripsi ID',
            'room_size' => 40, 'bed_count' => 1, 'max_companion' => 2,
            'image_path' => UploadedFile::fake()->image('room.png', 640, 360),
            'amenities' => $payload['amenities'], 'highlight_tags' => $payload['highlight_tags'],
            'whatsapp_text' => $payload['whatsapp_text']['id'] ?? null, 'is_active' => true,
        ])->set('activeLocale', 'en')->fillForm([
            'name' => 'Test Room', 'slug' => 'test-room', 'tagline' => 'EN tagline', 'description' => 'EN description',
            'whatsapp_text' => $payload['whatsapp_text']['en'] ?? null,
        ]);
    }

    public function test_api_projects_nested_text_per_locale_without_exposing_translation_objects(): void
    {
        $room = RoomFacility::factory()->create([...$this->nested(), 'is_active' => true]);

        foreach (['en', 'id', 'en'] as $locale) {
            $response = $this->withHmac()->getJson('/api/v1/cms/room-facilities?lang='.$locale)->assertOk();
            $detail = $this->withHmac()->getJson('/api/v1/cms/room-facilities/'.$room->getTranslation('slug', $locale, false).'?lang='.$locale)->assertOk();
            $group = $locale === 'id' ? 'Fasilitas Kamar' : 'Room Facilities';
            $name = $locale === 'id' ? 'Tempat Tidur' : 'Bed';
            $value = $locale === 'id' ? 'Elektrik' : 'Electric';

            foreach ([[$response, 'data.0'], [$detail, 'data']] as [$result, $prefix]) {
                $result->assertJsonPath($prefix.'.amenities.0.group', $group)
                    ->assertJsonPath($prefix.'.amenities.0.items.0.name', $name)
                    ->assertJsonPath($prefix.'.amenities.0.items.0.value', $value)
                    ->assertJsonPath($prefix.'.amenities.0.items.0.show_in_comparison', true)
                    ->assertJsonPath($prefix.'.amenities.0.items.1.value', null)
                    ->assertJsonPath($prefix.'.highlight_tags.0.icon', 'fa-solid fa-bed')
                    ->assertJsonPath($prefix.'.highlight_tags.0.label', $this->nested()['highlight_tags'][0]['label'][$locale])
                    ->assertJsonPath($prefix.'.whatsapp_text', $this->nested()['whatsapp_text'][$locale]);
                $this->assertSame(['group', 'items'], array_keys($result->json($prefix.'.amenities.0')));
                $this->assertCount(2, $result->json($prefix.'.amenities'));
                $this->assertCount(2, $result->json($prefix.'.amenities.0.items'));
            }
        }

        $this->assertEquals($this->nested()['amenities'][0]['group'], $room->fresh()->amenities[0]['group']);
    }

    public function test_create_form_preserves_shared_structure_across_locale_switches_and_saves_both_languages(): void
    {
        $component = $this->createForm();
        $component->set('activeLocale', 'id')->assertFormSet(['whatsapp_text' => $this->nested()['whatsapp_text']['id']])
            ->set('activeLocale', 'en')->assertFormSet(['whatsapp_text' => $this->nested()['whatsapp_text']['en']])
            ->call('create')->assertHasNoFormErrors();

        $room = RoomFacility::query()->sole();
        $this->assertEquals($this->nested()['amenities'][0], $room->amenities[0]);
        $this->assertEquals($this->nested()['highlight_tags'], $room->highlight_tags);
        $this->assertEquals($this->nested()['whatsapp_text'], $room->getTranslations('whatsapp_text'));
        $this->assertSame('40', (string) $room->room_size);
        $this->assertSame('1', (string) $room->bed_count);
    }

    public static function invalidNestedValues(): array
    {
        return [
            'group-en-missing' => ['amenities.0.group.en', null],
            'group-id-whitespace' => ['amenities.0.group.id', '   '],
            'group-number' => ['amenities.0.group.en', 42],
            'item-name-en-missing' => ['amenities.0.items.0.name.en', null],
            'item-name-object' => ['amenities.0.items.0.name.en', ['bad' => 'value']],
            'item-value-partial' => ['amenities.0.items.0.value.en', null],
            'item-value-number' => ['amenities.0.items.0.value.en', 42],
            'missing-items' => ['amenities.0.items', []],
            'non-boolean-flag' => ['amenities.0.items.0.show_in_comparison', 'invalid'],
            'tag-label-en-missing' => ['highlight_tags.0.label.en', null],
            'tag-label-whitespace' => ['highlight_tags.0.label.id', '   '],
            'tag-icon-missing' => ['highlight_tags.0.icon', null],
            'legacy-group-string' => ['amenities.0.group', 'Fasilitas Lama'],
            'legacy-item-string' => ['amenities.0.items.0.name', 'Tempat Tidur'],
            'legacy-tag-string' => ['highlight_tags.0.label', 'Label Lama'],
            'unexpected-group-key' => ['amenities.0.group', ['id' => 'Grup', 'en' => 'Group', 'fr' => 'Autre']],
            'group-too-long' => ['amenities.0.group.en', str_repeat('A', 101)],
        ];
    }

    #[DataProvider('invalidNestedValues')]
    public function test_model_rejects_invalid_nested_content_without_writing_it(string $path, mixed $value): void
    {
        $payload = $this->nested();
        data_set($payload, $path, $value);
        $payload['whatsapp_text'] = null;

        try {
            RoomFacility::factory()->create($payload);
            $this->fail('Invalid bilingual nested data was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($path, $exception->errors());
            $this->assertSame(0, RoomFacility::count());
        }
    }

    public function test_form_rejects_missing_nested_translation_and_does_not_create_a_record(): void
    {
        $payload = $this->nested();
        $payload['amenities'][0]['items'][0]['name']['en'] = '';
        $component = $this->createForm($payload);
        $component->call('create')->assertHasFormErrors();
        $this->assertSame(0, RoomFacility::count());
    }

    public function test_form_invalid_nested_edit_does_not_overwrite_existing_content(): void
    {
        $this->actAsMarketing();
        Storage::disk('media-test')->put('rooms/test.webp', 'test image');
        $room = RoomFacility::factory()->create([...$this->nested(), 'image_path' => 'rooms/test.webp']);
        $before = $room->amenities;
        $payload = $before;
        $payload[0]['group']['en'] = '';

        Livewire::test(EditRoomFacility::class, ['record' => $room->getRouteKey()])
            ->fillForm(['amenities' => $payload])->call('save')->assertHasFormErrors();

        $this->assertEquals($before, $room->fresh()->amenities);
    }

    public function test_storage_shape_and_nested_values_remain_shared_when_application_locale_changes(): void
    {
        $room = RoomFacility::factory()->create($this->nested());
        app()->setLocale('en');
        $fresh = $room->fresh();
        $this->assertEquals($this->nested()['amenities'][0]['group'], $fresh->amenities[0]['group']);
        $this->assertEquals($this->nested()['highlight_tags'], $fresh->highlight_tags);
        $this->assertSame($this->nested()['whatsapp_text']['en'], $fresh->whatsapp_text);
        app()->setLocale('id');
        $this->assertSame($this->nested()['whatsapp_text']['id'], $fresh->whatsapp_text);

        $columns = collect(Schema::getColumns('room_facilities'))->keyBy('name');
        foreach (['amenities', 'highlight_tags', 'whatsapp_text'] as $column) {
            $this->assertTrue($columns[$column]['nullable']);
            if (DB::getDriverName() === 'pgsql') {
                $this->assertSame('jsonb', $columns[$column]['type_name']);
            }
        }
    }

    public function test_edit_reorders_adds_and_removes_shared_items_without_locale_drift(): void
    {
        $this->actAsMarketing();
        Storage::disk('media-test')->put('rooms/test.webp', 'test image');
        $room = RoomFacility::factory()->create([...$this->nested(), 'image_path' => 'rooms/test.webp']);
        $payload = $this->nested();
        $payload['amenities'] = array_reverse($payload['amenities']);
        $payload['amenities'][1]['items'] = array_reverse($payload['amenities'][1]['items']);
        $payload['amenities'][0]['items'][] = ['name' => ['id' => 'Meja', 'en' => 'Desk'], 'value' => null, 'show_in_comparison' => true];
        $payload['highlight_tags'] = [$payload['highlight_tags'][1]];

        Livewire::test(EditRoomFacility::class, ['record' => $room->getRouteKey()])
            ->fillForm(['amenities' => $payload['amenities'], 'highlight_tags' => $payload['highlight_tags']])
            ->set('activeLocale', 'en')->fillForm(['whatsapp_text' => 'Updated English message'])
            ->set('activeLocale', 'id')->call('save')->assertHasNoFormErrors();

        $fresh = $room->fresh();
        $this->assertSame('Fasilitas Tambahan', $fresh->amenities[0]['group']['id']);
        $this->assertSame('Desk', $fresh->amenities[0]['items'][1]['name']['en']);
        $this->assertSame('Television', $fresh->amenities[1]['items'][0]['name']['en']);
        $this->assertCount(1, $fresh->highlight_tags);
        $this->assertSame('Updated English message', $fresh->getTranslation('whatsapp_text', 'en', false));
        $this->assertSame($this->nested()['whatsapp_text']['id'], $fresh->getTranslation('whatsapp_text', 'id', false));
    }

    public function test_invalid_edit_leaves_the_previous_nested_data_unchanged(): void
    {
        $room = RoomFacility::factory()->create($this->nested())->fresh();
        $before = $room->getRawOriginal('amenities');
        $payload = $room->amenities;
        $payload[0]['group']['en'] = '';

        try {
            $room->update(['amenities' => $payload]);
            $this->fail('Invalid nested update was accepted.');
        } catch (ValidationException) {
            $this->assertSame($before, $room->fresh()->getRawOriginal('amenities'));
        }
    }

    public function test_optional_nested_lists_and_whatsapp_can_be_empty(): void
    {
        $room = RoomFacility::factory()->create(['amenities' => [], 'highlight_tags' => [], 'whatsapp_text' => null, 'is_active' => true]);
        $this->withHmac()->getJson('/api/v1/cms/room-facilities?lang=en')->assertOk()
            ->assertJsonPath('data.0.amenities', [])->assertJsonPath('data.0.highlight_tags', [])
            ->assertJsonPath('data.0.whatsapp_text', null);
        $this->assertSame([], $room->fresh()->amenities);
    }

    public function test_form_can_remove_groups_items_tags_and_whatsapp_without_recreating_them_on_locale_switch(): void
    {
        $this->actAsMarketing();
        Storage::disk('media-test')->put('rooms/test.webp', 'test image');
        $room = RoomFacility::factory()->create([...$this->nested(), 'image_path' => 'rooms/test.webp', 'is_active' => true]);
        $groups = [$this->nested()['amenities'][0]];
        $groups[0]['items'] = [$groups[0]['items'][0]];

        Livewire::test(EditRoomFacility::class, ['record' => $room->getRouteKey()])
            ->fillForm(['amenities' => $groups, 'highlight_tags' => [], 'whatsapp_text' => ''])
            ->set('activeLocale', 'en')->fillForm(['whatsapp_text' => ''])
            ->set('activeLocale', 'id')->call('save')->assertHasNoFormErrors();

        $fresh = $room->fresh();
        $this->assertCount(1, $fresh->amenities);
        $this->assertCount(1, $fresh->amenities[0]['items']);
        $this->assertSame([], $fresh->highlight_tags);
        $this->assertNull($fresh->getRawOriginal('whatsapp_text'));
        $this->withHmac()->getJson('/api/v1/cms/room-facilities?lang=en')->assertOk()
            ->assertJsonPath('data.0.amenities.0.items.0.name', 'Bed')
            ->assertJsonCount(1, 'data.0.amenities')->assertJsonCount(1, 'data.0.amenities.0.items')
            ->assertJsonPath('data.0.highlight_tags', [])->assertJsonPath('data.0.whatsapp_text', null);
    }

    public function test_form_rejects_one_language_optional_specification(): void
    {
        $payload = $this->nested();
        $payload['amenities'][0]['items'][0]['value']['en'] = '';
        $this->createForm($payload)->call('create')->assertHasFormErrors();
        $this->assertSame(0, RoomFacility::count());
    }

    public function test_optional_specification_can_be_blank_or_complete_and_is_trimmed(): void
    {
        $payload = $this->nested();
        $payload['amenities'][0]['items'][0]['value'] = ['id' => '  Elektrik  ', 'en' => '  Electric  '];
        $room = RoomFacility::factory()->create($payload)->fresh();
        $this->assertEquals(['id' => 'Elektrik', 'en' => 'Electric'], $room->amenities[0]['items'][0]['value']);
        $this->assertNull($room->amenities[1]['items'][0]['value']);
    }

    public function test_partial_whatsapp_is_rejected_until_both_locales_are_filled(): void
    {
        $payload = $this->nested();
        $payload['whatsapp_text']['en'] = '';
        $component = $this->createForm($payload)->call('create')->assertNotified();
        $this->assertSame(0, RoomFacility::count());
        $component->fillForm(['whatsapp_text' => 'English message'])->call('create')->assertHasNoFormErrors();
        $this->assertSame('English message', RoomFacility::query()->sole()->getTranslation('whatsapp_text', 'en', false));
    }

    public function test_nested_edit_invalidates_both_locale_caches_and_dispatches_the_existing_webhook(): void
    {
        $room = RoomFacility::factory()->create([...$this->nested(), 'is_active' => true]);
        foreach (['id', 'en'] as $locale) {
            $this->withHmac()->getJson('/api/v1/cms/room-facilities?lang='.$locale)->assertOk();
        }
        Bus::fake();
        $payload = $room->amenities;
        $payload[0]['group']['en'] = 'Updated Room Facilities';
        $room->update(['amenities' => $payload]);
        $this->assertFalse(Cache::has('rs_web_cms_api_room_facilities_id'));
        $this->assertFalse(Cache::has('rs_web_cms_api_room_facilities_en'));
        Bus::assertDispatchedTimes(SendCacheInvalidationWebhookJob::class, 1);
        $this->withHmac()->getJson('/api/v1/cms/room-facilities?lang=en')->assertOk()
            ->assertJsonPath('data.0.amenities.0.group', 'Updated Room Facilities');
    }
}
