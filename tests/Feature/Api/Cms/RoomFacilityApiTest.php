<?php

namespace Tests\Feature\Api\Cms;

use App\Models\RoomFacility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\GeneratesHmacHeaders;

class RoomFacilityApiTest extends TestCase
{
    use GeneratesHmacHeaders, RefreshDatabase;

    public function test_api_returns_401_without_hmac_headers(): void
    {
        $response = $this->getJson('/api/v1/cms/room-facilities');
        $response->assertStatus(401);
    }

    public function test_api_returns_200_with_valid_hmac(): void
    {
        RoomFacility::create([
            'name' => 'Test Room',
            'slug' => 'test-room',
            'category' => 'standard',
            'description' => 'Desc',
            'is_active' => true,
        ]);

        $response = $this->withHmac()->getJson('/api/v1/cms/room-facilities');
        $response->assertStatus(200)->assertJsonPath('data.0.name', 'Test Room');
    }

    public function test_api_returns_detail_data(): void
    {
        RoomFacility::create([
            'name' => 'Detail Room',
            'slug' => 'detail-room',
            'category' => 'premium',
            'description' => 'Desc',
            'is_active' => true,
        ]);

        $response = $this->withHmac()->getJson('/api/v1/cms/room-facilities/detail-room');
        $response->assertStatus(200)->assertJsonPath('data.name', 'Detail Room');
    }
}
