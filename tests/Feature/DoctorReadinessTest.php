<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Services\DoctorApiService;
use App\Services\DoctorSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tests\Traits\GeneratesHmacHeaders;

class DoctorReadinessTest extends TestCase
{
    use GeneratesHmacHeaders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
    }

    public function test_filtered_lists_in_both_languages_are_invalidated_on_update_and_deactivation(): void
    {
        $doctor = Doctor::create(['paramedic_code' => 'CACHE-1', 'name' => 'Nama Lama', 'specialty_code' => 'SP-1', 'is_active' => true]);
        foreach (['id', 'en'] as $locale) {
            $this->withHmac()->getJson('/api/v1/cms/doctors?specialty=SP-1&lang='.$locale)
                ->assertOk()->assertJsonPath('data.0.name', 'Nama Lama');
        }
        Cache::put('other-module-sentinel', 'keep', 600);
        $doctor->update(['name' => 'Nama Baru']);
        foreach (['id', 'en'] as $locale) {
            $this->withHmac()->getJson('/api/v1/cms/doctors?specialty=SP-1&lang='.$locale)
                ->assertOk()->assertJsonPath('data.0.name', 'Nama Baru');
        }
        $doctor->update(['is_active' => false]);
        foreach (['id', 'en'] as $locale) {
            $this->withHmac()->getJson('/api/v1/cms/doctors?specialty=SP-1&lang='.$locale)
                ->assertOk()->assertJsonCount(0, 'data');
        }
        $this->assertSame('keep', Cache::get('other-module-sentinel'));
    }

    public function test_doctor_specialty_filter_rejects_non_string_input(): void
    {
        $this->withHmac()->getJson('/api/v1/cms/doctors?specialty[]=SP-1')->assertUnprocessable();
    }

    public function test_filtered_doctor_list_is_refreshed_on_delete_and_restore_without_a_slug(): void
    {
        $doctor = Doctor::create(['paramedic_code' => 'CACHE-RESTORE', 'name' => 'Dokter Restore', 'specialty_code' => 'SP-2', 'is_active' => true]);
        $url = '/api/v1/cms/doctors?specialty=SP-2&lang=id';
        $this->withHmac()->getJson($url)->assertJsonCount(1, 'data');
        $doctor->delete();
        $this->withHmac()->getJson($url)->assertJsonCount(0, 'data');
        Doctor::withTrashed()->findOrFail($doctor->id)->restore();
        $this->withHmac()->getJson($url)->assertJsonCount(1, 'data');
        $this->assertDatabaseHas('audit_logs', ['action' => 'content.restored', 'subject_id' => $doctor->id]);
    }

    public function test_sync_leaves_new_marketing_content_empty(): void
    {
        $api = $this->createMock(DoctorApiService::class);
        $api->method('getDaftarSpesialisasi')->willReturn([(object) ['Code' => 'SP-1', 'Name' => 'Bedah']]);
        $api->method('getDokterBySpesialisasi')->willReturn([
            'Success' => true,
            'ScheduleRoutine' => [(object) ['ParamedicCode' => 'NEW-1', 'ParamedicName' => 'Dokter Baru']],
        ]);
        (new DoctorSyncService($api))->syncFromMedInfras();
        $doctor = Doctor::where('paramedic_code', 'NEW-1')->firstOrFail();
        foreach ($doctor->translatable as $field) {
            $this->assertNull($doctor->getRawOriginal($field));
        }
        $this->assertFalse($doctor->is_profile_complete);
    }

    public function test_profile_requires_all_marketing_fields_in_both_languages(): void
    {
        $doctor = new Doctor;
        foreach ($doctor->translatable as $field) {
            $doctor->setTranslation($field, 'id', 'Konten');
        }
        $this->assertFalse($doctor->is_profile_complete);
        foreach ($doctor->translatable as $field) {
            $doctor->setTranslation($field, 'en', 'Content');
        }
        $this->assertTrue($doctor->is_profile_complete);
        $doctor->setTranslation('education', 'en', '<p>&nbsp;</p>');
        $this->assertFalse($doctor->is_profile_complete);
    }
}
