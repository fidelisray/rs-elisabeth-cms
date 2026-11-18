<?php

namespace App\Services\DynamicForm;

use App\Enums\DynamicFormFieldType;
use App\Enums\DynamicFormMode;
use App\Enums\FeedbackTicketStatus;
use App\Models\DynamicForm;
use App\Models\DynamicFormSubmission;
use App\Models\DynamicFormSubmissionAnswer;
use App\Models\DynamicFormVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DynamicFormAnalyticsService
{
    public function __construct(
        private readonly DynamicFormVersionService $versionService,
    ) {}

    /**
     * Hasilkan ringkasan metrik dan analitik per pertanyaan berdasarkan filter aktif.
     *
     * @param  array{version_id?: int|string|null, date_from?: ?string, date_to?: ?string}  $filters
     * @return array<string, mixed>
     */
    public function summarize(DynamicForm $form, array $filters = []): array
    {
        $versionId = isset($filters['version_id']) && is_numeric($filters['version_id'])
            ? (int) $filters['version_id']
            : null;
        $dateFrom = filled($filters['date_from'] ?? null) ? (string) $filters['date_from'] : null;
        $dateTo = filled($filters['date_to'] ?? null) ? (string) $filters['date_to'] : null;

        $submissionQuery = $this->filteredSubmissionsQuery($form, $versionId, $dateFrom, $dateTo);

        $totalSubmissions = (clone $submissionQuery)->count();
        $lastSubmittedRaw = (clone $submissionQuery)->max('submitted_at');
        $lastSubmittedAt = $lastSubmittedRaw ? Carbon::parse($lastSubmittedRaw) : null;

        $statusCounts = [
            FeedbackTicketStatus::Baru->value => 0,
            FeedbackTicketStatus::Diproses->value => 0,
            FeedbackTicketStatus::Selesai->value => 0,
        ];

        if ($form->mode === DynamicFormMode::Feedback) {
            $groupedStatuses = (clone $submissionQuery)
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            foreach ($statusCounts as $statusKey => $_) {
                $statusCounts[$statusKey] = (int) ($groupedStatuses[$statusKey] ?? 0);
            }
        }

        $fieldCatalog = $this->collectFieldCatalog($form, $versionId);
        $inputFields = array_values(array_filter(
            $fieldCatalog,
            fn (array $f): bool => ($f['type'] ?? '') !== DynamicFormFieldType::InfoBlock->value
        ));

        $submissionIdSubquery = (clone $submissionQuery)->select('id');
        $answersByField = DynamicFormSubmissionAnswer::query()
            ->where('dynamic_form_id', $form->id)
            ->whereIn('submission_id', $submissionIdSubquery)
            ->with(['submission:id,ticket_number,submitted_at'])
            ->orderByDesc('submission_id')
            ->get()
            ->groupBy('field_key');

        $questionSummaries = [];
        $totalAnswerCount = 0;

        foreach ($inputFields as $field) {
            $fieldKey = (string) $field['field_key'];
            $rows = $answersByField->get($fieldKey, collect());
            $distinctSubmissionCount = $rows->pluck('submission_id')->unique()->count();
            $totalAnswerCount += $distinctSubmissionCount;

            $questionSummaries[] = $this->buildFieldSummary($field, $rows, $distinctSubmissionCount);
        }

        $avgFillRate = null;
        if ($totalSubmissions > 0 && count($inputFields) > 0) {
            $avgFillRate = round(($totalAnswerCount / ($totalSubmissions * count($inputFields))) * 100, 1);
        }

        return [
            'filters' => [
                'version_id' => $versionId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'summary' => [
                'total_submissions' => $totalSubmissions,
                'last_submitted_at' => $lastSubmittedAt,
                'avg_fill_rate' => $avgFillRate,
                'status_counts' => $statusCounts,
                'active_version_number' => $form->publishedVersion?->version_number,
            ],
            'questions' => $questionSummaries,
        ];
    }

    /**
     * Katalog gabungan field berdasarkan field_key lintas versi (atau satu versi spesifik).
     *
     * @return list<array<string, mixed>>
     */
    public function collectFieldCatalog(DynamicForm $form, ?int $versionId = null): array
    {
        if ($versionId !== null) {
            $version = DynamicFormVersion::where('dynamic_form_id', $form->id)
                ->where('id', $versionId)
                ->first();

            if (! $version) {
                return [];
            }

            $snapshot = $this->resolveVersionSnapshot($version);

            return $this->flattenSnapshotFields($snapshot, false);
        }

        $versions = DynamicFormVersion::where('dynamic_form_id', $form->id)
            ->orderBy('version_number')
            ->get();

        if ($versions->isEmpty()) {
            return [];
        }

        $merged = [];
        $latestVersionId = $form->published_version_id ?? $versions->last()->id;
        $latestKeys = [];

        foreach ($versions as $version) {
            $snapshot = $this->resolveVersionSnapshot($version);
            $fields = $this->flattenSnapshotFields($snapshot, false);

            foreach ($fields as $field) {
                $key = (string) $field['field_key'];
                if ($version->id === $latestVersionId) {
                    $latestKeys[$key] = true;
                }
                $merged[$key] = $field;
            }
        }

        foreach ($merged as $key => &$field) {
            $field['is_legacy'] = ! isset($latestKeys[$key]);
        }
        unset($field);

        return array_values($merged);
    }

    /**
     * @return Builder<DynamicFormSubmission>
     */
    public function filteredSubmissionsQuery(
        DynamicForm $form,
        ?int $versionId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): Builder {
        $query = DynamicFormSubmission::query()
            ->where('dynamic_form_id', $form->id);

        if ($versionId !== null) {
            $query->where('dynamic_form_version_id', $versionId);
        }

        if (filled($dateFrom)) {
            $query->where('submitted_at', '>=', Carbon::parse((string) $dateFrom)->startOfDay());
        }

        if (filled($dateTo)) {
            $query->where('submitted_at', '<=', Carbon::parse((string) $dateTo)->endOfDay());
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveVersionSnapshot(DynamicFormVersion $version): array
    {
        if (is_array($version->schema_snapshot) && ! empty($version->schema_snapshot['sections'])) {
            return $version->schema_snapshot;
        }

        return $this->versionService->buildSchemaSnapshot($version);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return list<array<string, mixed>>
     */
    private function flattenSnapshotFields(array $snapshot, bool $isLegacy): array
    {
        $fields = [];
        $sections = array_values((array) ($snapshot['sections'] ?? []));
        usort($sections, fn (array $a, array $b): int => ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0)));

        foreach ($sections as $section) {
            $sectionFields = array_values((array) ($section['fields'] ?? []));
            usort($sectionFields, fn (array $a, array $b): int => ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0)));

            foreach ($sectionFields as $field) {
                $field['section_title'] = (string) ($section['title'] ?? '');
                $field['is_legacy'] = $isLegacy;
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  Collection<int, DynamicFormSubmissionAnswer>  $rows
     * @return array<string, mixed>
     */
    private function buildFieldSummary(array $field, $rows, int $answeredCount): array
    {
        $type = DynamicFormFieldType::tryFrom((string) ($field['type'] ?? 'short_text')) ?? DynamicFormFieldType::ShortText;
        $options = (array) ($field['options'] ?? []);

        $base = [
            'field_key' => (string) $field['field_key'],
            'label' => (string) $field['label'],
            'section_title' => (string) ($field['section_title'] ?? ''),
            'type' => $type->value,
            'type_label' => $type->label(),
            'is_legacy' => (bool) ($field['is_legacy'] ?? false),
            'answered_count' => $answeredCount,
        ];

        return match ($type) {
            DynamicFormFieldType::Radio, DynamicFormFieldType::Dropdown => array_merge(
                $base,
                $this->summarizeSingleChoice($rows, $options, $answeredCount)
            ),
            DynamicFormFieldType::Checkbox => array_merge(
                $base,
                $this->summarizeMultiChoice($rows, $options, $answeredCount)
            ),
            DynamicFormFieldType::LinearScale, DynamicFormFieldType::Rating => array_merge(
                $base,
                $this->summarizeNumericScale($type, $rows, $options, $answeredCount)
            ),
            DynamicFormFieldType::MatrixGrid => array_merge(
                $base,
                $this->summarizeMatrix($rows, $options)
            ),
            DynamicFormFieldType::FileUpload => array_merge(
                $base,
                $this->summarizeFiles($rows)
            ),
            default => array_merge(
                $base,
                $this->summarizeTextResponses($rows)
            ),
        };
    }

    /**
     * @param  Collection<int, DynamicFormSubmissionAnswer>  $rows
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function summarizeSingleChoice($rows, array $options, int $answeredCount): array
    {
        $counts = [];
        $otherTexts = [];

        foreach ($rows as $row) {
            $key = (string) ($row->value_text ?? '');
            if ($key === '') {
                continue;
            }
            $counts[$key] = ($counts[$key] ?? 0) + 1;

            if ($key === DynamicFormLogicEvaluator::OTHER_CHOICE_KEY && is_array($row->value_json) && filled($row->value_json['other_text'] ?? null)) {
                if (count($otherTexts) < 5) {
                    $otherTexts[] = (string) $row->value_json['other_text'];
                }
            }
        }

        $distribution = [];
        $knownKeys = [];
        foreach ((array) ($options['choices'] ?? []) as $choice) {
            $key = (string) ($choice['key'] ?? '');
            $label = (string) ($choice['label'] ?? $key);
            $knownKeys[$key] = true;
            $count = $counts[$key] ?? 0;

            $distribution[] = [
                'key' => $key,
                'label' => $label,
                'count' => $count,
                'percent' => $answeredCount > 0 ? round(($count / $answeredCount) * 100, 1) : 0.0,
            ];
        }

        if (! empty($options['allow_other']) || isset($counts[DynamicFormLogicEvaluator::OTHER_CHOICE_KEY])) {
            $otherCount = $counts[DynamicFormLogicEvaluator::OTHER_CHOICE_KEY] ?? 0;
            $knownKeys[DynamicFormLogicEvaluator::OTHER_CHOICE_KEY] = true;
            $distribution[] = [
                'key' => DynamicFormLogicEvaluator::OTHER_CHOICE_KEY,
                'label' => (string) ($options['other_label'] ?? 'Lainnya'),
                'count' => $otherCount,
                'percent' => $answeredCount > 0 ? round(($otherCount / $answeredCount) * 100, 1) : 0.0,
            ];
        }

        foreach ($counts as $key => $count) {
            if (! isset($knownKeys[$key])) {
                $distribution[] = [
                    'key' => $key,
                    'label' => "{$key} (Opsi versi lama)",
                    'count' => $count,
                    'percent' => $answeredCount > 0 ? round(($count / $answeredCount) * 100, 1) : 0.0,
                ];
            }
        }

        return [
            'chart_kind' => 'choice_distribution',
            'distribution' => $distribution,
            'other_samples' => $otherTexts,
        ];
    }

    /**
     * @param  Collection<int, DynamicFormSubmissionAnswer>  $rows
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function summarizeMultiChoice($rows, array $options, int $answeredCount): array
    {
        $counts = [];
        $otherTexts = [];

        foreach ($rows as $row) {
            $json = is_array($row->value_json) ? $row->value_json : [];
            $selected = (array) ($json['selected'] ?? []);
            foreach ($selected as $key) {
                $keyStr = (string) $key;
                $counts[$keyStr] = ($counts[$keyStr] ?? 0) + 1;
            }
            if (in_array(DynamicFormLogicEvaluator::OTHER_CHOICE_KEY, $selected, true) && filled($json['other_text'] ?? null)) {
                if (count($otherTexts) < 5) {
                    $otherTexts[] = (string) $json['other_text'];
                }
            }
        }

        $distribution = [];
        $knownKeys = [];
        foreach ((array) ($options['choices'] ?? []) as $choice) {
            $key = (string) ($choice['key'] ?? '');
            $label = (string) ($choice['label'] ?? $key);
            $knownKeys[$key] = true;
            $count = $counts[$key] ?? 0;

            $distribution[] = [
                'key' => $key,
                'label' => $label,
                'count' => $count,
                'percent' => $answeredCount > 0 ? round(($count / $answeredCount) * 100, 1) : 0.0,
            ];
        }

        if (! empty($options['allow_other']) || isset($counts[DynamicFormLogicEvaluator::OTHER_CHOICE_KEY])) {
            $otherCount = $counts[DynamicFormLogicEvaluator::OTHER_CHOICE_KEY] ?? 0;
            $knownKeys[DynamicFormLogicEvaluator::OTHER_CHOICE_KEY] = true;
            $distribution[] = [
                'key' => DynamicFormLogicEvaluator::OTHER_CHOICE_KEY,
                'label' => (string) ($options['other_label'] ?? 'Lainnya'),
                'count' => $otherCount,
                'percent' => $answeredCount > 0 ? round(($otherCount / $answeredCount) * 100, 1) : 0.0,
            ];
        }

        foreach ($counts as $key => $count) {
            if (! isset($knownKeys[$key])) {
                $distribution[] = [
                    'key' => $key,
                    'label' => "{$key} (Opsi versi lama)",
                    'count' => $count,
                    'percent' => $answeredCount > 0 ? round(($count / $answeredCount) * 100, 1) : 0.0,
                ];
            }
        }

        return [
            'chart_kind' => 'choice_distribution',
            'distribution' => $distribution,
            'other_samples' => $otherTexts,
        ];
    }

    /**
     * @param  Collection<int, DynamicFormSubmissionAnswer>  $rows
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function summarizeNumericScale(DynamicFormFieldType $type, $rows, array $options, int $answeredCount): array
    {
        $min = $type === DynamicFormFieldType::Rating ? 1 : (int) ($options['min_value'] ?? 1);
        $max = $type === DynamicFormFieldType::Rating
            ? (int) ($options['max_stars'] ?? 5)
            : (int) ($options['max_value'] ?? 5);

        $values = [];
        $bucketCounts = [];
        for ($i = $min; $i <= $max; $i++) {
            $bucketCounts[$i] = 0;
        }

        foreach ($rows as $row) {
            if ($row->value_number === null) {
                continue;
            }
            $val = (int) round((float) $row->value_number);
            $values[] = (float) $row->value_number;
            $bucketCounts[$val] = ($bucketCounts[$val] ?? 0) + 1;
        }

        sort($values);
        $count = count($values);
        $average = $count > 0 ? round(array_sum($values) / $count, 2) : null;
        $median = null;
        if ($count > 0) {
            $mid = intdiv($count, 2);
            $median = $count % 2 === 0
                ? round(($values[$mid - 1] + $values[$mid]) / 2, 2)
                : round($values[$mid], 2);
        }

        ksort($bucketCounts);
        $distribution = [];
        foreach ($bucketCounts as $score => $scoreCount) {
            $distribution[] = [
                'key' => (string) $score,
                'label' => (string) $score,
                'count' => $scoreCount,
                'percent' => $answeredCount > 0 ? round(($scoreCount / $answeredCount) * 100, 1) : 0.0,
            ];
        }

        return [
            'chart_kind' => 'numeric_scale',
            'average' => $average,
            'median' => $median,
            'min_value' => $min,
            'max_value' => $max,
            'min_label' => $options['min_label'] ?? null,
            'max_label' => $options['max_label'] ?? null,
            'distribution' => $distribution,
        ];
    }

    /**
     * @param  Collection<int, DynamicFormSubmissionAnswer>  $rows
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function summarizeMatrix($rows, array $options): array
    {
        $matrixRows = (array) ($options['rows'] ?? []);
        $matrixCols = (array) ($options['columns'] ?? []);

        $colScoreMap = [];
        foreach ($matrixCols as $col) {
            $colKey = (string) ($col['key'] ?? '');
            if ($colKey !== '' && isset($col['score']) && is_numeric($col['score'])) {
                $colScoreMap[$colKey] = (float) $col['score'];
            }
        }

        $groupedByRow = $rows->groupBy('row_key');
        $rowSummaries = [];
        $knownRowKeys = [];

        foreach ($matrixRows as $matrixRow) {
            $rowKey = (string) ($matrixRow['key'] ?? '');
            $knownRowKeys[$rowKey] = true;
            $rowLabel = (string) ($matrixRow['label'] ?? $rowKey);
            $rowEntries = $groupedByRow->get($rowKey, collect());
            $rowSummaries[] = $this->summarizeMatrixRow($rowKey, $rowLabel, $rowEntries, $matrixCols, $colScoreMap);
        }

        foreach ($groupedByRow as $legacyRowKey => $rowEntries) {
            $legacyKey = (string) $legacyRowKey;
            if ($legacyKey === '' || isset($knownRowKeys[$legacyKey])) {
                continue;
            }
            $rowSummaries[] = $this->summarizeMatrixRow($legacyKey, "{$legacyKey} (Baris versi lama)", $rowEntries, $matrixCols, $colScoreMap);
        }

        return [
            'chart_kind' => 'matrix',
            'columns' => array_map(fn ($col): array => [
                'key' => (string) ($col['key'] ?? ''),
                'label' => (string) ($col['label'] ?? ''),
                'score' => $col['score'] ?? null,
            ], $matrixCols),
            'rows' => $rowSummaries,
        ];
    }

    /**
     * @param  Collection<int, DynamicFormSubmissionAnswer>  $rowEntries
     * @param  array<int, array<string, mixed>>  $matrixCols
     * @param  array<string, float>  $colScoreMap
     * @return array<string, mixed>
     */
    private function summarizeMatrixRow(string $rowKey, string $rowLabel, $rowEntries, array $matrixCols, array $colScoreMap): array
    {
        $rowRespondentCount = $rowEntries->pluck('submission_id')->unique()->count();
        $colCounts = [];
        $scores = [];

        foreach ($rowEntries as $entry) {
            $decoded = $entry->value_json;
            $selectedCols = is_array($decoded) ? $decoded : (filled($entry->value_text) ? [(string) $entry->value_text] : []);

            foreach ($selectedCols as $colKey) {
                $colKeyStr = (string) $colKey;
                $colCounts[$colKeyStr] = ($colCounts[$colKeyStr] ?? 0) + 1;
                if (isset($colScoreMap[$colKeyStr])) {
                    $scores[] = $colScoreMap[$colKeyStr];
                }
            }
        }

        $cells = [];
        foreach ($matrixCols as $col) {
            $colKey = (string) ($col['key'] ?? '');
            $count = $colCounts[$colKey] ?? 0;
            $cells[] = [
                'col_key' => $colKey,
                'count' => $count,
                'percent' => $rowRespondentCount > 0 ? round(($count / $rowRespondentCount) * 100, 1) : 0.0,
            ];
        }

        return [
            'row_key' => $rowKey,
            'row_label' => $rowLabel,
            'answered_count' => $rowRespondentCount,
            'avg_score' => $scores !== [] ? round(array_sum($scores) / count($scores), 2) : null,
            'cells' => $cells,
        ];
    }

    /**
     * @param  Collection<int, DynamicFormSubmissionAnswer>  $rows
     * @return array<string, mixed>
     */
    private function summarizeFiles($rows): array
    {
        $items = [];
        foreach ($rows->take(15) as $row) {
            $files = is_array($row->value_json) ? $row->value_json : [];
            foreach ($files as $file) {
                if (! is_array($file)) {
                    continue;
                }
                $items[] = [
                    'submission_id' => $row->submission_id,
                    'ticket_number' => $row->submission?->ticket_number,
                    'submitted_at' => $row->submission?->submitted_at,
                    'upload_id' => $file['upload_id'] ?? null,
                    'original_name' => (string) ($file['original_name'] ?? 'berkas'),
                    'mime_type' => (string) ($file['mime_type'] ?? ''),
                    'size_bytes' => (int) ($file['size_bytes'] ?? 0),
                ];
            }
        }

        return [
            'chart_kind' => 'files',
            'recent_files' => array_slice($items, 0, 15),
        ];
    }

    /**
     * @param  Collection<int, DynamicFormSubmissionAnswer>  $rows
     * @return array<string, mixed>
     */
    private function summarizeTextResponses($rows): array
    {
        $recent = $rows->take(15)->map(fn (DynamicFormSubmissionAnswer $row): array => [
            'submission_id' => $row->submission_id,
            'ticket_number' => $row->submission?->ticket_number,
            'submitted_at' => $row->submission?->submitted_at,
            'value' => (string) ($row->value_text ?? ''),
        ])->values()->all();

        return [
            'chart_kind' => 'text_list',
            'recent_responses' => $recent,
        ];
    }
}
