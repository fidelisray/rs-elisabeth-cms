<?php

declare(strict_types=1);

namespace App\Services\DynamicForm;

use App\Enums\DynamicFormFieldType;
use App\Exceptions\ExportAbortedException;
use App\Models\AuditLog;
use App\Models\DynamicForm;
use App\Models\DynamicFormSubmission;
use App\Models\User;
use App\Services\Export\SpreadsheetExportWriter;
use Generator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DynamicFormExportService
{
    public function __construct(
        private readonly DynamicFormAnalyticsService $analyticsService,
        private readonly SpreadsheetExportWriter $writer,
    ) {}

    /**
     * Ekspor respons terfilter ke CSV dengan UTF-8 BOM + guard performa + audit log non-PII.
     *
     * @param  array{version_id?: int|string|null, date_from?: ?string, date_to?: ?string}  $filters
     *
     * @throws ExportAbortedException
     */
    public function streamCsv(DynamicForm $form, array $filters = [], ?User $actor = null): StreamedResponse
    {
        return $this->export($form, 'csv', $filters, $actor);
    }

    /**
     * Ekspor respons terfilter ke Excel (.xlsx) native OpenSpout + guard performa + audit log non-PII.
     *
     * @param  array{version_id?: int|string|null, date_from?: ?string, date_to?: ?string}  $filters
     *
     * @throws ExportAbortedException
     */
    public function streamXlsx(DynamicForm $form, array $filters = [], ?User $actor = null): BinaryFileResponse
    {
        return $this->export($form, 'xlsx', $filters, $actor);
    }

    /**
     * Susun header dan baris terstruktur yang konsisten antara CSV dan XLSX.
     *
     * @param  array{version_id?: int|string|null, date_from?: ?string, date_to?: ?string}  $filters
     * @return array{headers: list<string>, rows: list<list<mixed>>}
     */
    public function buildDataset(DynamicForm $form, array $filters = []): array
    {
        $prepared = $this->prepareSchemaAndFilters($form, $filters);
        $versionNumbers = $form->versions()->pluck('version_number', 'id')->all();
        $timezone = (string) config('app.timezone', 'Asia/Jakarta');

        $submissions = $this->analyticsService
            ->filteredSubmissionsQuery($form, $prepared['version_id'], $prepared['date_from'], $prepared['date_to'])
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->get();

        $rows = [];
        /** @var DynamicFormSubmission $submission */
        foreach ($submissions as $submission) {
            $rows[] = $this->mapSubmissionRow($submission, $prepared['columns'], $versionNumbers, $timezone);
        }

        return [
            'headers' => $prepared['headers'],
            'rows' => $rows,
        ];
    }

    /**
     * @param  array{version_id?: int|string|null, date_from?: ?string, date_to?: ?string}  $filters
     *
     * @throws ExportAbortedException
     */
    private function export(DynamicForm $form, string $format, array $filters, ?User $actor): BinaryFileResponse|StreamedResponse
    {
        $normalizedFormat = $format === 'csv' ? 'csv' : 'xlsx';
        $prepared = $this->prepareSchemaAndFilters($form, $filters);

        $query = $this->analyticsService
            ->filteredSubmissionsQuery($form, $prepared['version_id'], $prepared['date_from'], $prepared['date_to'])
            ->orderByDesc('submitted_at')
            ->orderByDesc('id');

        $count = (clone $query)->count();
        $effectiveActor = $actor ?? Auth::user();
        $actorId = $effectiveActor instanceof User ? $effectiveActor->id : null;

        $this->writer->enforceGuards($actorId, $count);

        $versionNumbers = $form->versions()->pluck('version_number', 'id')->all();
        $timezone = (string) config('app.timezone', 'Asia/Jakarta');

        $rows = function () use ($query, $prepared, $versionNumbers, $timezone): Generator {
            /** @var DynamicFormSubmission $submission */
            foreach ($query->cursor() as $submission) {
                yield $this->mapSubmissionRow($submission, $prepared['columns'], $versionNumbers, $timezone);
            }
        };

        AuditLog::record('report.exported', $form, [
            'module' => 'dynamic_forms',
            'format' => $normalizedFormat,
            'row_count' => $count,
            'filters' => array_filter([
                'version_id' => $prepared['version_id'],
                'date_from' => $prepared['date_from'],
                'date_to' => $prepared['date_to'],
            ], static fn ($value): bool => $value !== null),
        ]);

        $filename = $this->buildFilename($form, $normalizedFormat);

        if ($normalizedFormat === 'csv') {
            return $this->writer->streamCsv($filename, $prepared['headers'], $rows());
        }

        return $this->writer->downloadXlsx($filename, [
            [
                'name' => $form->title ?: 'Respons',
                'headers' => $prepared['headers'],
                'rows' => $rows(),
            ],
        ]);
    }

    /**
     * @param  array{version_id?: int|string|null, date_from?: ?string, date_to?: ?string}  $filters
     * @return array{
     *     version_id: ?int,
     *     date_from: ?string,
     *     date_to: ?string,
     *     headers: list<string>,
     *     columns: list<array{field: array<string, mixed>, type: DynamicFormFieldType, matrix_row_key: ?string}>
     * }
     */
    private function prepareSchemaAndFilters(DynamicForm $form, array $filters): array
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

        return [
            'version_id' => $versionId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'headers' => $headers,
            'columns' => $columns,
        ];
    }

    /**
     * @param  list<array{field: array<string, mixed>, type: DynamicFormFieldType, matrix_row_key: ?string}>  $columns
     * @param  array<int, int>  $versionNumbers
     * @return list<mixed>
     */
    private function mapSubmissionRow(
        DynamicFormSubmission $submission,
        array $columns,
        array $versionNumbers,
        string $timezone,
    ): array {
        $versionNumber = $versionNumbers[$submission->dynamic_form_version_id] ?? null;
        $submittedAt = $submission->submitted_at
            ? $submission->submitted_at->copy()->timezone($timezone)->format('Y-m-d H:i:s')
            : '';

        $row = [
            (int) $submission->id,
            $versionNumber !== null ? 'v'.$versionNumber : '',
            strtoupper((string) $submission->locale),
            (string) $submission->source,
            $submittedAt,
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

        return $row;
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
        $timezone = (string) config('app.timezone', 'Asia/Jakarta');

        return $slug.'-responses-'.now($timezone)->format('Ymd-His').'.'.$extension;
    }
}
