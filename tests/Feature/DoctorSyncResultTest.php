<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Services\DoctorApiService;
use App\Services\DoctorSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Ditulis pada fase offline; belum dieksekusi. Tidak memanggil API nyata. */
class DoctorSyncResultTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['rsapi.enabled' => true]);
        Bus::fake();
    }

    public function test_partial_sync_is_reported_and_does_not_overwrite_marketing_content(): void
    {
        $api = $this->partialApi();
        $service = new DoctorSyncService($api);
        $first = $service->syncFromMedInfras();
        $this->assertSame('partial', $first['status']);
        $this->assertSame(['BAD'], $first['failed_specialties']);
        $doctor = Doctor::where('paramedic_code', 'DOC-1')->firstOrFail();
        $doctor->setTranslations('bio', ['id' => 'Bio marketing', 'en' => 'Marketing bio'])->save();
        Bus::fake();
        $second = $service->syncFromMedInfras();
        $this->assertSame(1, $second['unchanged']);
        $this->assertSame(0, $second['updated']);
        $this->assertSame('Marketing bio', $doctor->fresh()->getTranslation('bio', 'en'));
        Bus::assertNothingDispatched();
    }

    public function test_disabled_sync_does_not_call_med_infras(): void
    {
        config(['rsapi.enabled' => false]);
        $api = $this->createMock(DoctorApiService::class);
        $api->expects($this->never())->method('getDaftarSpesialisasi');
        $result = (new DoctorSyncService($api))->syncFromMedInfras();
        $this->assertSame('disabled', $result['status']);
        $this->assertDatabaseCount('doctors', 0);
    }

    public function test_partial_warmup_keeps_last_complete_cache(): void
    {
        Cache::put('all_doctors_list', ['last-complete'], 600);
        $api = $this->partialApi();
        $this->assertSame(0, $api->fetchAndCacheAllDokter());
        $this->assertSame('partial', $api->getLastWarmupResult()['status']);
        $this->assertSame(['last-complete'], Cache::get('all_doctors_list'));
    }

    public function test_partial_command_has_a_nonzero_exit_code(): void
    {
        $this->mock(DoctorSyncService::class)->shouldReceive('syncFromMedInfras')->once()->andReturn([
            'status' => 'partial', 'total' => 1, 'created' => 1, 'updated' => 0, 'failed_specialties' => ['BAD'],
        ]);
        $this->artisan('dokter:sync')->assertFailed();
    }

    private function partialApi(): DoctorApiService
    {
        $api = $this->getMockBuilder(DoctorApiService::class)
            ->onlyMethods(['getDaftarSpesialisasi', 'getDokterBySpesialisasi'])->getMock();
        $api->method('getDaftarSpesialisasi')->willReturn([(object) ['Code' => 'GOOD'], (object) ['Code' => 'BAD']]);
        $api->method('getDokterBySpesialisasi')->willReturnCallback(fn ($code) => $code === 'BAD'
            ? ['Success' => false]
            : ['Success' => true, 'ScheduleRoutine' => [(object) ['ParamedicCode' => 'DOC-1', 'ParamedicName' => 'Dokter Satu']]]);

        return $api;
    }
}
