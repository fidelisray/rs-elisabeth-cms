<?php

namespace Tests\Feature;

use App\Models\RoomFacility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RoomFacilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_room_auto_fills_created_by_with_system_when_no_user(): void
    {
        $room = RoomFacility::create([
            'name' => ['id' => 'Test Room', 'en' => 'Test Room EN'], 'slug' => ['id' => 'test-room-1', 'en' => 'test-room-1-en'], 'tagline' => ['id' => 'Tag', 'en' => 'Tag EN'], 'description' => ['id' => 'Desc', 'en' => 'Desc EN'],
        ]);

        $this->assertEquals('system', $room->created_by);
    }

    public function test_room_uses_soft_delete(): void
    {
        $room = RoomFacility::create(['name' => ['id' => 'Name', 'en' => 'Name EN'], 'slug' => ['id' => 'name-1', 'en' => 'name-1-en'], 'tagline' => ['id' => 'Tag', 'en' => 'Tag EN'], 'description' => ['id' => 'Desc', 'en' => 'Desc EN']]);
        $id = $room->id;

        $room->delete();

        $this->assertNull(RoomFacility::find($id));
        $this->assertNotNull(RoomFacility::withTrashed()->find($id));
    }

    public function test_room_clears_cache_on_saved_and_deleted(): void
    {
        Cache::put('rs_web_cms_api_room_facilities', 'data-lama');

        $room = RoomFacility::create(['name' => ['id' => 'Name', 'en' => 'Name EN'], 'slug' => ['id' => 'name-2', 'en' => 'name-2-en'], 'tagline' => ['id' => 'Tag', 'en' => 'Tag EN'], 'description' => ['id' => 'Desc', 'en' => 'Desc EN']]);
        $this->assertFalse(Cache::has('rs_web_cms_api_room_facilities'));

        Cache::put('rs_web_cms_api_room_facilities', 'data-lama2');
        $room->delete();
        $this->assertFalse(Cache::has('rs_web_cms_api_room_facilities'));
    }

    public function test_room_is_active_cast_works_correctly(): void
    {
        $room = RoomFacility::create([
            'name' => ['id' => 'Name', 'en' => 'Name EN'], 'slug' => ['id' => 'name-3', 'en' => 'name-3-en'], 'tagline' => ['id' => 'Tag', 'en' => 'Tag EN'], 'description' => ['id' => 'Desc', 'en' => 'Desc EN'],
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('room_facilities', ['id' => $room->id, 'is_active' => 1]);
        $this->assertTrue($room->fresh()->is_active);
    }
}
