<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Exceptions\ExportAbortedException;
use App\Models\AuditLog;
use App\Models\QuestionnaireResponse;
use App\Models\User;
use App\Services\FeedbackService;
use App\Support\QuestionnaireDefinition;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ekspor teraudit untuk Kuesioner Kepuasan Pasien (IGD, Rawat Jalan, Rawat Inap).
 *
 * Struktur adaptif:
 * - Jika 1 unit layanan difilter: 1 sheet XLSX / 1 file CSV presisi berisi pertanyaan unit tersebut.
 * - Jika Semua Layanan: XLSX otomatis membentuk 3 worksheet terpisah (IGD, Rawat Jalan, Rawat Inap);
 *   CSV memakai kolom pertanyaan ter-prefix unit agar tidak ada tabrakan label.
 * - Skor Kepuasan (%) ditulis sebagai integer numerik (0-100) siap AVERAGE() / Pivot Table.
 */
class QuestionnaireExportService
{
    public function __construct(
        private readonly SpreadsheetExportWriter $writer,
        private readonly FeedbackService $feedbackService,
    ) {}

    /**
     * @param  Builder<QuestionnaireResponse>  $filteredQuery
     * @param  array<string, mixed>  $safeFilters
     *
     * @throws ExportAbortedException
     */
    public function export(
        Builder $filteredQuery,
        User $actor,
        string $format = 'xlsx',
        ?string $selectedUnit = null,
        array $safeFilters = [],
    ): BinaryFileResponse|StreamedResponse {
        Gate::forUser($actor)->authorize('export', QuestionnaireResponse::class);

        $normalizedFormat = $format === 'csv' ? 'csv' : 'xlsx';
        $unit = (is_string($selectedUnit) && array_key_exists($selectedUnit, QuestionnaireResponse::TYPES))
            ? $selectedUnit
            : null;

        $count = (clone $filteredQuery)->count();
        $this->writer->enforceGuards($actor->id, $count);

        $timezone = (string) config('app.timezone', 'Asia/Jakarta');

        AuditLog::record('report.exported', null, [
            'module' => 'questionnaires',
            'format' => $normalizedFormat,
            'row_count' => $count,
            'filters' => $safeFilters,
        ]);

        $unitSlug = $unit ?? 'semua';
        $filename = 'kuesioner-kepuasan-'.$unitSlug.'-'.now($timezone)->format('Ymd-His').'.'.$normalizedFormat;

        if ($unit !== null) {
            $headers = $this->singleUnitHeaders($unit);
            $orderedQuery = (clone $filteredQuery)
                ->reorder()
                ->orderByDesc('submitted_at')
                ->orderByDesc('id');

            $rows = function () use ($orderedQuery, $unit, $timezone): Generator {
                /** @var QuestionnaireResponse $record */
                foreach ($orderedQuery->cursor() as $record) {
                    yield $this->mapSingleUnitRow($record, $unit, $timezone);
                }
            };

            if ($normalizedFormat === 'csv') {
                return $this->writer->streamCsv($filename, $headers, $rows());
            }

            return $this->writer->downloadXlsx($filename, [
                [
                    'name' => QuestionnaireResponse::TYPES[$unit],
                    'headers' => $headers,
                    'rows' => $rows(),
                ],
            ]);
        }

        // Semua Layanan: XLSX 3 worksheet terpisah (IGD, Rawat Jalan, Rawat Inap)
        if ($normalizedFormat === 'xlsx') {
            $sheets = [];
            foreach (QuestionnaireResponse::TYPES as $typeKey => $typeLabel) {
                $unitQuery = (clone $filteredQuery)
                    ->where('questionnaire_type', $typeKey)
                    ->reorder()
                    ->orderByDesc('submitted_at')
                    ->orderByDesc('id');

                $sheets[] = [
                    'name' => $typeLabel,
                    'headers' => $this->singleUnitHeaders($typeKey),
                    'rows' => (function () use ($unitQuery, $typeKey, $timezone): Generator {
                        /** @var QuestionnaireResponse $record */
                        foreach ($unitQuery->cursor() as $record) {
                            yield $this->mapSingleUnitRow($record, $typeKey, $timezone);
                        }
                    })(),
                ];
            }

            return $this->writer->downloadXlsx($filename, $sheets);
        }

        // Semua Layanan CSV: 1 tabel datar dengan header pertanyaan ter-prefix unit [IGD] / [Rawat Jalan] / [Rawat Inap]
        $combinedColumns = $this->allUnitsQuestionColumns();
        $headers = array_merge(
            $this->baseHeaders(),
            array_map(static fn (array $col): string => $col['header'], $combinedColumns),
            ['Saran'],
        );

        $orderedQuery = (clone $filteredQuery)
            ->reorder()
            ->orderByDesc('submitted_at')
            ->orderByDesc('id');

        $rows = function () use ($orderedQuery, $combinedColumns, $timezone): Generator {
            /** @var QuestionnaireResponse $record */
            foreach ($orderedQuery->cursor() as $record) {
                yield $this->mapCombinedRow($record, $combinedColumns, $timezone);
            }
        };

        return $this->writer->streamCsv($filename, $headers, $rows());
    }

