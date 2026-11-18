<?php

namespace App\Services\DynamicForm;

use App\Enums\DynamicFormFieldType;

class DynamicFormLogicEvaluator
{
    public const SUBMIT_SENTINEL = '__submit__';

    public const OTHER_CHOICE_KEY = '__other__';

    /**
     * @var list<string>
     */
    public const OPERATORS = [
        'equals',
        'not_equals',
        'in',
        'not_in',
        'contains',
        'gt',
        'gte',
        'lt',
        'lte',
        'is_empty',
        'is_not_empty',
    ];

    /**
     * @return array<string, string>
     */
    public static function operatorOptions(): array
    {
        return [
            'equals' => 'Sama dengan (=)',
            'not_equals' => 'Tidak sama dengan (!=)',
            'in' => 'Salah satu dari (pisahkan koma)',
            'not_in' => 'Bukan salah satu dari (pisahkan koma)',
            'contains' => 'Mengandung teks / pilihan',
            'gt' => 'Lebih besar dari (>)',
            'gte' => 'Lebih besar atau sama dengan (>=)',
            'lt' => 'Lebih kecil dari (<)',
            'lte' => 'Lebih kecil atau sama dengan (<=)',
            'is_empty' => 'Kosong / tidak diisi',
            'is_not_empty' => 'Sudah diisi (tidak kosong)',
        ];
    }

