<?php

namespace App\Services;

use App\Models\Doctor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DoctorSyncService
{
    public function __construct(
        protected DoctorApiService $apiService
    ) {}

    /**
     * Melakukan sinkronisasi data master dokter dari SIMRS MedInfras ke tabel doctors.
     * Menjaga agar 4 field konten marketing yang telah diedit TIDAK pernah tertimpa.
     *
     * @return array{created: int, updated: int, unchanged: int, total: int, status: string, failed_specialties: array}
     */
    public function syncFromMedInfras(): array
    {
        if (! config('rsapi.enabled', true)) {
            return ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'total' => 0, 'status' => 'disabled', 'failed_specialties' => []];
        }
        $spesialisasi = $this->apiService->getDaftarSpesialisasi();

        if (empty($spesialisasi)) {
            Log::warning('DoctorSyncService: Daftar spesialisasi kosong dari API MedInfras.');

            return ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'total' => 0, 'status' => 'failed', 'failed_specialties' => []];
        }

        // Kumpulkan data dokter unik berdasarkan ParamedicCode
        $uniqueDoctors = [];
        $failedSpecialties = [];

        foreach ($spesialisasi as $spc) {
            $specialty = (array) $spc;
            $code = $specialty['Code'] ?? null;
            if (! $code) {
                $failedSpecialties[] = 'invalid-specialty';

                continue;
            }

            try {
                $response = $this->apiService->getDokterBySpesialisasi($code);

                if (! ($response['Success'] ?? false)) {
                    $failedSpecialties[] = (string) $code;

                    continue;
                }

                $schedules = $response['ScheduleRoutine'] ?? [];
                if (! is_array($schedules)) {
                    $failedSpecialties[] = (string) $code;

                    continue;
                }
                foreach ($schedules as $item) {
                    $itemArray = (array) $item;
                    $paramedicCode = trim((string) ($itemArray['ParamedicCode'] ?? ''));
                    $name = trim((string) ($itemArray['ParamedicName'] ?? ''));

                    if ($paramedicCode === '' || $name === '') {
                        $failedSpecialties[] = (string) $code;

                        continue;
                    }

                    // Jika dokter sudah terdata, update spesialisasi jika belum ada
                    if (! isset($uniqueDoctors[$paramedicCode])) {
                        $uniqueDoctors[$paramedicCode] = [
                            'paramedic_code' => $paramedicCode,
                            'name' => $name,
                            'specialty_code' => trim((string) ($itemArray['SpecialtyID'] ?? $code)),
                            'specialty_name' => trim((string) ($itemArray['SpecialtyName'] ?? ($specialty['Name'] ?? ''))),
                            'service_unit_name' => trim((string) ($itemArray['ServiceUnitName'] ?? '')),
                        ];
                    }
                }
            } catch (\Exception $e) {
                $failedSpecialties[] = (string) $code;
                Log::warning("DoctorSyncService: Gagal fetch spesialisasi {$code}", ['exception_type' => get_class($e)]);

                continue;
            }
        }

        $createdCount = 0;
        $updatedCount = 0;
        $unchangedCount = 0;

        DB::transaction(function () use ($uniqueDoctors, &$createdCount, &$updatedCount, &$unchangedCount) {
            foreach ($uniqueDoctors as $paramedicCode => $data) {
                $doctor = Doctor::withTrashed()->where('paramedic_code', $paramedicCode)->first();

                if ($doctor) {
                    // Hanya perbarui data master resmi dari SIMRS (TIDAK menyentuh bio, location, experience, education)
                    $doctor->name = $data['name'];
                    $doctor->specialty_code = $data['specialty_code'];
                    $doctor->specialty_name = $data['specialty_name'];
                    if (! empty($data['service_unit_name'])) {
                        $doctor->service_unit_name = $data['service_unit_name'];
                    }

                    if ($doctor->trashed()) {
                        $doctor->restore();
                        if ($doctor->isDirty()) {
                            $doctor->save();
                        }
                        $updatedCount++;
                    } elseif ($doctor->isDirty()) {
                        $doctor->save();
                        $updatedCount++;
                    } else {
                        $unchangedCount++;
                    }
                } else {
                    // Hanya data resmi SIMRS; konten marketing dibiarkan NULL sampai diverifikasi.
                    $newDoctor = new Doctor;
                    $newDoctor->paramedic_code = $paramedicCode;
                    $newDoctor->name = $data['name'];
                    $newDoctor->specialty_code = $data['specialty_code'];
                    $newDoctor->specialty_name = $data['specialty_name'];
                    $newDoctor->service_unit_name = $data['service_unit_name'];
                    $newDoctor->is_active = true;

                    $newDoctor->save();
                    $createdCount++;
                }
            }
        });

        return [
            'created' => $createdCount,
            'updated' => $updatedCount,
            'unchanged' => $unchangedCount,
            'total' => count($uniqueDoctors),
            'status' => $uniqueDoctors === [] ? 'failed' : ($failedSpecialties === [] ? 'success' : 'partial'),
            'failed_specialties' => array_values(array_unique($failedSpecialties)),
        ];
    }
}
