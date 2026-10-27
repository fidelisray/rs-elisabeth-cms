<?php

namespace Tests\Unit;

use App\Services\DoctorApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DoctorSpecialtyExclusionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['rsapi.enabled' => true, 'rsapi.blacklist_specialties' => ['KSM 37'], 'rsapi.fetch_delay_microseconds' => 0]);
        Cache::flush();
    }

    public function test_warmup_skips_excluded_code_even_when_it_has_a_cached_failure(): void
    {
        Cache::put('doctor_specialty_KSM 37', ['Success' => false], 3600);
        $api = $this->mockApi(['KSM 37', 'VALID', 'EMPTY']);
        $api->expects($this->exactly(2))->method('getDokterBySpesialisasi')
            ->willReturnCallback(function (string $code): array {
                $this->assertContains($code, ['VALID', 'EMPTY']);

                return ['Success' => true, 'ScheduleRoutine' => $code === 'EMPTY' ? [] : [$this->schedule()]];
            });

        $this->assertSame(1, $api->fetchAndCacheAllDokter());
        $result = $api->getLastWarmupResult();
        $this->assertSame('success', $result['status']);
        $this->assertSame(['KSM 37'], $result['excluded_specialties']);
        $this->assertSame([], $result['failed_specialties']);
        $this->assertSame(3, $result['source_specialties']);
        $this->assertSame(2, $result['requested_specialties']);
        $this->assertTrue($result['published']);
        $this->assertSame('DOC-1', Cache::get('all_doctors_list')[0]['ParamedicCode']);
        $this->assertSame(false, Cache::get('doctor_specialty_KSM 37')['Success']);
    }

    public function test_other_failures_are_not_hidden_by_the_exclusion(): void
    {
        Cache::put('all_doctors_list', ['complete-old'], 600);
        Cache::put('all_doctors_grouped', ['complete-old-group'], 600);
        $api = $this->mockApi(['KSM 37', 'VALID', 'BROKEN']);
        $api->expects($this->exactly(2))->method('getDokterBySpesialisasi')
            ->willReturnCallback(fn ($code) => $code === 'BROKEN'
                ? ['Success' => false]
                : ['Success' => true, 'ScheduleRoutine' => [$this->schedule()]]);

        $this->assertSame(0, $api->fetchAndCacheAllDokter());
        $result = $api->getLastWarmupResult();
        $this->assertSame('partial', $result['status']);
        $this->assertSame(['BROKEN'], $result['failed_specialties']);
        $this->assertSame(['KSM 37'], $result['excluded_specialties']);
        $this->assertFalse($result['published']);
        $this->assertTrue($result['cache_available']);
        $this->assertSame(['complete-old'], Cache::get('all_doctors_list'));
        $this->assertSame(['complete-old-group'], Cache::get('all_doctors_grouped'));
    }

    public function test_empty_configuration_reenables_the_previously_excluded_code(): void
    {
        config(['rsapi.blacklist_specialties' => []]);
        $api = $this->mockApi(['KSM 37', 'VALID']);
        $api->expects($this->exactly(2))->method('getDokterBySpesialisasi')
            ->willReturnCallback(fn ($code) => $code === 'KSM 37'
                ? ['Success' => false]
                : ['Success' => true, 'ScheduleRoutine' => [$this->schedule()]]);
        $this->assertSame(0, $api->fetchAndCacheAllDokter());
        $this->assertSame([], $api->getLastWarmupResult()['excluded_specialties']);
        $this->assertSame(['KSM 37'], $api->getLastWarmupResult()['failed_specialties']);
    }

    public function test_matching_is_exact_and_duplicate_master_codes_are_only_requested_once(): void
    {
        config(['rsapi.blacklist_specialties' => [' KSM 37 ', 'KSM 37', '']]);
        $api = $this->mockApi([' KSM 37 ', 'KSM 370', 'KSM-37', 'KSM-37']);
        $seen = [];
        $api->expects($this->exactly(2))->method('getDokterBySpesialisasi')
            ->willReturnCallback(function ($code) use (&$seen) {
                $seen[] = $code;

                return ['Success' => true, 'ScheduleRoutine' => [$this->schedule()]];
            });
        $this->assertSame(2, $api->fetchAndCacheAllDokter());
        $this->assertSame(['KSM 370', 'KSM-37'], $seen);
        $this->assertSame(['KSM 37'], $api->getLastWarmupResult()['excluded_specialties']);
    }

    public function test_all_codes_excluded_does_not_publish_an_empty_success(): void
    {
        $api = $this->mockApi(['KSM 37']);
        $api->expects($this->never())->method('getDokterBySpesialisasi');
        $this->assertSame(0, $api->fetchAndCacheAllDokter());
        $this->assertSame('no_eligible_specialties', $api->getLastWarmupResult()['status']);
        $this->assertFalse(Cache::has('all_doctors_list'));
    }

    public function test_direct_request_for_excluded_code_does_not_use_cached_failure_or_send_http(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        Cache::put('doctor_specialty_KSM 37', ['Success' => false, 'Message' => 'old-failure'], 3600);
        $result = (new DoctorApiService)->getDokterBySpesialisasi('KSM 37');
        $this->assertFalse($result['Success']);
        $this->assertTrue($result['Excluded']);
        $this->assertSame('old-failure', Cache::get('doctor_specialty_KSM 37')['Message']);
        Http::assertNothingSent();
    }

    public function test_command_reports_excluded_codes_separately_from_failures(): void
    {
        $api = $this->mockApi(['KSM 37', 'VALID'], true);
        $api->expects($this->once())->method('getDokterBySpesialisasi')->with('VALID')
            ->willReturn(['Success' => true, 'ScheduleRoutine' => [$this->schedule()]]);
        $this->app->instance(DoctorApiService::class, $api);
        $this->artisan('dokter:fetch-all')
            ->expectsOutput('Spesialisasi dikecualikan: KSM 37')
            ->assertSuccessful();
    }

    public function test_command_does_not_claim_old_cache_exists_on_cold_failure(): void
    {
        $api = $this->mockApi(['BROKEN'], true);
        $api->method('getDokterBySpesialisasi')->willReturn(['Success' => false]);
        $this->app->instance(DoctorApiService::class, $api);
        $this->artisan('dokter:fetch-all')
            ->expectsOutput('Spesialisasi gagal: BROKEN')
            ->expectsOutput('Cache agregat belum tersedia; tidak ada snapshot lama sebagai fallback.')
            ->assertFailed();
    }

    private function mockApi(array $codes, bool $units = false): DoctorApiService
    {
        $methods = ['getDaftarSpesialisasi', 'getDokterBySpesialisasi'];
        if ($units) {
            $methods[] = 'getDaftarUnits';
        }
        $api = $this->getMockBuilder(DoctorApiService::class)->onlyMethods($methods)->getMock();
        $api->method('getDaftarSpesialisasi')->willReturn(array_map(fn ($code) => (object) ['Code' => $code], $codes));
        if ($units) {
            $api->method('getDaftarUnits')->willReturn([]);
        }

        return $api;
    }

    private function schedule(): object
    {
        return (object) ['ParamedicCode' => 'DOC-1', 'ParamedicName' => 'Dokter Uji'];
    }
}