    /**
     * Evaluasi jalur section aktif, visibilitas field, dan sanitasi jawaban liar.
     *
     * @param  array<string, mixed>  $schemaSnapshot
     * @param  array<string, mixed>  $rawAnswers
     * @return array{
     *     active_section_keys: list<string>,
     *     visible_fields: list<array<string, mixed>>,
     *     visible_field_keys: list<string>,
     *     sanitized_answers: array<string, mixed>,
     *     errors: list<string>
     * }
     */
    public function evaluate(array $schemaSnapshot, array $rawAnswers): array
    {
        $sections = array_values((array) ($schemaSnapshot['sections'] ?? []));
        usort($sections, fn (array $a, array $b): int => ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0)));

        if ($sections === []) {
            return [
                'active_section_keys' => [],
                'visible_fields' => [],
                'visible_field_keys' => [],
                'sanitized_answers' => [],
                'errors' => [],
            ];
        }

        $sectionsByKey = [];
        $indexByKey = [];
        foreach ($sections as $index => $section) {
            $key = (string) ($section['section_key'] ?? '');
            if ($key === '') {
                continue;
            }
            $sectionsByKey[$key] = $section;
            $indexByKey[$key] = $index;
        }

        $activeSectionKeys = [];
        $visibleFields = [];
        $visibleFieldKeys = [];
        $sanitizedAnswers = [];
        $effectiveAnswers = [];
        $visited = [];
        $errors = [];

        $currentKey = (string) ($sections[0]['section_key'] ?? '');
        $maxSteps = count($sections);

        for ($step = 0; $step < $maxSteps && $currentKey !== ''; $step++) {
            if (isset($visited[$currentKey])) {
                $errors[] = "Terdeteksi loop logika pada section [{$currentKey}].";
                break;
            }

            if (! isset($sectionsByKey[$currentKey])) {
                $errors[] = "Section tujuan [{$currentKey}] tidak ditemukan.";
                break;
            }

            $visited[$currentKey] = true;
            $activeSectionKeys[] = $currentKey;
            $section = $sectionsByKey[$currentKey];

            $fields = array_values((array) ($section['fields'] ?? []));
            usort($fields, fn (array $a, array $b): int => ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0)));

            $jumpTarget = null;

            foreach ($fields as $field) {
                $fieldKey = (string) ($field['field_key'] ?? '');
                if ($fieldKey === '') {
                    continue;
                }

                if (! $this->evaluateVisibility($field, $effectiveAnswers)) {
                    continue;
                }

                $visibleFields[] = $field;
                $visibleFieldKeys[] = $fieldKey;

                $type = (string) ($field['type'] ?? '');
                if ($type === DynamicFormFieldType::InfoBlock->value) {
                    continue;
                }

                if (array_key_exists($fieldKey, $rawAnswers)) {
                    $answerValue = $rawAnswers[$fieldKey];
                    $sanitizedAnswers[$fieldKey] = $answerValue;
                    $effectiveAnswers[$fieldKey] = $answerValue;

                    $choiceJump = $this->resolveChoiceJumpTarget($field, $answerValue);
                    if ($choiceJump !== null) {
                        $jumpTarget = $choiceJump;
                    }
                }
            }

            $nextKey = $jumpTarget ?? $this->resolveSectionNextTarget($section, $sections, $indexByKey[$currentKey]);

            if ($nextKey === null || $nextKey === self::SUBMIT_SENTINEL) {
                break;
            }

            if (isset($visited[$nextKey])) {
                $errors[] = "Terdeteksi loop lompatan ke section [{$nextKey}].";
                break;
            }

            $currentKey = $nextKey;
        }

        return [
            'active_section_keys' => $activeSectionKeys,
            'visible_fields' => $visibleFields,
            'visible_field_keys' => $visibleFieldKeys,
            'sanitized_answers' => $sanitizedAnswers,
            'errors' => $errors,
        ];
    }

    /**
     * Evaluasi visibilitas satu field berdasarkan aturan conditional_logic.visibility.
     *
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $effectiveAnswers
     */
    public function evaluateVisibility(array $field, array $effectiveAnswers): bool
    {
        $visibility = (array) ($field['conditional_logic']['visibility'] ?? []);
        if (empty($visibility['enabled'])) {
            return true;
        }

        $rules = array_values(array_filter(
            (array) ($visibility['rules'] ?? []),
            fn ($rule): bool => is_array($rule) && filled($rule['source_field_key'] ?? null)
        ));

        if ($rules === []) {
            return true;
        }

        $matchMode = ($visibility['match'] ?? 'all') === 'any' ? 'any' : 'all';

        foreach ($rules as $rule) {
            $sourceKey = (string) ($rule['source_field_key'] ?? '');
            $operator = (string) ($rule['operator'] ?? 'equals');
            $expected = $rule['value'] ?? null;
            $actual = $this->normalizeComparableValue($effectiveAnswers[$sourceKey] ?? null);

            $matched = $this->evaluateOperator($actual, $operator, $expected);

            if ($matchMode === 'any' && $matched) {
                return true;
            }

            if ($matchMode === 'all' && ! $matched) {
                return false;
            }
        }

        return $matchMode === 'all';
    }

    /**
     * Deteksi duplikasi eksplisit section_key, field_key, serta choice/row/column key per field sebelum normalisasi.
     *
     * @param  array<int|string, mixed>  $rawSections
     * @return list<string>
     */
    public function validateUniqueRawKeys(array $rawSections): array
    {
        $seenSections = [];
        $seenFields = [];
        $errors = [];

        foreach ($rawSections as $section) {
            if (! is_array($section)) {
                continue;
            }

            $sKey = strtolower(trim((string) ($section['section_key'] ?? '')));
            if ($sKey !== '') {
                if (isset($seenSections[$sKey])) {
                    $errors[] = "Kunci bagian [{$sKey}] terduplikasi. Setiap bagian harus memiliki section_key unik.";
                }
                $seenSections[$sKey] = true;
            }

            foreach ((array) ($section['fields'] ?? []) as $field) {
                if (! is_array($field)) {
                    continue;
                }

                $fKey = strtolower(trim((string) ($field['field_key'] ?? '')));
                if ($fKey !== '') {
                    if (isset($seenFields[$fKey])) {
                        $errors[] = "Kunci pertanyaan [{$fKey}] terduplikasi. Setiap pertanyaan harus memiliki field_key unik.";
                    }
                    $seenFields[$fKey] = true;
                }

                $fieldLabel = trim((string) ($field['label'] ?? ''));
                if ($fieldLabel === '') {
                    $fieldLabel = $fKey !== '' ? $fKey : 'tanpa kunci';
                }

                $rawType = $field['type'] ?? null;
                $fieldType = $rawType instanceof DynamicFormFieldType
                    ? $rawType
                    : DynamicFormFieldType::tryFrom((string) ($rawType ?? ''));
                $options = (array) ($field['options'] ?? []);

                if ($fieldType === null || $fieldType->supportsChoices()) {
                    $seenChoices = [];
                    foreach ((array) ($options['choices'] ?? []) as $choice) {
                        if (! is_array($choice)) {
                            continue;
                        }
                        $cKey = strtolower(trim((string) ($choice['key'] ?? '')));
                        if ($cKey !== '') {
                            if (isset($seenChoices[$cKey])) {
                                $errors[] = "Kunci pilihan [{$cKey}] pada pertanyaan [{$fieldLabel}] terduplikasi. Setiap opsi pilihan dalam satu pertanyaan harus unik.";
                            }
                            $seenChoices[$cKey] = true;
                        }
                    }
                }

                if ($fieldType === null || $fieldType === DynamicFormFieldType::MatrixGrid) {
                    $seenRows = [];
                    foreach ((array) ($options['rows'] ?? []) as $row) {
                        if (! is_array($row)) {
                            continue;
                        }
                        $rKey = strtolower(trim((string) ($row['key'] ?? '')));
                        if ($rKey !== '') {
                            if (isset($seenRows[$rKey])) {
                                $errors[] = "Kunci baris matrix [{$rKey}] pada pertanyaan [{$fieldLabel}] terduplikasi. Setiap baris dalam satu pertanyaan harus unik.";
                            }
                            $seenRows[$rKey] = true;
                        }
                    }

                    $seenColumns = [];
                    foreach ((array) ($options['columns'] ?? []) as $column) {
                        if (! is_array($column)) {
                            continue;
                        }
                        $colKey = strtolower(trim((string) ($column['key'] ?? '')));
                        if ($colKey !== '') {
                            if (isset($seenColumns[$colKey])) {
                                $errors[] = "Kunci kolom matrix [{$colKey}] pada pertanyaan [{$fieldLabel}] terduplikasi. Setiap kolom dalam satu pertanyaan harus unik.";
                            }
                            $seenColumns[$colKey] = true;
                        }
                    }
                }
            }
        }

        return array_values(array_unique($errors));
    }

    /**
     * Validasi integritas graf percabangan & urutan dependency pada builder Step 3.
     *
     * @param  list<array<string, mixed>>  $sections
     * @return list<string>
     */
    public function validateSchemaLogic(array $sections): array
    {
        $errors = [];
        $sections = array_values($sections);

        $sectionKeys = [];
        $sectionIndexMap = [];
        $fieldPositions = [];
        $fieldTypes = [];
        $globalFieldOrder = 0;

        foreach ($sections as $sectionIdx => $section) {
            $sectionKey = (string) ($section['section_key'] ?? '');
            if ($sectionKey === '') {
                $errors[] = 'Setiap section harus memiliki section_key yang valid.';

                continue;
            }

            if (isset($sectionIndexMap[$sectionKey])) {
                $errors[] = "Terdapat duplikasi kunci section [{$sectionKey}].";

                continue;
            }

            $sectionKeys[] = $sectionKey;
            $sectionIndexMap[$sectionKey] = $sectionIdx;

            $fields = array_values((array) ($section['fields'] ?? []));
            foreach ($fields as $field) {
                $fieldKey = (string) ($field['field_key'] ?? '');
                if ($fieldKey === '') {
                    $errors[] = "Pertanyaan di section [{$sectionKey}] belum memiliki field_key.";

                    continue;
                }

                if (isset($fieldPositions[$fieldKey])) {
                    $errors[] = "Terdapat duplikasi kunci pertanyaan [{$fieldKey}].";

                    continue;
                }

                $fieldPositions[$fieldKey] = $globalFieldOrder++;
                $fieldTypes[$fieldKey] = (string) ($field['type'] ?? '');
            }
        }

        $graph = [];
        foreach ($sectionKeys as $key) {
            $graph[$key] = [];
        }

        foreach ($sections as $sectionIdx => $section) {
            $sectionKey = (string) ($section['section_key'] ?? '');
            if ($sectionKey === '' || ! isset($sectionIndexMap[$sectionKey])) {
                continue;
            }

            $nextRule = (array) ($section['next_section_rule'] ?? []);
            $nextAction = (string) ($nextRule['action'] ?? 'next');
            $nextTarget = isset($nextRule['target_section_key']) ? (string) $nextRule['target_section_key'] : null;

            if ($nextAction === 'jump') {
                if ($nextTarget === null || $nextTarget === '') {
                    $errors[] = "Section [{$sectionKey}] memakai aksi lompat tetapi target section belum dipilih.";
                } elseif ($nextTarget !== self::SUBMIT_SENTINEL) {
                    if (! isset($sectionIndexMap[$nextTarget])) {
                        $errors[] = "Target section [{$nextTarget}] pada section [{$sectionKey}] tidak ditemukan.";
                    } else {
                        if ($sectionIndexMap[$nextTarget] <= $sectionIdx) {
                            $errors[] = "Lompatan pada section [{$sectionKey}] ke [{$nextTarget}] tidak boleh mundur atau mengarah ke dirinya sendiri.";
                        }
                        $graph[$sectionKey][] = $nextTarget;
                    }
                }
            } elseif ($nextAction !== 'submit' && isset($sections[$sectionIdx + 1]['section_key'])) {
                $graph[$sectionKey][] = (string) $sections[$sectionIdx + 1]['section_key'];
            }

            foreach ((array) ($section['fields'] ?? []) as $field) {
                $fieldKey = (string) ($field['field_key'] ?? '');
                $fieldLabel = (string) ($field['label'] ?? $fieldKey);

                $visibility = (array) ($field['conditional_logic']['visibility'] ?? []);
                if (! empty($visibility['enabled'])) {
                    foreach ((array) ($visibility['rules'] ?? []) as $rule) {
                        if (! is_array($rule)) {
                            continue;
                        }

                        $sourceKey = (string) ($rule['source_field_key'] ?? '');
                        $operator = (string) ($rule['operator'] ?? 'equals');

                        if ($sourceKey === '') {
                            $errors[] = "Aturan visibilitas pada [{$fieldLabel}] belum memilih pertanyaan sumber.";

                            continue;
                        }

                        if (! isset($fieldPositions[$sourceKey])) {
                            $errors[] = "Pertanyaan sumber [{$sourceKey}] pada aturan [{$fieldLabel}] tidak ditemukan.";

                            continue;
                        }

                        if (($fieldTypes[$sourceKey] ?? '') === DynamicFormFieldType::InfoBlock->value) {
                            $errors[] = "Pertanyaan sumber [{$sourceKey}] pada aturan [{$fieldLabel}] bertipe Blok Informasi dan tidak memiliki jawaban.";
                        }

                        if (isset($fieldPositions[$fieldKey]) && $fieldPositions[$sourceKey] >= $fieldPositions[$fieldKey]) {
                            $errors[] = "Aturan visibilitas pada [{$fieldLabel}] hanya boleh merujuk pertanyaan yang muncul sebelumnya (bukan dirinya sendiri atau pertanyaan setelahnya).";
                        }

                        if (! in_array($operator, self::OPERATORS, true)) {
                            $errors[] = "Operator logika [{$operator}] pada [{$fieldLabel}] tidak didukung.";
                        }
                    }
                }

                $type = (string) ($field['type'] ?? '');
                if (in_array($type, [DynamicFormFieldType::Radio->value, DynamicFormFieldType::Dropdown->value], true)) {
                    foreach ((array) ($field['options']['choices'] ?? []) as $choice) {
                        $target = (string) ($choice['go_to_section_key'] ?? '');
                        if ($target === '' || $target === self::SUBMIT_SENTINEL) {
                            continue;
                        }

                        if (! isset($sectionIndexMap[$target])) {
                            $errors[] = "Target lompatan [{$target}] pada pilihan pertanyaan [{$fieldLabel}] tidak ditemukan.";

                            continue;
                        }

                        if ($sectionIndexMap[$target] <= $sectionIdx) {
                            $errors[] = "Lompatan pada pilihan pertanyaan [{$fieldLabel}] ke [{$target}] tidak boleh mundur atau mengarah ke section yang sama.";
                        }

                        $graph[$sectionKey][] = $target;
                    }
                }
            }
        }

        // DFS cycle detection untuk mencegah loop tak terbatas.
        $state = []; // 0 = unvisited, 1 = visiting, 2 = visited
        $dfs = function (string $node) use (&$dfs, &$state, $graph, &$errors): void {
            $state[$node] = 1;
            foreach (array_unique($graph[$node] ?? []) as $neighbor) {
                $neighborState = $state[$neighbor] ?? 0;
                if ($neighborState === 1) {
                    $errors[] = "Terdeteksi siklus (loop) percabangan antar-section melibatkan [{$node}] dan [{$neighbor}].";

                    continue;
                }
                if ($neighborState === 0) {
                    $dfs($neighbor);
                }
            }
            $state[$node] = 2;
        };

        foreach ($sectionKeys as $key) {
            if (($state[$key] ?? 0) === 0) {
                $dfs($key);
            }
        }

        return array_values(array_unique($errors));
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function resolveChoiceJumpTarget(array $field, mixed $answerValue): ?string
    {
        $type = (string) ($field['type'] ?? '');
        if (! in_array($type, [DynamicFormFieldType::Radio->value, DynamicFormFieldType::Dropdown->value], true)) {
            return null;
        }

        $selectedKey = null;
        if (is_array($answerValue)) {
            $selectedKey = isset($answerValue['selected']) ? (string) $answerValue['selected'] : null;
        } elseif (is_scalar($answerValue)) {
            $selectedKey = (string) $answerValue;
        }

        if ($selectedKey === null || $selectedKey === '') {
            return null;
        }

        foreach ((array) ($field['options']['choices'] ?? []) as $choice) {
            if ((string) ($choice['key'] ?? '') === $selectedKey) {
                $target = trim((string) ($choice['go_to_section_key'] ?? ''));

                return $target !== '' ? $target : null;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $section
     * @param  list<array<string, mixed>>  $sections
     */
    private function resolveSectionNextTarget(array $section, array $sections, int $currentIndex): ?string
    {
        $rule = (array) ($section['next_section_rule'] ?? []);
        $action = (string) ($rule['action'] ?? 'next');
        $target = trim((string) ($rule['target_section_key'] ?? ''));

        if ($action === 'submit' || $target === self::SUBMIT_SENTINEL) {
            return self::SUBMIT_SENTINEL;
        }

        if ($action === 'jump' && $target !== '') {
            return $target;
        }

        return isset($sections[$currentIndex + 1]['section_key'])
            ? (string) $sections[$currentIndex + 1]['section_key']
            : null;
    }

    private function normalizeComparableValue(mixed $value): mixed
    {
        if (is_array($value)) {
            if (array_key_exists('selected', $value)) {
                return $value['selected'];
            }

            return $value;
        }

        return $value;
    }

    private function evaluateOperator(mixed $actual, string $operator, mixed $expected): bool
    {
        $isEmpty = $this->isValueEmpty($actual);

        return match ($operator) {
            'is_empty' => $isEmpty,
            'is_not_empty' => ! $isEmpty,
            'equals' => ! $isEmpty && $this->matchesEquals($actual, $expected),
            'not_equals' => ! $isEmpty && ! $this->matchesEquals($actual, $expected),
            'in' => ! $isEmpty && $this->matchesIn($actual, $expected),
            'not_in' => ! $isEmpty && ! $this->matchesIn($actual, $expected),
            'contains' => ! $isEmpty && $this->matchesContains($actual, $expected),
            'gt' => is_numeric($actual) && is_numeric($expected) && ((float) $actual > (float) $expected),
            'gte' => is_numeric($actual) && is_numeric($expected) && ((float) $actual >= (float) $expected),
            'lt' => is_numeric($actual) && is_numeric($expected) && ((float) $actual < (float) $expected),
            'lte' => is_numeric($actual) && is_numeric($expected) && ((float) $actual <= (float) $expected),
            default => false,
        };
    }

    private function isValueEmpty(mixed $value): bool
    {
        if ($value === null || $value === '' || $value === []) {
            return true;
        }

        if (is_array($value)) {
            $nonEmpty = array_filter($value, fn ($item): bool => $item !== null && $item !== '' && $item !== []);

            return $nonEmpty === [];
        }

        return false;
    }

    private function matchesEquals(mixed $actual, mixed $expected): bool
    {
        if (is_array($actual)) {
            return count($actual) === 1 && (string) array_values($actual)[0] === (string) $expected;
        }

        if (is_numeric($actual) && is_numeric($expected)) {
            return (float) $actual === (float) $expected;
        }

        return (string) $actual === (string) $expected;
    }

    private function matchesIn(mixed $actual, mixed $expected): bool
    {
        $list = is_array($expected)
            ? array_values(array_filter(array_map(fn ($v) => trim((string) $v), $expected), fn ($v) => $v !== ''))
            : array_values(array_filter(array_map('trim', explode(',', (string) $expected)), fn ($v) => $v !== ''));

        if ($list === []) {
            return false;
        }

        if (is_array($actual)) {
            $actualStrings = array_map('strval', $actual);

            return count(array_intersect($actualStrings, $list)) > 0;
        }

        return in_array((string) $actual, $list, true);
    }

    private function matchesContains(mixed $actual, mixed $expected): bool
    {
        $needle = (string) ($expected ?? '');
        if ($needle === '') {
            return false;
        }

        if (is_array($actual)) {
            return in_array($needle, array_map('strval', $actual), true);
        }

        return str_contains(mb_strtolower((string) $actual), mb_strtolower($needle));
    }
}
