<?php

namespace App\Services\DynamicForm;

use App\Enums\DynamicFormFieldType;
use App\Models\DynamicForm;
use App\Models\DynamicFormSubmission;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DynamicFormExportService
{
    public function __construct(
        private readonly DynamicFormAnalyticsService $analyticsService,
    ) {}

    /**
     * Ekspor respons terfilter ke CSV dengan UTF-8 BOM.
     *
     * @param  array{version_id?: int|string|null, date_from?: ?string, date_to?: ?string}  $filters
     */
    public function streamCsv(DynamicForm $form, array $filters = []): StreamedResponse
    {
        $dataset = $this->buildDataset($form, $filters);
        $filename = $this->buildFilename($form, 'csv');

        return response()->streamDownload(function () use ($dataset): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }

            // UTF-8 BOM agar Excel langsung membaca karakter UTF-8 dengan benar.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $dataset['headers']);

            foreach ($dataset['rows'] as $row) {
                fputcsv($handle, array_map($this->sanitizeSpreadsheetCell(...), $row));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Ekspor respons terfilter ke Excel (.xlsx) asli menggunakan OpenSpout.
     *
     * @param  array{version_id?: int|string|null, date_from?: ?string, date_to?: ?string}  $filters
     */
    public function streamXlsx(DynamicForm $form, array $filters = []): BinaryFileResponse
    {
        $dataset = $this->buildDataset($form, $filters);
        $filename = $this->buildFilename($form, 'xlsx');

        $tmpFile = tempnam(sys_get_temp_dir(), 'rse_df_xlsx_');
        $xlsxPath = $tmpFile.'.xlsx';
        @unlink($tmpFile);

        $writer = new XlsxWriter;
        $writer->openToFile($xlsxPath);
        $writer->addRow(Row::fromValues($dataset['headers']));

        foreach ($dataset['rows'] as $row) {
            $writer->addRow(Row::fromValues(array_map($this->sanitizeSpreadsheetCell(...), $row)));
        }

        $writer->close();

        return response()->download($xlsxPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Susun header dan baris terstruktur yang konsisten antara CSV dan XLSX.
     *
     * @param  array{version_id?: int|string|null, date_from?: ?string, date_to?: ?string}  $filters
     * @return array{headers: list<string>, rows: list<list<mixed>>}
     */
    public function buildDataset(DynamicForm $form, array $filters = []): array
    {
        $versionId = isset($filters['version_id']) && is_numeric($filters['version_id'])
            ? (int) $filters['version_id']
            : null;
        $dateFrom = filled($filters['date_from'] ?? null) ? (string) $filters['date_from'] : null;
        $dateTo = filled($filters['date_to'] ?? null) ? (string) $filters['date_to'] : null;

        $catalog = array_values(array_filter(
            $this->analyticsService->collectFieldCatalog($form, $versionId),
            fn (array $f): bool => ($f['type'] ?? '') !== DynamicFormFieldType::InfoBlock->value
        ));

        $headers = [
            'ID Submission',
            'Versi',
            'Bahasa',
            'Sumber',
            'Waktu Kirim',
        ];

        $columns = [];
        foreach ($catalog as $field) {
            $type = DynamicFormFieldType::tryFrom((string) ($field['type'] ?? 'short_text')) ?? DynamicFormFieldType::ShortText;
            if ($type === DynamicFormFieldType::MatrixGrid) {
                $matrixRows = (array) ($field['options']['rows'] ?? []);
                foreach ($matrixRows as $mRow) {
                    $rowKey = (string) ($mRow['key'] ?? '');
                    $rowLabel = (string) ($mRow['label'] ?? $rowKey);
                    $headers[] = "{$field['label']}: {$rowLabel}";
                    $columns[] = [
                        'field' => $field,
                        'type' => $type,
                        'matrix_row_key' => $rowKey,
                    ];
                }

                continue;
            }

            $headers[] = (string) $field['label'];
            $columns[] = [
                'field' => $field,
                'type' => $type,
                'matrix_row_key' => null,
            ];
        }

        $submissions = $this->analyticsService
            ->filteredSubmissionsQuery($form, $versionId, $dateFrom, $dateTo)
            ->with(['version:id,version_number'])
            ->orderByDesc('submitted_at')
            ->get();

        $rows = [];
        /** @var DynamicFormSubmission $submission */
        foreach ($submissions as $submission) {
            $row = [
                $submission->id,
                $submission->version ? 'v'.$submission->version->version_number : '',
                strtoupper((string) $submission->locale),
                (string) $submission->source,
                optional($submission->submitted_at)->format('Y-m-d H:i:s') ?? '',
            ];

            $answers = (array) ($submission->answers ?? []);
            foreach ($columns as $colDef) {
                $row[] = $this->formatAnswerCell(
                    $colDef['field'],
                    $colDef['type'],
                    $answers[$colDef['field']['field_key']] ?? null,
                    $colDef['matrix_row_key']
                );
            }

            $rows[] = $row;
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function formatAnswerCell(
        array $field,
        DynamicFormFieldType $type,
        mixed $value,
        ?string $matrixRowKey,
    ): mixed {
        if ($value === null || $value === '' || $value === []) {
            return '';
        }

        $options = (array) ($field['options'] ?? []);

        if ($type === DynamicFormFieldType::Radio) {
            $choiceMap = $this->choiceLabelMap($options);
            $selected = is_array($value) ? (string) ($value['selected'] ?? '') : (string) $value;
            if ($selected === DynamicFormLogicEvaluator::OTHER_CHOICE_KEY) {
                $otherText = is_array($value) ? trim((string) ($value['other_text'] ?? '')) : '';

                return $otherText !== '' ? "Lainnya: {$otherText}" : 'Lainnya';
            }

            return $choiceMap[$selected] ?? $selected;
        }

        if ($type === DynamicFormFieldType::Dropdown) {
            $choiceMap = $this->choiceLabelMap($options);

            return $choiceMap[(string) $value] ?? (string) $value;
        }

        if ($type === DynamicFormFieldType::Checkbox && is_array($value)) {
            $choiceMap = $this->choiceLabelMap($options);
            $selected = (array) ($value['selected'] ?? $value);
            $otherText = trim((string) ($value['other_text'] ?? ''));

            return collect($selected)->map(function ($key) use ($choiceMap, $otherText): string {
                $k = (string) $key;
                if ($k === DynamicFormLogicEvaluator::OTHER_CHOICE_KEY) {
                    return $otherText !== '' ? "Lainnya: {$otherText}" : 'Lainnya';
                }

                return $choiceMap[$k] ?? $k;
            })->implode(', ');
        }

        if ($type === DynamicFormFieldType::MatrixGrid && is_array($value) && $matrixRowKey !== null) {
            $cellVal = $value[$matrixRowKey] ?? null;
            if ($cellVal === null) {
                return '';
            }
            $colMap = collect((array) ($options['columns'] ?? []))
                ->mapWithKeys(fn ($c): array => [(string) ($c['key'] ?? '') => (string) ($c['label'] ?? '')])
                ->all();

            if (is_array($cellVal)) {
                return collect($cellVal)->map(fn ($k) => $colMap[(string) $k] ?? (string) $k)->implode(', ');
            }

            return $colMap[(string) $cellVal] ?? (string) $cellVal;
        }

        if ($type === DynamicFormFieldType::FileUpload && is_array($value)) {
            return collect($value)->pluck('original_name')->filter()->implode(', ');
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        return is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, string>
     */
    private function choiceLabelMap(array $options): array
    {
        return collect((array) ($options['choices'] ?? []))
            ->mapWithKeys(fn ($c): array => [(string) ($c['key'] ?? '') => (string) ($c['label'] ?? '')])
            ->all();
    }

    private function buildFilename(DynamicForm $form, string $extension): string
    {
        $slug = Str::slug($form->slug ?: $form->title) ?: 'dynamic-form';

        return $slug.'-responses-'.now()->format('Ymd-His').'.'.$extension;
    }

    /**
     * Mencegah CSV/Spreadsheet formula injection saat file dibuka di Excel.
     */
    private function sanitizeSpreadsheetCell(mixed $value): mixed
    {
        if (is_int($value) || is_float($value) || $value === null) {
            return $value;
        }

        $str = (string) $value;
        if ($str !== '' && preg_match('/^[=+\-@\t\r]/', $str) === 1) {
            return "'".$str;
        }

        return $str;
    }
}
