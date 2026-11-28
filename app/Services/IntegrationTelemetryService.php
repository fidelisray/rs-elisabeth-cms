<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Doctor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Layanan telemetri operasional zero-migration untuk System & Integrations Monitor
 * dan KPI Dashboard.
 *
 * ATURAN KEAMANAN:
 * - Tidak pernah menyimpan atau mengekspos secret key, Cons-ID, password, token,
 *   URL kredensial, payload mentah failed_jobs, maupun PII pasien.
 * - Seluruh operasi pembacaan/penulisan cache bersifat fail-safe agar gangguan cache
 *   tidak pernah memutus alur webhook atau sinkronisasi SIMRS.
 */
class IntegrationTelemetryService
{
    public const CACHE_KEY_WEBHOOK_LAST = 'integration:telemetry:webhook_last';

    public const CACHE_KEY_DOCTOR_SYNC_LAST = 'integration:telemetry:doctor_sync_last';

    private const TELEMETRY_TTL_DAYS = 30;

    /**
     * @var list<string>
     */
    private const SENSITIVE_METADATA_FRAGMENTS = [
        'password',
        'secret',
        'token',
        'signature',
        'cons_id',
        'consid',
        'api_key',
        'apikey',
        'authorization',
        'phone',
        'reporter_name',
        'patient_name',
        'complaint',
        'internal_note',
        'answers',
    ];

    /**
     * Rekam hasil pengiriman webhook terakhir ke Cache store (non-sensitif).
     */
    public static function recordWebhookResult(
        string $status,
        string $module,
        string $event,
        ?int $httpStatus = null,
        ?string $errorSummary = null,
    ): void {
        try {
            Cache::put(
                self::CACHE_KEY_WEBHOOK_LAST,
                [
                    'status' => $status,
                    'module' => $module,
                    'event' => $event,
                    'http_status' => $httpStatus,
                    'error_summary' => $errorSummary !== null ? self::sanitizeMessage($errorSummary) : null,
                    'recorded_at' => now()->toIso8601String(),
                ],
                now()->addDays(self::TELEMETRY_TTL_DAYS),
            );
        } catch (\Throwable) {
            // Jangan gagalkan pengiriman webhook bila cache store sementara bermasalah.
        }
    }

