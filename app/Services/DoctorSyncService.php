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
     * @return array{created: int, updated: int, total: int}
     */
    public function syncFromMedInfras(): array
    {
        $spesialisasi = $this->apiService->getDaftarSpesialisasi();

        if (empty($spesialisasi)) {
            Log::warning('DoctorSyncService: Daftar spesialisasi kosong dari API MedInfras.');

            return ['created' => 0, 'updated' => 0, 'total' => 0];
        }

        // Kumpulkan data dokter unik berdasarkan ParamedicCode
        $uniqueDoctors = [];

        foreach ($spesialisasi as $spc) {
            $code = $spc->Code ?? null;
            if (! $code) {
                continue;
            }

            try {
                $response = $this->apiService->getDokterBySpesialisasi($code);

                if (! ($response['Success'] ?? false)) {
                    continue;
                }

                $schedules = $response['ScheduleRoutine'] ?? [];
                foreach ($schedules as $item) {
                    $itemArray = is_object($item) ? (array) $item : (array) $item;
                    $paramedicCode = trim((string) ($itemArray['ParamedicCode'] ?? ''));

                    if ($paramedicCode === '') {
                        continue;
                    }

                    // Jika dokter sudah terdata, update spesialisasi jika belum ada
                    if (! isset($uniqueDoctors[$paramedicCode])) {
                        $uniqueDoctors[$paramedicCode] = [
                            'paramedic_code' => $paramedicCode,
                            'name' => trim((string) ($itemArray['ParamedicName'] ?? '')),
                            'specialty_code' => trim((string) ($itemArray['SpecialtyID'] ?? $code)),
                            'specialty_name' => trim((string) ($itemArray['SpecialtyName'] ?? ($spc->Name ?? ''))),
                            'service_unit_name' => trim((string) ($itemArray['ServiceUnitName'] ?? '')),
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::warning("DoctorSyncService: Gagal fetch spesialisasi {$code}", ['error' => $e->getMessage()]);

                continue;
            }
        }

        $createdCount = 0;
        $updatedCount = 0;

        DB::transaction(function () use ($uniqueDoctors, &$createdCount, &$updatedCount) {
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
                    }

                    $doctor->save();
                    $updatedCount++;
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
            'total' => count($uniqueDoctors),
        ];
    }
}