    /**
     * @return list<string>
     */
    private function baseHeaders(): array
    {
        return [
            'ID Respons',
            'Waktu Kirim',
            'Unit Layanan',
            'Kelompok Usia',
            'Jenis Kelamin',
            'Cara Bayar',
            'Skor Kepuasan (%)',
        ];
    }

    /**
     * @return list<string>
     */
    public function singleUnitHeaders(string $unit): array
    {
        $questionLabels = array_map(
            static fn (string $code): string => QuestionnaireDefinition::label($code),
            array_keys(QuestionnaireDefinition::QUESTIONS[$unit] ?? []),
        );

        return array_merge($this->baseHeaders(), $questionLabels, ['Saran']);
    }

    /**
     * @return list<array{unit: string, code: string, header: string}>
     */
    private function allUnitsQuestionColumns(): array
    {
        $columns = [];
        foreach (QuestionnaireResponse::TYPES as $typeKey => $typeLabel) {
            foreach (array_keys(QuestionnaireDefinition::QUESTIONS[$typeKey] ?? []) as $code) {
                $columns[] = [
                    'unit' => $typeKey,
                    'code' => $code,
                    'header' => "[{$typeLabel}] ".QuestionnaireDefinition::label($code),
                ];
            }
        }

        return $columns;
    }

    /**
     * @return list<mixed>
     */
    private function mapSingleUnitRow(QuestionnaireResponse $record, string $unit, string $timezone): array
    {
        $ratings = (array) ($record->ratings ?? []);
        $row = $this->mapBaseValues($record, $ratings, $timezone);

        foreach (array_keys(QuestionnaireDefinition::QUESTIONS[$unit] ?? []) as $code) {
            $row[] = (string) ($ratings[$code] ?? '');
        }

        $row[] = (string) ($record->suggestions ?? '');

        return $row;
    }

    /**
     * @param  list<array{unit: string, code: string, header: string}>  $columns
     * @return list<mixed>
     */
    private function mapCombinedRow(QuestionnaireResponse $record, array $columns, string $timezone): array
    {
        $ratings = (array) ($record->ratings ?? []);
        $row = $this->mapBaseValues($record, $ratings, $timezone);

        foreach ($columns as $col) {
            $row[] = $record->questionnaire_type === $col['unit']
                ? (string) ($ratings[$col['code']] ?? '')
                : '';
        }

        $row[] = (string) ($record->suggestions ?? '');

        return $row;
    }

    /**
     * @param  array<string, mixed>  $ratings
     * @return list<mixed>
     */
    private function mapBaseValues(QuestionnaireResponse $record, array $ratings, string $timezone): array
    {
        $score = $this->feedbackService->calculateResponseScore($ratings);
        $submittedAt = $record->submitted_at
            ? $record->submitted_at->copy()->timezone($timezone)->format('Y-m-d H:i:s')
            : '';

        return [
            (int) $record->id,
            $submittedAt,
            QuestionnaireResponse::TYPES[$record->questionnaire_type] ?? (string) $record->questionnaire_type,
            (string) ($record->age_group ?? ''),
            (string) ($record->gender ?? ''),
            (string) ($record->payment_method ?? ''),
            (int) $score['percent'],
        ];
    }
}
