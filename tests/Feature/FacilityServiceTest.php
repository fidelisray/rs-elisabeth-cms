<?php

namespace Tests\Feature;

use App\Models\FacilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FacilityServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_facility_auto_fills_created_by_with_system_when_no_user(): void
    {
        $fac = FacilityService::create([
            'name' => 'Test Facility',
            'description' => 'Desc',
        ]);

        $this->assertEquals('system', $fac->created_by);
    }

    public function test_facility_uses_soft_delete(): void
    {
        $fac = FacilityService::create(['name' => 'Name', 'description' => 'Desc']);
        $id = $fac->id;

        $fac->delete();

        $this->assertNull(FacilityService::find($id));
        $this->assertNotNull(FacilityService::withTrashed()->find($id));
    }

    public function test_facility_clears_cache_on_saved_and_deleted(): void
    {
        Cache::put('rs_web_cms_api_facility_services', 'data-lama');

        $fac = FacilityService::create(['name' => 'Name', 'description' => 'Desc']);
        $this->assertFalse(Cache::has('rs_web_cms_api_facility_services'));

        Cache::put('rs_web_cms_api_facility_services', 'data-lama2');
        $fac->delete();
        $this->assertFalse(Cache::has('rs_web_cms_api_facility_services'));
    }

    public function test_facility_is_active_cast_works_correctly(): void
    {
        $fac = FacilityService::create([
            'name' => 'Name',
            'description' => 'Desc',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('facility_services', ['id' => $fac->id, 'is_active' => 1]);
        $this->assertTrue($fac->fresh()->is_active);
    }
}
