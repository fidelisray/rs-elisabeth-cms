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
            'name' => 'Test Room',
            'slug' => 'test-room-1',
            'description' => 'Desc',
        ]);

        $this->assertEquals('system', $room->created_by);
    }

    public function test_room_uses_soft_delete(): void
    {
        $room = RoomFacility::create(['name' => 'Name', 'slug' => 'name-1', 'description' => 'Desc']);
        $id = $room->id;

        $room->delete();

        $this->assertNull(RoomFacility::find($id));
        $this->assertNotNull(RoomFacility::withTrashed()->find($id));
    }

    public function test_room_clears_cache_on_saved_and_deleted(): void
    {
        Cache::put('rs_web_cms_api_room_facilities', 'data-lama');

        $room = RoomFacility::create(['name' => 'Name', 'slug' => 'name-2', 'description' => 'Desc']);
        $this->assertFalse(Cache::has('rs_web_cms_api_room_facilities'));

        Cache::put('rs_web_cms_api_room_facilities', 'data-lama2');
        $room->delete();
        $this->assertFalse(Cache::has('rs_web_cms_api_room_facilities'));
    }

    public function test_room_is_active_cast_works_correctly(): void
    {
        $room = RoomFacility::create([
            'name' => 'Name',
            'slug' => 'name-3',
            'description' => 'Desc',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('room_facilities', ['id' => $room->id, 'is_active' => 1]);
        $this->assertTrue($room->fresh()->is_active);
    }
}
