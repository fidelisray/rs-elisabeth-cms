<?php

namespace Tests\Feature;

use App\Filament\Resources\Doctors\DoctorResource;
use App\Models\Doctor;
use App\Services\DoctorApiService;
use App\Services\DoctorSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class DoctorModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('cms_api.clients', [
            'TEST-WEB-CONSID' => 'test-secret-web-key-1234567890',
        ]);
        Config::set('cms_api.tolerance_minutes', 5);
    }

    public function test_doctor_model_translatable_marketing_fields(): void
    {
        $doctor = Doctor::create([
            'paramedic_code' => 'TEST-001',
            'name' => 'dr. Budi Santoso, Sp.A',
            'specialty_code' => 'KSM-01',
            'specialty_name' => 'Spesialis Anak',
            'is_active' => true,
        ]);

        $doctor->setTranslation('bio', 'id', 'Dokter spesialis anak ramah');
        $doctor->setTranslation('bio', 'en', 'Friendly pediatrician');

        $doctor->setTranslation('location', 'id', 'Semarang');
        $doctor->setTranslation('location', 'en', 'Semarang');

        $doctor->setTranslation('experience', 'id', '10 Tahun');
        $doctor->setTranslation('experience', 'en', '10 Years');

        $doctor->setTranslation('education', 'id', 'Universitas Diponegoro');
        $doctor->setTranslation('education', 'en', 'Diponegoro University');

        $doctor->save();

        $fresh = $doctor->fresh();
        $this->assertEquals('Dokter spesialis anak ramah', $fresh->getTranslation('bio', 'id'));
        $this->assertEquals('Friendly pediatrician', $fresh->getTranslation('bio', 'en'));
        $this->assertEquals('10 Tahun', $fresh->getTranslation('experience', 'id'));
        $this->assertEquals('10 Years', $fresh->getTranslation('experience', 'en'));
        $this->assertTrue($fresh->is_profile_complete);
    }

    public function test_sync_service_does_not_overwrite_marketing_fields(): void
    {
        $doctor = Doctor::create([
            'paramedic_code' => 'MEDIN-100',
            'name' => 'dr. Lama, Sp.B',
            'specialty_code' => 'KSM-10',
            'specialty_name' => 'Bedah Umum',
            'is_active' => true,
        ]);

        $doctor->setTranslation('bio', 'id', 'Bio diedit oleh tim marketing');
        $doctor->setTranslation('bio', 'en', 'Bio edited by marketing');
        $doctor->setTranslation('location', 'id', 'Klinik Spesialis');
        $doctor->setTranslation('location', 'en', 'Specialist Clinic');
        $doctor->setTranslation('experience', 'id', '15 Tahun');
        $doctor->setTranslation('experience', 'en', '15 Years');
        $doctor->setTranslation('education', 'id', 'UGM');
        $doctor->setTranslation('education', 'en', 'Gadjah Mada University');
        $doctor->save();

        // Mock DoctorApiService
        $mockApiService = $this->createMock(DoctorApiService::class);
        $mockApiService->method('getDaftarSpesialisasi')->willReturn([
            (object) ['Code' => 'KSM-10', 'Name' => 'Bedah Umum'],
        ]);
        $mockApiService->method('getDokterBySpesialisasi')->with('KSM-10')->willReturn([
            'Success' => true,
            'ScheduleRoutine' => [
                [
                    'ParamedicCode' => 'MEDIN-100',
                    'ParamedicName' => 'dr. Baru Diupdate SIMRS, Sp.B, Subsp.BD',
                    'SpecialtyID' => 'KSM-10',
                    'SpecialtyName' => 'Bedah Digestif',
                    'ServiceUnitName' => 'Klinik Bedah',
                ],
            ],
        ]);

        $syncService = new DoctorSyncService($mockApiService);
        $result = $syncService->syncFromMedInfras();

        $this->assertEquals(1, $result['total']);
        $this->assertEquals(0, $result['created']);
        $this->assertEquals(1, $result['updated']);

        $fresh = $doctor->fresh();
        // Master data updated from SIMRS
        $this->assertEquals('dr. Baru Diupdate SIMRS, Sp.B, Subsp.BD', $fresh->name);
        $this->assertEquals('Bedah Digestif', $fresh->specialty_name);

        // Marketing fields NOT overwritten!
        $this->assertEquals('Bio diedit oleh tim marketing', $fresh->getTranslation('bio', 'id'));
        $this->assertEquals('Bio edited by marketing', $fresh->getTranslation('bio', 'en'));
        $this->assertEquals('15 Tahun', $fresh->getTranslation('experience', 'id'));
        $this->assertEquals('UGM', $fresh->getTranslation('education', 'id'));
    }

    public function test_doctor_resource_disables_create_and_delete(): void
    {
        $doctor = new Doctor([
            'paramedic_code' => 'MEDIN-999',
            'name' => 'dr. Test',
        ]);

        $this->assertFalse(DoctorResource::canCreate());
        $this->assertFalse(DoctorResource::canDelete($doctor));
        $this->assertFalse(DoctorResource::canDeleteAny());
    }

    public function test_api_cms_doctors_endpoint_with_hmac(): void
    {
        $doctor = Doctor::create([
            'paramedic_code' => 'DOC-API-01',
            'name' => 'dr. Sarah, Sp.JP',
            'specialty_code' => 'KSM-05',
            'specialty_name' => 'Jantung & Pembuluh Darah',
            'is_active' => true,
        ]);
        $doctor->setTranslation('bio', 'id', 'Spesialis jantung terpercaya');
        $doctor->setTranslation('bio', 'en', 'Trusted cardiologist');
        $doctor->save();

        $timestamp = (string) time();
        $consId = 'TEST-WEB-CONSID';
        $secretKey = 'test-secret-web-key-1234567890';
        $signature = base64_encode(hash_hmac('sha256', $timestamp.$consId, $secretKey, true));

        $responseId = $this->withHeaders([
            'X-Cons-ID' => $consId,
            'X-Timestamp' => $timestamp,
            'X-Signature' => $signature,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/cms/doctors/DOC-API-01?lang=id');

        $responseId->assertStatus(200);
        $responseId->assertJsonPath('data.paramedic_code', 'DOC-API-01');
        $responseId->assertJsonPath('data.name', 'dr. Sarah, Sp.JP');
        $responseId->assertJsonPath('data.bio', 'Spesialis jantung terpercaya');

        $responseEn = $this->withHeaders([
            'X-Cons-ID' => $consId,
            'X-Timestamp' => $timestamp,
            'X-Signature' => $signature,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/cms/doctors/DOC-API-01?lang=en');

        $responseEn->assertStatus(200);
        $responseEn->assertJsonPath('data.bio', 'Trusted cardiologist');
    }
}
