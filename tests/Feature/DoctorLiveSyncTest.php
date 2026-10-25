<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Services\DoctorApiService;
use App\Services\DoctorSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Uji integrasi nyata terhadap API MedInfras (read-only, GET saja).
 *
 * Dijalankan sebagai bagian dari suite utama. Jika API tidak dapat dijangkau
 * (di luar jaringan RS atau konfigurasi RS_MEDIN_* kosong), test di-skip dengan alasan
 * yang jelas, bukan gagal. Setiap kegagalan saat API dapat dijangkau tetap gagal.
 */
class DoctorLiveSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! config('rsapi.enabled', true)) {
            $this->markTestSkipped('RS_API_ENABLED=false: live MedInfras tidak dijalankan pada profil offline.');
        }

        if (empty(config('rsapi.medin_consid')) || empty(config('rsapi.medin_secretkey'))) {
            $this->markTestSkipped('RS_MEDIN_API_CONSID dan RS_MEDIN_API_SECRETKEY belum diisi.');
        }

        if (! $this->medinfrasReachable()) {
            $this->markTestSkipped('API MedInfras tidak dapat dijangkau dari lingkungan ini.');
        }

        Cache::flush();
    }

    private function medinfrasReachable(): bool
    {
        $host = parse_url((string) config('rsapi.base_url'), PHP_URL_HOST);
        $port = parse_url((string) config('rsapi.base_url'), PHP_URL_SCHEME) === 'http' ? 80 : 443;

        if (! $host) {
            return false;
        }

        $socket = @fsockopen($host, $port, $errno, $errstr, 3);
        if ($socket === false) {
            return false;
        }
        fclose($socket);

        return true;
    }

    public function test_live_specialty_list_is_returned(): void
    {
        $specialties = $this->app->make(DoctorApiService::class)->getDaftarSpesialisasi();

        $this->assertIsArray($specialties);
        $this->assertNotEmpty($specialties, 'Daftar spesialisasi dari MedInfras kosong.');
        $this->assertNotEmpty($specialties[0]->Code ?? null);
    }

    public function test_live_sync_populates_doctors_and_is_idempotent(): void
    {
        $sync = $this->app->make(DoctorSyncService::class);

        $first = $sync->syncFromMedInfras();
        $this->assertGreaterThan(0, $first['total'], 'Sinkronisasi live tidak menghasilkan dokter.');
        $this->assertSame($first['total'], Doctor::count());

        $second = $sync->syncFromMedInfras();
        $this->assertSame($first['total'], Doctor::count(), 'Sync kedua tidak boleh menggandakan dokter.');
        $this->assertSame(0, $second['created'], 'Sync kedua tidak boleh membuat dokter baru.');
    }

    public function test_live_sync_keeps_marketing_content_on_existing_doctor(): void
    {
        $sync = $this->app->make(DoctorSyncService::class);
        $sync->syncFromMedInfras();

        $doctor = Doctor::firstOrFail();
        $doctor->setTranslation('bio', 'id', 'Bio uji marketing');
        $doctor->setTranslation('bio', 'en', 'Marketing bio test');
        $doctor->save();

        $sync->syncFromMedInfras();

        $fresh = $doctor->fresh();
        $this->assertSame('Bio uji marketing', $fresh->getTranslation('bio', 'id'));
        $this->assertSame('Marketing bio test', $fresh->getTranslation('bio', 'en'));
    }
}
