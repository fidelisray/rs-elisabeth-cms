<?php

namespace Tests\Feature\Api\Cms;

use App\Models\FacilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\GeneratesHmacHeaders;

class FacilityServiceApiTest extends TestCase
{
    use GeneratesHmacHeaders, RefreshDatabase;

    public function test_api_returns_401_without_hmac_headers(): void
    {
        $response = $this->getJson('/api/v1/cms/facilities');
        $response->assertStatus(401);
    }

    public function test_api_returns_200_with_valid_hmac(): void
    {
        FacilityService::create([
            'name' => ['id' => 'Test Facility', 'en' => 'Test Facility EN'],
            'description' => ['id' => 'Desc', 'en' => 'Desc EN'],
            'is_active' => true,
        ]);

        $response = $this->withHmac()->getJson('/api/v1/cms/facilities');
        $response->assertStatus(200)->assertJsonPath('data.0.name', 'Test Facility');
    }

    public function test_api_returns_detail_data(): void
    {
        FacilityService::create([
            'name' => ['id' => 'Detail Facility', 'en' => 'Detail Facility EN'],
            'slug' => ['id' => 'detail-facility', 'en' => 'detail-facility-en'],
            'description' => ['id' => 'Desc', 'en' => 'Desc EN'],
            'is_active' => true,
        ]);

        $response = $this->withHmac()->getJson('/api/v1/cms/facilities/detail-facility');
        $response->assertStatus(200)->assertJsonPath('data.name', 'Detail Facility');
    }
}
