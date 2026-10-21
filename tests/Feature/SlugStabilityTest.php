<?php

namespace Tests\Feature;

use App\Filament\Resources\RoomFacilities\Pages\CreateRoomFacility;
use App\Filament\Resources\RoomFacilities\Pages\EditRoomFacility;
use App\Models\RoomFacility;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * MVP-2.3: mengedit nama record lama tidak boleh mengubah slug, karena slug
 * adalah bagian URL publik (link web, bookmark, SEO).
 */
class SlugStabilityTest extends TestCase
{
    use RefreshDatabase;

    private function actAsMarketing(): void
    {
        config(['filesystems.media_disk' => 'media-test']);
        Storage::fake('media-test');
        Storage::disk('media-test')->put('rooms/test.webp', 'test image');
        $this->seed(DatabaseSeeder::class);

        $user = User::where('email', 'marketing@rs-elisabeth.com')->first();
        $user->update(['must_change_password' => false]);
        $this->actingAs($user);
    }

    private function room(array $attributes = []): RoomFacility
    {
        return RoomFacility::withoutEvents(fn () => RoomFacility::create(array_merge([
            'name' => ['id' => 'Kamar VIP', 'en' => 'VIP Room'],
            'slug' => ['id' => 'kamar-vip', 'en' => 'vip-room'],
            'tagline' => ['id' => 'Tag', 'en' => 'Tag EN'],
            'description' => ['id' => 'Desc', 'en' => 'Desc EN'],
            'image_path' => 'rooms/test.webp',
            'is_active' => true,
        ], $attributes)));
    }

    public function test_editing_the_name_of_an_existing_room_keeps_its_slug(): void
    {
        $this->actAsMarketing();
        $room = $this->room();

        Livewire::test(EditRoomFacility::class, ['record' => $room->getRouteKey()])
            ->fillForm(['name' => 'Kamar VIP Deluxe Baru'])
            ->assertFormSet(['slug' => 'kamar-vip']);
    }

    public function test_slug_in_database_is_unchanged_after_saving_a_renamed_room(): void
    {
        $this->actAsMarketing();
        $room = $this->room();

        Livewire::test(EditRoomFacility::class, ['record' => $room->getRouteKey()])
            ->fillForm(['name' => 'Nama Yang Sama Sekali Berbeda'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Nama Yang Sama Sekali Berbeda', $room->fresh()->getTranslation('name', 'id'));
        $this->assertSame('kamar-vip', $room->fresh()->getTranslation('slug', 'id'));
    }

    public function test_creating_a_new_room_still_generates_slug_from_name(): void
    {
        $this->actAsMarketing();

        Livewire::test(CreateRoomFacility::class)
            ->fillForm(['name' => 'President Suite'])
            ->assertFormSet(['slug' => 'president-suite']);
    }

    public function test_editing_a_room_that_has_an_empty_slug_fills_it_from_the_name(): void
    {
        $this->actAsMarketing();
        $room = $this->room(['slug' => ['id' => 'sementara', 'en' => 'sementara-en']]);
        Livewire::test(EditRoomFacility::class, ['record' => $room->getRouteKey()])
            ->set('data.slug', '')
            ->fillForm(['name' => 'Nama Untuk Slug Kosong'])
            ->assertFormSet(['slug' => 'nama-untuk-slug-kosong']);
    }

    public function test_slug_can_still_be_edited_manually_when_needed(): void
    {
        $this->actAsMarketing();
        $room = $this->room();

        Livewire::test(EditRoomFacility::class, ['record' => $room->getRouteKey()])
            ->fillForm(['slug' => 'slug-manual-baru'])
            ->assertFormSet(['slug' => 'slug-manual-baru']);
    }

    public function test_every_content_form_uses_the_create_only_slug_rule(): void
    {
        // Penjaga regresi: tidak ada form yang boleh menimpa slug tanpa memeriksa operasi.
        $offenders = [];

        foreach (glob(app_path('Filament/Resources/*/Schemas/*Form.php')) as $file) {
            $source = file_get_contents($file);

            if (! str_contains($source, 'Str::slug')) {
                continue;
            }

            if (! str_contains($source, "\$operation === 'create'")) {
                $offenders[] = basename($file);
            }
        }

        $this->assertSame([], $offenders, 'Form berikut menimpa slug saat edit: '.implode(', ', $offenders));
    }
}
