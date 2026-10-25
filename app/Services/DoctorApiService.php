<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DoctorApiService
{
    protected string $baseUrl;

    protected string $medinEndpoint;

    protected string $consId;

    protected string $secretKey;

    protected int $timeout;

    private array $lastWarmupResult = ['status' => 'not_run', 'total' => 0, 'failed_specialties' => []];

    public function __construct()
    {
        $this->baseUrl = (string) (config('rsapi.base_url') ?? '');
        $this->medinEndpoint = (string) (config('rsapi.medin_endpoint') ?? '');
        $this->consId = (string) (config('rsapi.medin_consid') ?? '');
        $this->secretKey = (string) (config('rsapi.medin_secretkey') ?? '');
        $this->timeout = (int) config('rsapi.timeout', 10);
    }

    /**
     * Decode JSON string if needed, or return original data.
     */
    protected function decodeData(mixed $data): mixed
    {
        if (is_string($data)) {
            $decoded = json_decode($data);

            return json_last_error() === JSON_ERROR_NONE ? $decoded : $data;
        }

        return $data;
    }

    protected function apiRequest(): PendingRequest
    {
        if (! config('rsapi.enabled', true)) {
            throw new \RuntimeException('RS API dinonaktifkan pada environment ini.');
        }
        $consid = $this->consId;
        $secretKey = $this->secretKey;

        $tStamp = strval(time());
        $signature = hash_hmac('sha256', $tStamp.$consid, $secretKey, true);
        $encodedSignature = base64_encode($signature);

        return Http::withHeaders([
            'X-Cons-ID' => $consid,
            'X-Timestamp' => $tStamp,
            'X-Signature' => $encodedSignature,
            'Accept' => 'application/json',
        ])->timeout($this->timeout);
    }

    /**
     * Ambil daftar Healthcare Service Units (Klinik / Poli / Instalasi).
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, mixed>
     */
    public function getDaftarUnits(array $filters = []): array
    {
        $cacheKey = 'units_'.md5(serialize($filters));
        $ttl = (int) config('rsapi.cache_ttl.units', 3600);

        return Cache::remember($cacheKey, $ttl, function () {
            try {
                $response = $this->apiRequest()
                    ->get("{$this->baseUrl}{$this->medinEndpoint}/api/reference/master/lst_hsu");

                if ($response->successful()) {
                    $data = $this->decodeData($response->json('Data'));

                    return is_array($data) ? $data : [];
                }

                Log::warning('API MedInfras gagal (getDaftarUnits)', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            } catch (\Exception $e) {
                Log::error('Gagal connect ke API MedInfras (getDaftarUnits)', [
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }

    /**
     * Ambil daftar dokter berdasarkan ID Unit.
     *
     * @return array<int, mixed>
     */
    public function getDaftarDokterByUnitId(string $id): array
    {
        $cacheKey = 'dokterUnitId_'.$id;
        $ttl = (int) config('rsapi.cache_ttl.dokter', 3600);

        return Cache::remember($cacheKey, $ttl, function () use ($id) {
            try {
                $response = $this->apiRequest()
                    ->get("{$this->baseUrl}{$this->medinEndpoint}/api/physician/list/doctor-schedule/{$id}");

                if ($response->successful()) {
                    $data = $this->decodeData($response->json('Data'));

                    return is_array($data) ? $data : [];
                }

                Log::warning('API MedInfras gagal (getDaftarDokterByUnitId)', [
                    'status' => $response->status(),
                    'unit_id' => $id,
                    'body' => $response->body(),
                ]);

                return [];
            } catch (\Exception $e) {
                Log::error('Gagal connect ke API MedInfras (getDaftarDokterByUnitId)', [
                    'unit_id' => $id,
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }

    /**
     * Ambil daftar spesialisasi dokter.
     *
     * @return array<int, mixed>
     */
    public function getDaftarSpesialisasi(): array
    {
        $cacheKey = 'specialty_list_';
        $ttl = (int) config('rsapi.cache_ttl.specialty', 3600);

        return Cache::remember($cacheKey, $ttl, function () {
            try {
                $response = $this->apiRequest()
                    ->get("{$this->baseUrl}{$this->medinEndpoint}/api/reference/master/lst_spc");

                if ($response->successful()) {
                    $data = $this->decodeData($response->json('Data'));

                    return is_array($data) ? $data : [];
                }

                Log::warning('API MedInfras gagal (getDaftarSpesialisasi)', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            } catch (\Exception $e) {
                Log::error('Gagal connect ke API MedInfras (getDaftarSpesialisasi)', [
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }

    /**
     * Ambil daftar spesialisasi langsung dari cache.
     *
     * @return array<int, mixed>
     */
    public function getCachedSpesialisasi(): array
    {
        return Cache::get('specialty_list_', []);
    }

    /**
     * Ambil daftar units langsung dari cache.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, mixed>
     */
    public function getCachedUnits(array $filters = []): array
    {
        $cacheKey = 'units_'.md5(serialize($filters));

        return Cache::get($cacheKey, []);
    }

    /**
     * Ambil jadwal dokter berdasarkan kode spesialisasi.
     *
     * @return array{Success: bool, Message?: string, LeaveSchedule?: array<int, mixed>, ScheduleByDate?: array<int, mixed>, ScheduleRoutine?: array<int, mixed>}
     */
    public function getDokterBySpesialisasi(string $specialtyCode): array
    {
        $cacheKey = 'doctor_specialty_'.$specialtyCode;
        $ttl = (int) config('rsapi.cache_ttl.dokter_by_speciality', 3600);

        return Cache::remember($cacheKey, $ttl, function () use ($specialtyCode) {
            try {
                $response = $this->apiRequest()
                    ->get("{$this->baseUrl}{$this->medinEndpoint}/api/paramedicschedule/base/list/specialty/{$specialtyCode}");

                if ($response->successful()) {
                    $data = $this->decodeData($response->json('Data'));

                    return [
                        'Success' => true,
                        'LeaveSchedule' => $data?->LeaveSchedule ?? [],
                        'ScheduleByDate' => $data?->ScheduleByDate ?? [],
                        'ScheduleRoutine' => $data?->ScheduleRoutine ?? [],
                    ];
                }

                Log::warning('API MedInfras gagal (getDokterBySpesialisasi)', [
                    'status' => $response->status(),
                    'specialty' => $specialtyCode,
                    'body' => $response->body(),
                ]);

                return [
                    'Success' => false,
                    'Message' => "Data gagal diambil dari API Medin untuk spesialisasi {$specialtyCode}",
                ];
            } catch (\Exception $e) {
                Log::error('Gagal connect ke API MedInfras (getDokterBySpesialisasi)', [
                    'specialty' => $specialtyCode,
                    'error' => $e->getMessage(),
                ]);

                return [
                    'Success' => false,
                    'Message' => "Gagal connect ke API MedInfras untuk spesialisasi {$specialtyCode}",
                ];
            }
        });
    }

    /**
     * Ambil semua dokter dari seluruh specialty code,
     * hasil merge disimpan di cache terpisah.
     */
    public function fetchAndCacheAllDokter(): int
    {
        $this->lastWarmupResult = ['status' => 'failed', 'total' => 0, 'failed_specialties' => []];
        if (! config('rsapi.enabled', true)) {
            $this->lastWarmupResult['status'] = 'disabled';
            return 0;
        }
        $spesialisasiList = [];

        foreach ($this->getDaftarSpesialisasi() as $item) {
            if (isset($item->Code)) {
                $spesialisasiList[] = $item->Code;
            } elseif (is_array($item) && isset($item['Code'])) {
                $spesialisasiList[] = $item['Code'];
            }
        }

        if (empty($spesialisasiList)) {
            Log::error('Gagal fetch daftar spesialisasi, fetchAndCacheAllDokter dibatalkan.');

            return 0;
        }

        $allDoctors = [];
        $failed = [];

        foreach ($spesialisasiList as $code) {
            try {
                $data = $this->getDokterBySpesialisasi($code);

                if (! ($data['Success'] ?? false)) {
                    $failed[] = $code;
                    continue;
                }

                if (! is_array($data['ScheduleRoutine'] ?? null)) {
                    $failed[] = $code;
                    continue;
                }

                if (! empty($data['ScheduleRoutine']) && is_array($data['ScheduleRoutine'])) {
                    foreach ($data['ScheduleRoutine'] as $item) {
                        $itemArray = is_object($item) ? (array) $item : (array) $item;
                        $itemArray['SpecialityCode'] = $code;
                        $allDoctors[] = $itemArray;
                    }
                }
            } catch (\Exception $e) {
                $failed[] = $code;
                Log::warning("Gagal fetch specialty {$code}", ['exception_type' => get_class($e)]);

                continue;
            }

            sleep(1);
        }

        $this->lastWarmupResult = [
            'status' => count($allDoctors) === 0 ? 'failed' : ($failed === [] ? 'success' : 'partial'),
            'total' => count($allDoctors),
            'failed_specialties' => array_values(array_unique($failed)),
        ];
        if ($failed !== [] || $allDoctors === []) {
            // Jangan menimpa cache lengkap yang lama dengan hasil parsial atau kosong.
            return 0;
        }

        Cache::put('all_doctors_list', $allDoctors, now()->addHours(12));
        Cache::put('all_doctors_grouped', collect($allDoctors)
            ->groupBy('SpecialityCode')
            ->toArray(), now()->addHours(12));

        return count($allDoctors);
    }

    public function getLastWarmupResult(): array
    {
        return $this->lastWarmupResult;
    }
}