    /**
     * Rekam hasil sinkronisasi SIMRS MedInfras terakhir ke Cache store (non-sensitif).
     *
     * @param  array<string, mixed>  $result
     */
    public static function recordDoctorSyncResult(array $result): void
    {
        try {
            Cache::put(
                self::CACHE_KEY_DOCTOR_SYNC_LAST,
                [
                    'status' => (string) ($result['status'] ?? 'unknown'),
                    'created' => (int) ($result['created'] ?? 0),
                    'updated' => (int) ($result['updated'] ?? 0),
                    'unchanged' => (int) ($result['unchanged'] ?? 0),
                    'total' => (int) ($result['total'] ?? 0),
                    'failed_specialties' => array_values(array_map('strval', (array) ($result['failed_specialties'] ?? []))),
                    'excluded_specialties' => array_values(array_map('strval', (array) ($result['excluded_specialties'] ?? []))),
                    'recorded_at' => now()->toIso8601String(),
                ],
                now()->addDays(self::TELEMETRY_TTL_DAYS),
            );
        } catch (\Throwable) {
            // Jangan gagalkan sinkronisasi SIMRS bila cache store sementara bermasalah.
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getLastWebhookSnapshot(): ?array
    {
        try {
            $snapshot = Cache::get(self::CACHE_KEY_WEBHOOK_LAST);

            return is_array($snapshot) ? $snapshot : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getLastDoctorSyncSnapshot(): ?array
    {
        try {
            $snapshot = Cache::get(self::CACHE_KEY_DOCTOR_SYNC_LAST);

            return is_array($snapshot) ? $snapshot : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Hitung total baris failed_jobs secara aman lintas driver.
     */
    public function getFailedJobsCount(): int
    {
        try {
            if (! Schema::hasTable('failed_jobs')) {
                return 0;
            }

            return (int) DB::table('failed_jobs')->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Ringkasan read-only aman dari tabel failed_jobs (maks N terbaru).
     * Payload mentah TIDAK pernah dikembalikan untuk mencegah kebocoran PII/secret.
     *
     * @return array{total: int, recent: list<array{uuid: string, job: string, queue: string, connection: string, failed_at: string, error_summary: string}>}
     */
    public function getFailedJobsSummary(int $limit = 10): array
    {
        try {
            if (! Schema::hasTable('failed_jobs')) {
                return ['total' => 0, 'recent' => []];
            }

            $total = (int) DB::table('failed_jobs')->count();
            if ($total === 0) {
                return ['total' => 0, 'recent' => []];
            }

            $rows = DB::table('failed_jobs')
                ->select(['uuid', 'connection', 'queue', 'payload', 'exception', 'failed_at'])
                ->orderByDesc('failed_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get();

            $recent = [];
            foreach ($rows as $row) {
                $recent[] = [
                    'uuid' => (string) ($row->uuid ?? '-'),
                    'job' => $this->extractJobName((string) ($row->payload ?? '')),
                    'queue' => (string) ($row->queue ?? 'default'),
                    'connection' => (string) ($row->connection ?? '-'),
                    'failed_at' => (string) ($row->failed_at ?? '-'),
                    'error_summary' => self::sanitizeMessage($this->extractFirstExceptionLine((string) ($row->exception ?? ''))),
                ];
            }

            return [
                'total' => $total,
                'recent' => $recent,
            ];
        } catch (\Throwable) {
            return ['total' => 0, 'recent' => []];
        }
    }

    /**
     * Kumpulkan status konfigurasi non-sensitif untuk halaman Sistem & Integrasi.
     *
     * @return array<string, mixed>
     */
    public function getSystemConfigFlags(): array
    {
        $clients = array_filter(
            (array) config('cms_api.clients', []),
            static fn ($secret, $consId): bool => filled($consId) && filled($secret),
            ARRAY_FILTER_USE_BOTH,
        );

        $doctors = Doctor::query()
            ->select(['id', 'is_active', 'bio', 'location', 'experience', 'education'])
            ->get();

        $activeDoctors = $doctors->where('is_active', true);
        $completeActiveDoctors = $activeDoctors->filter(fn (Doctor $doctor): bool => $doctor->is_profile_complete)->count();

        return [
            'webhook_configured' => filled(config('web_frontend.webhook_url'))
                && filled(config('web_frontend.cons_id'))
                && filled(config('web_frontend.secret_key')),
            'webhook_timeout' => (int) config('web_frontend.timeout', 5),
            'public_url_configured' => filled(config('web_frontend.public_url')),
            'cms_api_clients_count' => count($clients),
            'rs_api_enabled' => (bool) config('rsapi.enabled', true),
            'rs_api_blacklist_specialties' => array_values((array) config('rsapi.blacklist_specialties', [])),
            'media_disk' => (string) config('filesystems.media_disk', 's3'),
            'private_disk' => (string) config('filesystems.dynamic_form_private_disk', 's3_private'),
            'cache_store' => (string) config('cache.default', 'database'),
            'queue_connection' => (string) config('queue.default', 'database'),
            'app_timezone' => (string) config('app.timezone', 'Asia/Jakarta'),
            'total_doctors_count' => $doctors->count(),
            'active_doctors_count' => $activeDoctors->count(),
            'complete_doctors_count' => $completeActiveDoctors,
        ];
    }

    /**
     * Sanitasi defensif pada array metadata (dipakai oleh AuditLogsPage sebelum ditampilkan).
     *
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public static function sanitizeMetadata(array $metadata): array
    {
        $clean = [];

        foreach ($metadata as $key => $value) {
            $keyString = (string) $key;
            $lowerKey = strtolower($keyString);

            $isSensitive = false;
            foreach (self::SENSITIVE_METADATA_FRAGMENTS as $fragment) {
                if (str_contains($lowerKey, $fragment)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $clean[$keyString] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $clean[$keyString] = self::sanitizeMetadata($value);
            } elseif (is_string($value)) {
                $clean[$keyString] = self::sanitizeMessage($value, 240);
            } else {
                $clean[$keyString] = $value;
            }
        }

        return $clean;
    }

    /**
     * Bersihkan string error/pesan dari potensi kredensial URL, token panjang, email, dan nomor telepon.
     */
    public static function sanitizeMessage(string $message, int $maxLength = 180): string
    {
        $singleLine = trim((string) preg_replace('/\s+/', ' ', $message));

        if ($singleLine === '') {
            return '-';
        }

        // Samarkan kredensial inline pada URL (https://user:pass@host -> https://[redacted]@host)
        $singleLine = (string) preg_replace('#(https?://)[^/\s@]+@#i', '$1[redacted]@', $singleLine);
        // Samarkan query string URL agar token/signature tidak ikut terbaca
        $singleLine = (string) preg_replace('#(https?://[^\s?]+)\?[^\s]+#i', '$1?[redacted]', $singleLine);
        // Samarkan pola Bearer token atau key=value sensitif
        $singleLine = (string) preg_replace('/\b(Bearer\s+)[A-Za-z0-9._\-]+/i', '$1[redacted]', $singleLine);
        $singleLine = (string) preg_replace('/\b(secret|token|password|api[_-]?key|signature)\s*[:=]\s*\S+/i', '$1=[redacted]', $singleLine);
        // Samarkan nomor telepon panjang (>= 9 digit)
        $singleLine = (string) preg_replace('/\b(?:\+62|62|0)\d{8,14}\b/', '[redacted-phone]', $singleLine);

        return Str::limit($singleLine, $maxLength, '...');
    }

    private function extractJobName(string $rawPayload): string
    {
        if ($rawPayload === '') {
            return 'UnknownJob';
        }

        $decoded = json_decode($rawPayload, true);
        if (! is_array($decoded)) {
            return 'UnknownJob';
        }

        $displayName = $decoded['displayName'] ?? $decoded['job'] ?? 'UnknownJob';

        return is_string($displayName) && trim($displayName) !== ''
            ? class_basename($displayName)
            : 'UnknownJob';
    }

    private function extractFirstExceptionLine(string $exception): string
    {
        $trimmed = trim($exception);
        if ($trimmed === '') {
            return '-';
        }

        $lines = preg_split('/\r\n|\r|\n/', $trimmed);

        return trim((string) ($lines[0] ?? '-'));
    }
}
