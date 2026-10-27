<?php

namespace Tests\Feature;

use App\Filament\Resources\Doctors\Pages\EditDoctor;
use App\Filament\Resources\Doctors\Pages\ListDoctors;
use App\Filament\Resources\RoomFacilities\Schemas\RoomFacilityForm;
use App\Models\Doctor;
use App\Models\User;
use App\Services\DoctorApiService;
use App\Services\DoctorSyncService;
use Database\Seeders\DatabaseSeeder;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class DoctorPolicyAndFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['rsapi.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
        $this->artisan('app:super-admin:init', ['--email' => 'admin@rs-elisabeth.com'])->assertExitCode(0);
    }

    public function test_doctor_policy_enforces_role_permissions(): void
    {
        $superAdmin = User::where('email', 'admin@rs-elisabeth.com')->firstOrFail();
        $marketing = User::where('email', 'marketing@rs-elisabeth.com')->firstOrFail();
        $staffIt = User::where('email', 'it@rs-elisabeth.com')->firstOrFail();

        $doctor = Doctor::create([
            'paramedic_code' => 'DOC-POL-1',
            'name' => 'dr. Policy Test',
            'specialty_code' => 'SP-01',
            'specialty_name' => 'Spesialis Umum',
            'is_active' => true,
        ]);

        // Super Admin: can view, update, cannot create/delete
        $this->assertTrue(Gate::forUser($superAdmin)->allows('viewAny', Doctor::class));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('view', $doctor));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('update', $doctor));
        $this->assertFalse(Gate::forUser($superAdmin)->allows('create', Doctor::class));
        $this->assertFalse(Gate::forUser($superAdmin)->allows('delete', $doctor));
        $this->assertFalse(Gate::forUser($superAdmin)->allows('forceDelete', $doctor));

        // Marketing: can view, update, cannot create/delete
        $this->assertTrue(Gate::forUser($marketing)->allows('viewAny', Doctor::class));
        $this->assertTrue(Gate::forUser($marketing)->allows('view', $doctor));
        $this->assertTrue(Gate::forUser($marketing)->allows('update', $doctor));
        $this->assertFalse(Gate::forUser($marketing)->allows('create', Doctor::class));
        $this->assertFalse(Gate::forUser($marketing)->allows('delete', $doctor));

        // Staff IT: can view only, CANNOT update
        $this->assertTrue(Gate::forUser($staffIt)->allows('viewAny', Doctor::class));
        $this->assertTrue(Gate::forUser($staffIt)->allows('view', $doctor));
        $this->assertFalse(Gate::forUser($staffIt)->allows('update', $doctor));
        $this->assertFalse(Gate::forUser($staffIt)->allows('create', Doctor::class));
        $this->assertFalse(Gate::forUser($staffIt)->allows('delete', $doctor));
    }

    public function test_list_doctors_sync_action_visibility(): void
    {
        $marketing = User::where('email', 'marketing@rs-elisabeth.com')->firstOrFail();
        $marketing->update(['must_change_password' => false]);

        $staffIt = User::where('email', 'it@rs-elisabeth.com')->firstOrFail();
        $staffIt->update(['must_change_password' => false]);

        // Marketing can see sync action
        $this->actingAs($marketing);
        Livewire::test(ListDoctors::class)
            ->assertActionVisible('syncMedin');

        // Staff IT cannot see sync action
        $this->actingAs($staffIt);
        Livewire::test(ListDoctors::class)
            ->assertActionHidden('syncMedin');
    }

    public function test_edit_doctor_allows_saving_incomplete_marketing_profile(): void
    {
        $marketing = User::where('email', 'marketing@rs-elisabeth.com')->firstOrFail();
        $marketing->update(['must_change_password' => false]);
        $this->actingAs($marketing);

        $doctor = Doctor::create([
            'paramedic_code' => 'DOC-EMPTY-1',
            'name' => 'dr. Dokter Kosong',
            'specialty_code' => 'SP-02',
            'specialty_name' => 'Spesialis Bedah',
            'is_active' => true,
        ]);

        // Toggling is_active without marketing fields should save cleanly
        Livewire::test(EditDoctor::class, ['record' => $doctor->getKey()])
            ->fillForm([
                'is_active' => false,
            ])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse($doctor->fresh()->is_active);
    }

    public function test_edit_doctor_requires_both_locales_when_marketing_field_is_partially_filled(): void
    {
        $marketing = User::where('email', 'marketing@rs-elisabeth.com')->firstOrFail();
        $marketing->update(['must_change_password' => false]);
        $this->actingAs($marketing);

        $doctor = Doctor::create([
            'paramedic_code' => 'DOC-PARTIAL-1',
            'name' => 'dr. Dokter Parsial',
            'specialty_code' => 'SP-03',
            'specialty_name' => 'Spesialis Anak',
            'is_active' => true,
        ]);

        // Filling only Indonesian bio without English should halt
        $component = Livewire::test(EditDoctor::class, ['record' => $doctor->getKey()])
            ->fillForm([
                'bio' => 'Biografi dokter bahasa Indonesia saja',
            ])
            ->call('save');

        $component->assertNotified();
        $this->assertNull($doctor->fresh()->getTranslation('bio', 'en', false));
    }

    public function test_edit_doctor_preserves_readonly_identity_fields_when_switching_locale(): void
    {
        $marketing = User::where('email', 'marketing@rs-elisabeth.com')->firstOrFail();
        $marketing->update(['must_change_password' => false]);
        $this->actingAs($marketing);

        $doctor = Doctor::create([
            'paramedic_code' => 'DOC-LOCALE-1',
            'name' => 'dr. Caecilia Nancy S, Msi.Med, Sp.A',
            'specialty_code' => 'SP-ANAK',
            'specialty_name' => 'Spesialis Anak',
            'service_unit_name' => 'Poliklinik Anak',
            'bio' => ['id' => 'Biografi ID', 'en' => 'Biography EN'],
            'location' => ['id' => 'Semarang', 'en' => 'Semarang City'],
            'experience' => ['id' => '5 Tahun', 'en' => '5 Years'],
            'education' => ['id' => 'Universitas Diponegoro', 'en' => 'Stanford University'],
            'is_active' => true,
        ]);

        $component = Livewire::test(EditDoctor::class, ['record' => $doctor->getKey()])
            ->assertFormSet([
                'name' => 'dr. Caecilia Nancy S, Msi.Med, Sp.A',
                'paramedic_code' => 'DOC-LOCALE-1',
                'specialty_name' => 'Spesialis Anak',
                'service_unit_name' => 'Poliklinik Anak',
                'bio' => 'Biografi ID',
            ])
            ->set('activeLocale', 'en')
            ->assertFormSet([
                'name' => 'dr. Caecilia Nancy S, Msi.Med, Sp.A',
                'paramedic_code' => 'DOC-LOCALE-1',
                'specialty_name' => 'Spesialis Anak',
                'service_unit_name' => 'Poliklinik Anak',
                'bio' => 'Biography EN',
                'location' => 'Semarang City',
                'experience' => '5 Years',
                'education' => 'Stanford University',
            ])
            ->set('activeLocale', 'id')
            ->assertFormSet([
                'name' => 'dr. Caecilia Nancy S, Msi.Med, Sp.A',
                'paramedic_code' => 'DOC-LOCALE-1',
                'specialty_name' => 'Spesialis Anak',
                'service_unit_name' => 'Poliklinik Anak',
                'bio' => 'Biografi ID',
            ]);
    }

    public function test_doctor_sync_persists_dirty_attributes_on_restored_doctor(): void
    {
        $doctor = Doctor::create([
            'paramedic_code' => 'DOC-RESTORE-1',
            'name' => 'dr. Nama Lama',
            'specialty_code' => 'SP-OLD',
            'specialty_name' => 'Spesialis Lama',
            'service_unit_name' => 'Poli Lama',
            'is_active' => true,
        ]);
        $doctor->delete();
        $this->assertTrue($doctor->fresh()->trashed());

        $mockApi = $this->createMock(DoctorApiService::class);
        $mockApi->method('getDaftarSpesialisasi')->willReturn([
            (object) ['Code' => 'SP-NEW', 'Name' => 'Spesialis Baru'],
        ]);
        $mockApi->method('getDokterBySpesialisasi')->with('SP-NEW')->willReturn([
            'Success' => true,
            'ScheduleRoutine' => [
                [
                    'ParamedicCode' => 'DOC-RESTORE-1',
                    'ParamedicName' => 'dr. Nama Baru SIMRS',
                    'SpecialtyID' => 'SP-NEW',
                    'SpecialtyName' => 'Spesialis Baru',
                    'ServiceUnitName' => 'Poli Baru',
                ],
            ],
        ]);

        $service = new DoctorSyncService($mockApi);
        $result = $service->syncFromMedInfras();

        $this->assertSame(1, $result['updated']);
        $fresh = $doctor->fresh();
        $this->assertFalse($fresh->trashed());
        $this->assertSame('dr. Nama Baru SIMRS', $fresh->name);
        $this->assertSame('SP-NEW', $fresh->specialty_code);
        $this->assertSame('Spesialis Baru', $fresh->specialty_name);
        $this->assertSame('Poli Baru', $fresh->service_unit_name);
    }

    public function test_room_facility_form_has_clean_utf8_strings_without_mojibake(): void
    {
        $schema = RoomFacilityForm::configure(new Schema);
        $components = $schema->getComponents();

        $fileContent = file_get_contents(app_path('Filament/Resources/RoomFacilities/Schemas/RoomFacilityForm.php'));

        // Assert no mojibake byte sequences exist
        $this->assertStringNotContainsString('Ã', $fileContent);
        $this->assertStringNotContainsString('â€', $fileContent);
        $this->assertStringNotContainsString('Â²', $fileContent);

        // Assert clean strings are present
        $this->assertStringContainsString("suffix('m²')", $fileContent);
        $this->assertStringContainsString('Nama Grup — Indonesia', $fileContent);
        $this->assertStringContainsString('Nama Grup — English', $fileContent);
        $this->assertStringContainsString('Nama Fasilitas — Indonesia', $fileContent);
        $this->assertStringContainsString('Nama Fasilitas — English', $fileContent);
        $this->assertStringContainsString('Spesifikasi — Indonesia (Opsional)', $fileContent);
        $this->assertStringContainsString('Spesifikasi — English (Opsional)', $fileContent);
        $this->assertStringContainsString('Label Tag — Indonesia', $fileContent);
        $this->assertStringContainsString('Label Tag — English', $fileContent);
    }

    public function test_doctor_table_profile_status_filters_correctly(): void
    {
        $completeDoctor = Doctor::create([
            'paramedic_code' => 'DOC-COMP',
            'name' => 'dr. Lengkap',
            'specialty_code' => 'SP-01',
            'specialty_name' => 'Umum',
            'is_active' => true,
            'bio' => ['id' => 'Bio ID', 'en' => 'Bio EN'],
            'location' => ['id' => 'Loc ID', 'en' => 'Loc EN'],
            'experience' => ['id' => 'Exp ID', 'en' => 'Exp EN'],
            'education' => ['id' => 'Edu ID', 'en' => 'Edu EN'],
        ]);

        $partialDoctor = Doctor::create([
            'paramedic_code' => 'DOC-PART',
            'name' => 'dr. Parsial',
            'specialty_code' => 'SP-01',
            'specialty_name' => 'Umum',
            'is_active' => true,
            // Satu field bilingual terisi, tiga field lain masih NULL. Terjemahan
            // satu bahasa tidak sah pada PostgreSQL dan bukan fixture profil parsial.
            'bio' => ['id' => 'Bio ID', 'en' => 'Bio EN'],
        ]);

        $emptyDoctor = Doctor::create([
            'paramedic_code' => 'DOC-EMP',
            'name' => 'dr. Kosong',
            'specialty_code' => 'SP-01',
            'specialty_name' => 'Umum',
            'is_active' => true,
        ]);

        $marketing = User::where('email', 'marketing@rs-elisabeth.com')->firstOrFail();
        $marketing->update(['must_change_password' => false]);
        $this->actingAs($marketing);

        // TernaryFilter true: only complete doctors
        Livewire::test(ListDoctors::class)
            ->filterTable('profile_status', true)
            ->searchTable('DOC-COMP')
            ->assertCanSeeTableRecords([$completeDoctor])
            ->assertCanNotSeeTableRecords([$partialDoctor, $emptyDoctor]);

        // TernaryFilter false: partial or empty doctors
        Livewire::test(ListDoctors::class)
            ->filterTable('profile_status', false)
            ->assertCanSeeTableRecords([$partialDoctor, $emptyDoctor])
            ->assertCanNotSeeTableRecords([$completeDoctor]);
    }
}
