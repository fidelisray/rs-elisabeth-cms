<?php

namespace Tests\Unit;

use App\Services\DoctorApiService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DoctorApiServiceTest extends TestCase
{
    private DoctorApiService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('rsapi.base_url', 'https://test-api.rs-elisabeth.com');
        Config::set('rsapi.medin_endpoint', '/medinfrasapi/test');
        Config::set('rsapi.medin_consid', 'TEST-CONSID');
        Config::set('rsapi.medin_secretkey', 'TEST-SECRETKEY');
        Config::set('rsapi.timeout', 5);

        Cache::flush();
        $this->service = new DoctorApiService;
    }

    public function test_api_request_sends_required_hmac_headers(): void
    {
        Http::fake([
            'https://test-api.rs-elisabeth.com/medinfrasapi/test/api/reference/master/lst_spc' => Http::response([
                'Code' => '200',
                'Message' => 'OK',
                'Data' => json_encode([['Code' => 'KSM-01', 'Name' => 'Bedah']]),
            ], 200),
        ]);

        $result = $this->service->getDaftarSpesialisasi();

        $this->assertCount(1, $result);
        $this->assertEquals('KSM-01', $result[0]->Code);

        Http::assertSent(function (Request $request) {
            $hasConsId = $request->hasHeader('X-Cons-ID', 'TEST-CONSID');
            $hasTimestamp = $request->hasHeader('X-Timestamp');
            $hasSignature = $request->hasHeader('X-Signature');

            $timestamp = $request->header('X-Timestamp')[0];
            $expectedSignature = base64_encode(hash_hmac('sha256', $timestamp.'TEST-CONSID', 'TEST-SECRETKEY', true));

            return $hasConsId && $hasTimestamp && $hasSignature && ($request->header('X-Signature')[0] === $expectedSignature);
        });
    }

    public function test_get_daftar_units_handles_double_encoded_json_and_caches(): void
    {
        $mockUnits = [
            ['Code' => 'PL-01', 'Name' => 'Poli Umum'],
            ['Code' => 'PL-02', 'Name' => 'Poli Anak'],
        ];

        Http::fake([
            'https://test-api.rs-elisabeth.com/medinfrasapi/test/api/reference/master/lst_hsu' => Http::response([
                'Code' => '200',
                'Message' => 'OK',
                'Data' => json_encode($mockUnits),
            ], 200),
        ]);

        $units = $this->service->getDaftarUnits();

        $this->assertCount(2, $units);
        $this->assertEquals('PL-01', $units[0]->Code);

        // Verify caching prevents subsequent HTTP request
        Http::fake([
            'https://test-api.rs-elisabeth.com/medinfrasapi/test/api/reference/master/lst_hsu' => Http::response(null, 500),
        ]);

        $cachedUnits = $this->service->getDaftarUnits();
        $this->assertCount(2, $cachedUnits);
    }

    public function test_get_daftar_units_returns_empty_array_on_failure(): void
    {
        Http::fake([
            'https://test-api.rs-elisabeth.com/medinfrasapi/test/api/reference/master/lst_hsu' => Http::response('Server Error', 500),
        ]);

        $units = $this->service->getDaftarUnits();

        $this->assertIsArray($units);
        $this->assertEmpty($units);
    }

    public function test_get_dokter_by_spesialisasi_returns_structured_payload(): void
    {
        $schedulePayload = [
            'LeaveSchedule' => [],
            'ScheduleByDate' => [],
            'ScheduleRoutine' => [
                [
                    'ParamedicCode' => '12345',
                    'ParamedicName' => 'dr. John Doe, Sp.B',
                ],
            ],
        ];

        Http::fake([
            'https://test-api.rs-elisabeth.com/medinfrasapi/test/api/paramedicschedule/base/list/specialty/KSM-01' => Http::response([
                'Code' => '200',
                'Data' => json_encode($schedulePayload),
            ], 200),
        ]);

        $response = $this->service->getDokterBySpesialisasi('KSM-01');

        $this->assertTrue($response['Success']);
        $this->assertCount(1, $response['ScheduleRoutine']);
        $this->assertEquals('dr. John Doe, Sp.B', $response['ScheduleRoutine'][0]->ParamedicName);
    }
}
