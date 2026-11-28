<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Exceptions\ExportAbortedException;
use App\Models\AuditLog;
use App\Models\Feedback;
use App\Models\User;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ekspor teraudit untuk Kritik & Saran pasien (CSV & XLSX).
 *
 * ATURAN KEAMANAN:
 * - Membutuhkan permission Export:Feedback (via FeedbackPolicy::export).
 * - Kolom "Catatan Internal" 100% eksklusif untuk super_admin.
 * - Mendukung Mode Anonim (menyembunyikan Nama Pelapor, Nama Pasien, dan Nomor Telepon).
 * - Header & nilai status di dalam file selalu baku Bahasa Indonesia demi stabilitas akreditasi RS.
 * - AuditLog hanya mencatat metadata terstruktur non-PII (tidak pernah menyalin teks pencarian/keluhan/catatan).
 */
class FeedbackExportService
{
    public function __construct(
        private readonly SpreadsheetExportWriter $writer,
    ) {}

    /**
     * @param  Builder<Feedback>  $filteredQuery
     * @param  array<string, mixed>  $safeFilters
     *
     * @throws ExportAbortedException
     */
    public function export(
        Builder $filteredQuery,
        User $actor,
        string $format = 'xlsx',
        bool $anonymized = false,
        array $safeFilters = [],
    ): BinaryFileResponse|StreamedResponse {
        Gate::forUser($actor)->authorize('export', Feedback::class);

        $normalizedFormat = $format === 'csv' ? 'csv' : 'xlsx';
        $count = (clone $filteredQuery)->count();

        $this->writer->enforceGuards($actor->id, $count);

        $includeInternalNote = $actor->isSuperAdmin();
        $headers = $this->headers($anonymized, $includeInternalNote);
        $timezone = (string) config('app.timezone', 'Asia/Jakarta');

        $orderedQuery = (clone $filteredQuery)
            ->reorder()
            ->orderByDesc('submitted_at')
            ->orderByDesc('id');

        $rowGenerator = function () use ($orderedQuery, $anonymized, $includeInternalNote, $timezone): Generator {
            /** @var Feedback $feedback */
            foreach ($orderedQuery->cursor() as $feedback) {
                yield $this->mapRow($feedback, $anonymized, $includeInternalNote, $timezone);
            }
        };

        AuditLog::record('report.exported', null, [
            'module' => 'feedbacks',
            'format' => $normalizedFormat,
            'row_count' => $count,
            'filters' => $safeFilters,
            'anonymized' => $anonymized,
            'included_internal_note' => $includeInternalNote,
        ]);

        $prefix = $anonymized ? 'kritik-saran-anonim' : 'kritik-saran';
        $filename = $prefix.'-'.now($timezone)->format('Ymd-His').'.'.$normalizedFormat;

        if ($normalizedFormat === 'csv') {
            return $this->writer->streamCsv($filename, $headers, $rowGenerator());
        }

        return $this->writer->downloadXlsx($filename, [
            [
                'name' => 'Kritik & Saran',
                'headers' => $headers,
                'rows' => $rowGenerator(),
            ],
        ]);
    }

    /**
     * @return list<string>
     */
    public function headers(bool $anonymized, bool $includeInternalNote): array
    {
        $headers = [
            'Nomor Tiket',
            'Waktu Kirim',
            'Status',
        ];

        if (! $anonymized) {
            $headers[] = 'Nama Pelapor';
        }

        $headers[] = 'Hubungan dengan Pasien';

        if (! $anonymized) {
            $headers[] = 'Nama Pasien';
            $headers[] = 'Nomor Telepon';
        }

        $headers[] = 'Kategori';
        $headers[] = 'Isi Keluhan';
        $headers[] = 'Solusi yang Diharapkan';

        if ($includeInternalNote) {
            $headers[] = 'Catatan Internal';
        }

        return $headers;
    }

    /**
     * @return list<mixed>
     */
    private function mapRow(Feedback $feedback, bool $anonymized, bool $includeInternalNote, string $timezone): array
    {
        $submittedAt = $feedback->submitted_at
            ? $feedback->submitted_at->copy()->timezone($timezone)->format('Y-m-d H:i:s')
            : '';

        $relation = trim((string) $feedback->relation_to_patient);
        $relationOther = trim((string) ($feedback->relation_other ?? ''));
        if ($relationOther !== '') {
            $relation = $relation !== '' ? "{$relation} ({$relationOther})" : $relationOther;
        }

        $categories = array_values(array_filter(
            (array) ($feedback->categories ?? []),
            static fn ($item): bool => is_string($item) && trim($item) !== '',
        ));
        $categoryOther = trim((string) ($feedback->category_other ?? ''));
        if ($categoryOther !== '') {
            $categories[] = "Lainnya: {$categoryOther}";
        }

        $row = [
            (string) $feedback->ticket_number,
            $submittedAt,
            self::bakuStatusLabel($feedback->status),
        ];

        if (! $anonymized) {
            $row[] = (string) $feedback->reporter_name;
        }

        $row[] = $relation;

        if (! $anonymized) {
            $row[] = (string) $feedback->patient_name;
            // Disimpan eksplisit sebagai string agar angka 0 di depan nomor telepon tidak hilang di Excel.
            $row[] = (string) $feedback->phone_number;
        }

        $row[] = implode(', ', $categories);
        $row[] = (string) $feedback->complaint_detail;
        $row[] = (string) ($feedback->expected_solution ?? '');

        if ($includeInternalNote) {
            $row[] = (string) ($feedback->internal_note ?? '');
        }

        return $row;
    }

    public static function bakuStatusLabel(?string $status): string
    {
        return match ($status) {
            'baru' => 'Baru',
            'diproses' => 'Diproses',
            'selesai' => 'Selesai',
            default => (string) ($status ?? ''),
        };
    }
}
