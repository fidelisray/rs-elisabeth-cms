<?php

namespace App\Services\DynamicForm;

use App\Enums\DynamicFormFieldType;
use App\Enums\DynamicFormStatus;
use App\Models\AuditLog;
use App\Models\DynamicForm;
use App\Models\DynamicFormField;
use App\Models\DynamicFormSection;
use App\Models\DynamicFormVersion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DynamicFormVersionService
{
    public function __construct(
        private readonly DynamicFormLogicEvaluator $logicEvaluator,
    ) {}

    /**
     * Muat struktur section & field untuk diisi ke Wizard Filament dari draft terbaru atau versi terpublikasi.
     *
     * @return list<array<string, mixed>>
     */
    public function loadEditableSectionsData(DynamicForm $form): array
    {
        $version = $form->latestDraftVersion() ?? $form->publishedVersion()->first() ?? $form->latestVersion();

        if (! $version) {
            return [];
        }

        if (is_array($version->schema_snapshot) && ! empty($version->schema_snapshot['sections']) && $version->sections()->count() === 0) {
            return array_values((array) $version->schema_snapshot['sections']);
        }

        return $version->sections()
            ->with(['fields' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
            ->get()
            ->map(function (DynamicFormSection $section): array {
                $nextRule = (array) ($section->next_section_rule ?? []);

                return [
                    'section_key' => $section->section_key,
                    'title' => $section->title,
                    'description' => $section->description,
                    'sort_order' => $section->sort_order,
                    'next_section_rule' => [
                        'action' => (string) ($nextRule['action'] ?? 'next'),
                        'target_section_key' => $nextRule['target_section_key'] ?? null,
                    ],
                    'fields' => $section->fields->map(function (DynamicFormField $field): array {
                        return [
                            'field_key' => $field->field_key,
                            'type' => $field->type->value,
                            'label' => $field->label,
                            'help_text' => $field->help_text,
                            'placeholder' => $field->placeholder,
                            'is_required' => $field->is_required,
                            'sort_order' => $field->sort_order,
                            'options' => $field->options ?? [],
                            'validation_rules' => $field->validation_rules ?? [],
                            'conditional_logic' => $field->conditional_logic ?? [],
                        ];
                    })->values()->all(),
                ];
            })->values()->all();
    }

    /**
     * Normalisasi struktur section & field dari Wizard agar setiap section_key, field_key,
     * dan choice/row/column key terisi bersih, unik, dan deterministik.
     *
     * @param  array<int, array<string, mixed>>  $rawSections
     * @return list<array<string, mixed>>
     */
    public function normalizeSectionsInput(array $rawSections): array
    {
        $usedSectionKeys = [];
        $usedFieldKeys = [];
        $normalized = [];

        foreach (array_values($rawSections) as $sectionIdx => $rawSection) {
            if (! is_array($rawSection)) {
                continue;
            }

            $title = trim((string) ($rawSection['title'] ?? ''));
            if ($title === '') {
                $title = 'Bagian '.($sectionIdx + 1);
            }

            $sectionKey = $this->uniqueKey(
                (string) ($rawSection['section_key'] ?? ''),
                $title,
                'sec_'.($sectionIdx + 1),
                $usedSectionKeys
            );

            $rawNextRule = (array) ($rawSection['next_section_rule'] ?? []);
            $nextAction = (string) ($rawNextRule['action'] ?? 'next');
            if (! in_array($nextAction, ['next', 'jump', 'submit'], true)) {
                $nextAction = 'next';
            }

            $fields = [];
            foreach (array_values((array) ($rawSection['fields'] ?? [])) as $fieldIdx => $rawField) {
                if (! is_array($rawField)) {
                    continue;
                }

                $typeValue = (string) ($rawField['type'] ?? DynamicFormFieldType::ShortText->value);
                $type = DynamicFormFieldType::tryFrom($typeValue) ?? DynamicFormFieldType::ShortText;
                $label = trim((string) ($rawField['label'] ?? ''));
                if ($label === '') {
                    $label = 'Pertanyaan '.($fieldIdx + 1);
                }

                $fieldKey = $this->uniqueKey(
                    (string) ($rawField['field_key'] ?? ''),
                    $label,
                    'fld_'.($fieldIdx + 1),
                    $usedFieldKeys
                );

                $options = $this->normalizeFieldOptions($type, (array) ($rawField['options'] ?? []));
                $validationRules = $this->normalizeValidationRules($type, (array) ($rawField['validation_rules'] ?? []));
                $conditionalLogic = $this->normalizeConditionalLogic((array) ($rawField['conditional_logic'] ?? []));

                $fields[] = [
                    'field_key' => $fieldKey,
                    'type' => $type->value,
                    'label' => $label,
                    'help_text' => filled($rawField['help_text'] ?? null) ? trim((string) $rawField['help_text']) : null,
                    'placeholder' => filled($rawField['placeholder'] ?? null) ? trim((string) $rawField['placeholder']) : null,
                    'is_required' => $type->isInput() ? (bool) ($rawField['is_required'] ?? false) : false,
                    'sort_order' => $fieldIdx + 1,
                    'options' => $options,
                    'validation_rules' => $validationRules,
                    'conditional_logic' => $conditionalLogic,
                ];
            }

            $normalized[] = [
                'section_key' => $sectionKey,
                'title' => $title,
                'description' => filled($rawSection['description'] ?? null) ? trim((string) $rawSection['description']) : null,
                'sort_order' => $sectionIdx + 1,
                'next_section_rule' => [
                    'action' => $nextAction,
                    'target_section_key' => filled($rawNextRule['target_section_key'] ?? null)
                        ? trim((string) $rawNextRule['target_section_key'])
                        : null,
                ],
                'fields' => $fields,
            ];
        }

        return $normalized;
    }

    /**
     * Ekstrak aturan percabangan dari struktur sections menjadi state terpisah untuk Step 3 Wizard.
     *
     * @param  list<array<string, mixed>>  $sections
     * @return array{
     *     section_jumps: list<array<string, mixed>>,
     *     choice_jumps: list<array<string, mixed>>,
     *     visibility_rules: list<array<string, mixed>>
     * }
     */
    public function extractLogicConfigFromSections(array $sections): array
    {
        $sectionJumps = [];
        $choiceJumps = [];
        $visibilityRules = [];

        foreach ($sections as $section) {
            $sectionKey = (string) ($section['section_key'] ?? '');
            $nextRule = (array) ($section['next_section_rule'] ?? []);
            $action = (string) ($nextRule['action'] ?? 'next');
            $target = $nextRule['target_section_key'] ?? null;

            if ($sectionKey !== '' && ($action !== 'next' || filled($target))) {
                $sectionJumps[] = [
                    'section_key' => $sectionKey,
                    'action' => $action,
                    'target_section_key' => $target,
                ];
            }

            foreach ((array) ($section['fields'] ?? []) as $field) {
                $fieldKey = (string) ($field['field_key'] ?? '');
                if ($fieldKey === '') {
                    continue;
                }

                $type = (string) ($field['type'] ?? '');
                if (in_array($type, [DynamicFormFieldType::Radio->value, DynamicFormFieldType::Dropdown->value], true)) {
                    foreach ((array) ($field['options']['choices'] ?? []) as $choice) {
                        if (filled($choice['go_to_section_key'] ?? null)) {
                            $choiceJumps[] = [
                                'source_field_key' => $fieldKey,
                                'choice_key' => (string) ($choice['key'] ?? ''),
                                'go_to_section_key' => (string) $choice['go_to_section_key'],
                            ];
                        }
                    }
                }

                $vis = (array) ($field['conditional_logic']['visibility'] ?? []);
                if (! empty($vis['enabled']) && ! empty($vis['rules'])) {
                    $visibilityRules[] = [
                        'target_field_key' => $fieldKey,
                        'match' => ($vis['match'] ?? 'all') === 'any' ? 'any' : 'all',
                        'rules' => array_values((array) $vis['rules']),
                    ];
                }
            }
        }

        return [
            'section_jumps' => $sectionJumps,
            'choice_jumps' => $choiceJumps,
            'visibility_rules' => $visibilityRules,
        ];
    }

    /**
     * Gabungkan state percabangan dari Step 3 Wizard kembali ke struktur sections sebelum validasi/simpan.
     *
     * @param  array<int, array<string, mixed>>  $rawSections
     * @param  array<string, mixed>  $logicConfig
     * @return list<array<string, mixed>>
     */
    public function mergeLogicConfigIntoSections(array $rawSections, array $logicConfig): array
    {
        $sections = $this->normalizeSectionsInput($rawSections);

        $sectionJumpMap = [];
        foreach ((array) ($logicConfig['section_jumps'] ?? []) as $item) {
            if (! is_array($item) || blank($item['section_key'] ?? null)) {
                continue;
            }
            $sectionJumpMap[(string) $item['section_key']] = [
                'action' => (string) ($item['action'] ?? 'next'),
                'target_section_key' => filled($item['target_section_key'] ?? null)
                    ? trim((string) $item['target_section_key'])
                    : null,
            ];
        }

        $choiceJumpMap = [];
        foreach ((array) ($logicConfig['choice_jumps'] ?? []) as $item) {
            if (! is_array($item) || blank($item['source_field_key'] ?? null) || blank($item['choice_key'] ?? null)) {
                continue;
            }
            $choiceJumpMap[(string) $item['source_field_key']][(string) $item['choice_key']] = filled($item['go_to_section_key'] ?? null)
                ? trim((string) $item['go_to_section_key'])
                : null;
        }

        $visibilityMap = [];
        foreach ((array) ($logicConfig['visibility_rules'] ?? []) as $item) {
            if (! is_array($item) || blank($item['target_field_key'] ?? null)) {
                continue;
            }
            $rules = array_values(array_filter(
                (array) ($item['rules'] ?? []),
                fn ($r): bool => is_array($r) && filled($r['source_field_key'] ?? null)
            ));
            if ($rules === []) {
                continue;
            }
            $visibilityMap[(string) $item['target_field_key']] = [
                'visibility' => [
                    'enabled' => true,
                    'match' => ($item['match'] ?? 'all') === 'any' ? 'any' : 'all',
                    'rules' => array_map(fn (array $r): array => [
                        'source_field_key' => trim((string) $r['source_field_key']),
                        'operator' => (string) ($r['operator'] ?? 'equals'),
                        'value' => $r['value'] ?? null,
                    ], $rules),
                ],
            ];
        }

        foreach ($sections as &$section) {
            $sKey = (string) $section['section_key'];
            if (isset($sectionJumpMap[$sKey])) {
                $section['next_section_rule'] = $sectionJumpMap[$sKey];
            } else {
                $section['next_section_rule'] = ['action' => 'next', 'target_section_key' => null];
            }

            foreach ($section['fields'] as &$field) {
                $fKey = (string) $field['field_key'];
                if (isset($field['options']['choices']) && is_array($field['options']['choices'])) {
                    foreach ($field['options']['choices'] as &$choice) {
                        $cKey = (string) ($choice['key'] ?? '');
                        if (isset($choiceJumpMap[$fKey][$cKey])) {
                            $choice['go_to_section_key'] = $choiceJumpMap[$fKey][$cKey];
                        } elseif (array_key_exists('go_to_section_key', $choice)) {
                            $choice['go_to_section_key'] = null;
                        }
                    }
                    unset($choice);
                }

                if (isset($visibilityMap[$fKey])) {
                    $field['conditional_logic'] = $visibilityMap[$fKey];
                } else {
                    $field['conditional_logic'] = [
                        'visibility' => [
                            'enabled' => false,
                            'match' => 'all',
                            'rules' => [],
                        ],
                    ];
                }
            }
            unset($field);
        }
        unset($section);

        return $sections;
    }

    /**
     * Simpan struktur ke versi draft aktif (membuat draft vNext otomatis jika form sudah terpublikasi).
     *
     * @param  array<int, array<string, mixed>>  $rawSections
     */
    public function syncDraftStructure(DynamicForm $form, array $rawSections, bool $createNextDraftOnlyIfChanged = true): DynamicFormVersion
    {
        $normalized = $this->normalizeSectionsInput($rawSections);

        return DB::transaction(function () use ($form, $normalized, $createNextDraftOnlyIfChanged): DynamicFormVersion {
            $draft = $form->latestDraftVersion();

            if (! $draft && $form->published_version_id && $createNextDraftOnlyIfChanged) {
                $published = $form->publishedVersion()->first();
                if ($published && $this->sectionsEqual($this->extractSnapshotSections($published), $normalized)) {
                    return $published;
                }
            }

            if (! $draft) {
                $nextNumber = ((int) $form->versions()->max('version_number')) + 1;
                $draft = DynamicFormVersion::create([
                    'dynamic_form_id' => $form->id,
                    'version_number' => $nextNumber,
                    'status' => 'draft',
                ]);
            }

            // Hapus dan tulis ulang sections & fields pada draft version saja (versi terpublikasi tidak disentuh).
            $draft->fields()->delete();
            $draft->sections()->delete();

            foreach ($normalized as $sectionData) {
                $section = DynamicFormSection::create([
                    'dynamic_form_version_id' => $draft->id,
                    'section_key' => $sectionData['section_key'],
                    'title' => $sectionData['title'],
                    'description' => $sectionData['description'],
                    'sort_order' => $sectionData['sort_order'],
                    'next_section_rule' => $sectionData['next_section_rule'],
                ]);

                foreach ($sectionData['fields'] as $fieldData) {
                    DynamicFormField::create([
                        'dynamic_form_version_id' => $draft->id,
                        'dynamic_form_section_id' => $section->id,
                        'field_key' => $fieldData['field_key'],
                        'type' => $fieldData['type'],
                        'label' => $fieldData['label'],
                        'help_text' => $fieldData['help_text'],
                        'placeholder' => $fieldData['placeholder'],
                        'is_required' => $fieldData['is_required'],
                        'sort_order' => $fieldData['sort_order'],
                        'options' => $fieldData['options'],
                        'validation_rules' => $fieldData['validation_rules'],
                        'conditional_logic' => $fieldData['conditional_logic'],
                    ]);
                }
            }

            return $draft->fresh(['sections.fields']);
        });
    }

    /**
     * Bangun snapshot skema dari relasi sections & fields (dipakai saat publish dan Live Preview).
     *
     * @return array<string, mixed>
     */
    public function buildSchemaSnapshot(DynamicFormVersion $version): array
    {
        $version->loadMissing(['sections.fields']);

        $sections = $version->sections
            ->sortBy('sort_order')
            ->values()
            ->map(function (DynamicFormSection $section): array {
                return [
                    'section_key' => $section->section_key,
                    'title' => $section->title,
                    'description' => $section->description,
                    'sort_order' => $section->sort_order,
                    'next_section_rule' => $section->next_section_rule ?? ['action' => 'next', 'target_section_key' => null],
                    'fields' => $section->fields
                        ->sortBy('sort_order')
                        ->values()
                        ->map(fn (DynamicFormField $field): array => [
                            'field_key' => $field->field_key,
                            'type' => $field->type->value,
                            'label' => $field->label,
                            'help_text' => $field->help_text,
                            'placeholder' => $field->placeholder,
                            'is_required' => $field->is_required,
                            'sort_order' => $field->sort_order,
                            'options' => $field->options ?? [],
                            'validation_rules' => $field->validation_rules ?? [],
                            'conditional_logic' => $field->conditional_logic ?? [],
                        ])->all(),
                ];
            })->all();

        return [
            'version_id' => $version->id,
            'version_number' => $version->version_number,
            'sections' => $sections,
        ];
    }

    /**
     * Publikasikan draft terbaru secara atomik dan bekukan schema_snapshot.
     *
     * @throws ValidationException
     */
    public function publishDraftVersion(DynamicForm $form, ?string $actorEmail = null): DynamicFormVersion
    {
        return DB::transaction(function () use ($form, $actorEmail): DynamicFormVersion {
            $lockedForm = DynamicForm::whereKey($form->id)->lockForUpdate()->firstOrFail();
            $targetVersion = $lockedForm->latestDraftVersion() ?? $lockedForm->publishedVersion()->first();

            if (! $targetVersion) {
                throw ValidationException::withMessages([
                    'sections' => ['Formulir harus memiliki minimal 1 bagian dan 1 pertanyaan sebelum dipublikasikan.'],
                ]);
            }

            $snapshot = $this->buildSchemaSnapshot($targetVersion);
            $sections = (array) ($snapshot['sections'] ?? []);

            if ($sections === []) {
                throw ValidationException::withMessages([
                    'sections' => ['Formulir harus memiliki minimal 1 bagian sebelum dipublikasikan.'],
                ]);
            }

            $hasInputField = collect($sections)
                ->flatMap(fn (array $s) => (array) ($s['fields'] ?? []))
                ->contains(fn (array $f): bool => ($f['type'] ?? '') !== DynamicFormFieldType::InfoBlock->value);

            if (! $hasInputField) {
                throw ValidationException::withMessages([
                    'sections' => ['Formulir harus memiliki minimal 1 pertanyaan input (selain blok informasi).'],
                ]);
            }

            $logicErrors = $this->logicEvaluator->validateSchemaLogic($sections);
            if ($logicErrors !== []) {
                throw ValidationException::withMessages([
                    'sections' => $logicErrors,
                ]);
            }

            // Arsipkan versi terpublikasi sebelumnya bila menerbitkan versi baru.
            DynamicFormVersion::where('dynamic_form_id', $lockedForm->id)
                ->where('id', '!=', $targetVersion->id)
                ->where('status', 'published')
                ->update(['status' => 'archived']);

            $targetVersion->update([
                'status' => 'published',
                'schema_snapshot' => $snapshot,
                'published_at' => $targetVersion->published_at ?? now(),
                'published_by' => $actorEmail ?? Auth::user()?->email ?? 'system',
            ]);

            $lockedForm->update([
                'published_version_id' => $targetVersion->id,
                'status' => DynamicFormStatus::Published,
            ]);

            AuditLog::record('dynamic_form.published', $lockedForm, [
                'version_id' => $targetVersion->id,
                'version_number' => $targetVersion->version_number,
                'source' => 'panel',
            ]);

            return $targetVersion->fresh();
        });
    }

    public function closeForm(DynamicForm $form): void
    {
        DB::transaction(function () use ($form): void {
            $locked = DynamicForm::whereKey($form->id)->lockForUpdate()->firstOrFail();
            $locked->update(['status' => DynamicFormStatus::Closed]);

            AuditLog::record('dynamic_form.closed', $locked, ['source' => 'panel']);
        });
    }

    public function reopenForm(DynamicForm $form): void
    {
        if (! $form->published_version_id) {
            throw ValidationException::withMessages([
                'status' => ['Formulir belum pernah dipublikasikan. Publikasikan versi terlebih dahulu.'],
            ]);
        }

        DB::transaction(function () use ($form): void {
            $locked = DynamicForm::whereKey($form->id)->lockForUpdate()->firstOrFail();
            $locked->update(['status' => DynamicFormStatus::Published]);

            AuditLog::record('dynamic_form.reopened', $locked, ['source' => 'panel']);
        });
    }

    /**
     * Duplikat form beserta seluruh section, field, dan conditional logic ke form draft baru.
     */
    public function duplicateForm(
        DynamicForm $source,
        ?string $newTitle = null,
        ?string $newSlug = null,
        ?string $newLocale = null,
    ): DynamicForm {
        $sectionsData = $this->loadEditableSectionsData($source);

        return DB::transaction(function () use ($source, $sectionsData, $newTitle, $newSlug, $newLocale): DynamicForm {
            $title = filled($newTitle) ? trim((string) $newTitle) : $source->title.' (Salinan)';
            $baseSlug = filled($newSlug) ? Str::slug((string) $newSlug) : Str::slug($title);
            if ($baseSlug === '') {
                $baseSlug = 'form-'.Str::lower(Str::random(6));
            }

            $slug = $baseSlug;
            $suffix = 2;
            while (DynamicForm::withTrashed()->where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$suffix;
                $suffix++;
            }

            $locale = in_array($newLocale, ['id', 'en'], true) ? $newLocale : $source->locale;

            $copy = DynamicForm::create([
                'title' => $title,
                'slug' => $slug,
                'description' => $source->description,
                'mode' => $source->mode,
                'locale' => $locale,
                'status' => DynamicFormStatus::Draft,
                'published_version_id' => null,
                'starts_at' => $source->starts_at,
                'ends_at' => $source->ends_at,
                'max_responses' => $source->max_responses,
                'allow_multiple_submissions' => $source->allow_multiple_submissions,
                'ticket_prefix' => $source->ticket_prefix,
                'confirmation_message' => $source->confirmation_message,
                'closed_message' => $source->closed_message,
            ]);

            if ($sectionsData !== []) {
                $this->syncDraftStructure($copy, $sectionsData, false);
            } else {
                DynamicFormVersion::create([
                    'dynamic_form_id' => $copy->id,
                    'version_number' => 1,
                    'status' => 'draft',
                ]);
            }

            AuditLog::record('dynamic_form.duplicated', $copy, [
                'source_form_id' => $source->id,
                'source' => 'panel',
            ]);

            return $copy->fresh();
        });
    }

    /**
     * @param  array<string, bool>  $usedKeys
     */
    private function uniqueKey(string $candidate, string $fallbackLabel, string $prefix, array &$usedKeys): string
    {
        $trimmed = strtolower(trim($candidate));
        if ($trimmed !== '' && preg_match('/^[a-z0-9_]+$/', $trimmed) === 1 && trim($trimmed, '_') !== '') {
            $clean = $trimmed;
        } else {
            $clean = strtolower((string) preg_replace('/[^a-zA-Z0-9_]+/', '_', trim($candidate)));
            $clean = trim($clean, '_');
        }

        if ($clean === '') {
            $clean = strtolower(Str::slug($fallbackLabel, '_'));
            $clean = (string) preg_replace('/[^a-z0-9_]+/', '_', $clean);
            $clean = trim($clean, '_');
        }

        if ($clean === '') {
            $clean = $prefix;
        }

        $clean = substr($clean, 0, 80);
        $base = $clean;
        $counter = 2;

        while (isset($usedKeys[$clean])) {
            $suffix = '_'.$counter;
            $clean = substr($base, 0, 80 - strlen($suffix)).$suffix;
            $counter++;
        }

        $usedKeys[$clean] = true;

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalizeFieldOptions(DynamicFormFieldType $type, array $raw): array
    {
        return match ($type) {
            DynamicFormFieldType::ShortText => [
                'subtype' => in_array($raw['subtype'] ?? 'text', ['text', 'email', 'phone', 'number', 'url'], true)
                    ? ($raw['subtype'] ?? 'text')
                    : 'text',
            ],
            DynamicFormFieldType::Radio, DynamicFormFieldType::Checkbox, DynamicFormFieldType::Dropdown => [
                'choices' => $this->normalizeKeyedItems((array) ($raw['choices'] ?? []), 'opt', $type->supportsSectionJump()),
                'allow_other' => $type === DynamicFormFieldType::Dropdown ? false : (bool) ($raw['allow_other'] ?? false),
                'other_label' => filled($raw['other_label'] ?? null) ? trim((string) $raw['other_label']) : 'Lainnya',
            ],
            DynamicFormFieldType::LinearScale => [
                'min_value' => max(0, min(1, (int) ($raw['min_value'] ?? 1))),
                'max_value' => max(2, min(10, (int) ($raw['max_value'] ?? 5))),
                'min_label' => filled($raw['min_label'] ?? null) ? trim((string) $raw['min_label']) : null,
                'max_label' => filled($raw['max_label'] ?? null) ? trim((string) $raw['max_label']) : null,
            ],
            DynamicFormFieldType::Rating => [
                'max_stars' => max(3, min(10, (int) ($raw['max_stars'] ?? 5))),
                'icon' => in_array($raw['icon'] ?? 'star', ['star', 'heart', 'thumb'], true) ? ($raw['icon'] ?? 'star') : 'star',
            ],
            DynamicFormFieldType::MatrixGrid => [
                'selection_mode' => ($raw['selection_mode'] ?? 'single') === 'multiple' ? 'multiple' : 'single',
                'rows' => $this->normalizeKeyedItems((array) ($raw['rows'] ?? []), 'row'),
                'columns' => $this->normalizeKeyedItems((array) ($raw['columns'] ?? []), 'col', false, true),
                'require_all_rows' => (bool) ($raw['require_all_rows'] ?? false),
            ],
            DynamicFormFieldType::FileUpload => [
                'allowed_extensions' => $this->normalizeExtensions($raw['allowed_extensions'] ?? ['pdf', 'jpg', 'jpeg', 'png']),
                'max_file_size_mb' => max(1, min(10, (int) ($raw['max_file_size_mb'] ?? 5))),
                'max_files' => max(1, min(5, (int) ($raw['max_files'] ?? 1))),
            ],
            DynamicFormFieldType::InfoBlock => [
                'content' => filled($raw['content'] ?? null) ? trim((string) $raw['content']) : '',
                'media_url' => filled($raw['media_url'] ?? null) ? trim((string) $raw['media_url']) : null,
            ],
            default => [],
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function normalizeKeyedItems(array $items, string $prefix, bool $withSectionJump = false, bool $withScore = false): array
    {
        $used = [];
        $result = [];

        foreach (array_values($items) as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $label = trim((string) ($item['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $key = $this->uniqueKey((string) ($item['key'] ?? ''), $label, $prefix.'_'.($index + 1), $used);

            $row = [
                'key' => $key,
                'label' => $label,
            ];

            if ($withSectionJump) {
                $row['go_to_section_key'] = filled($item['go_to_section_key'] ?? null)
                    ? trim((string) $item['go_to_section_key'])
                    : null;
            }

            if ($withScore) {
                $row['score'] = isset($item['score']) && $item['score'] !== '' && is_numeric($item['score'])
                    ? (float) $item['score']
                    : null;
            }

            $result[] = $row;
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    private function normalizeExtensions(mixed $raw): array
    {
        $items = is_string($raw)
            ? array_map('trim', explode(',', $raw))
            : (is_array($raw) ? $raw : []);

        $allowedWhitelist = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'txt'];

        $clean = collect($items)
            ->map(fn ($ext): string => ltrim(strtolower(trim((string) $ext)), '.'))
            ->filter(fn (string $ext): bool => in_array($ext, $allowedWhitelist, true))
            ->unique()
            ->values()
            ->all();

        return $clean !== [] ? $clean : ['pdf', 'jpg', 'jpeg', 'png'];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalizeValidationRules(DynamicFormFieldType $type, array $raw): array
    {
        $rules = [];

        if (in_array($type, [DynamicFormFieldType::ShortText, DynamicFormFieldType::LongText], true)) {
            if (isset($raw['min_length']) && is_numeric($raw['min_length']) && (int) $raw['min_length'] >= 0) {
                $rules['min_length'] = (int) $raw['min_length'];
            }
            if (isset($raw['max_length']) && is_numeric($raw['max_length']) && (int) $raw['max_length'] > 0) {
                $rules['max_length'] = min(5000, (int) $raw['max_length']);
            }
        }

        if ($type === DynamicFormFieldType::ShortText) {
            if (isset($raw['min_value']) && $raw['min_value'] !== '' && is_numeric($raw['min_value'])) {
                $rules['min_value'] = (float) $raw['min_value'];
            }
            if (isset($raw['max_value']) && $raw['max_value'] !== '' && is_numeric($raw['max_value'])) {
                $rules['max_value'] = (float) $raw['max_value'];
            }
            if (filled($raw['pattern'] ?? null)) {
                $rules['pattern'] = trim((string) $raw['pattern']);
            }
        }

        if ($type === DynamicFormFieldType::Checkbox) {
            if (isset($raw['min_selected']) && is_numeric($raw['min_selected']) && (int) $raw['min_selected'] >= 0) {
                $rules['min_selected'] = (int) $raw['min_selected'];
            }
            if (isset($raw['max_selected']) && is_numeric($raw['max_selected']) && (int) $raw['max_selected'] > 0) {
                $rules['max_selected'] = (int) $raw['max_selected'];
            }
        }

        if ($type === DynamicFormFieldType::Date) {
            if (filled($raw['min_date'] ?? null)) {
                $rules['min_date'] = trim((string) $raw['min_date']);
            }
            if (filled($raw['max_date'] ?? null)) {
                $rules['max_date'] = trim((string) $raw['max_date']);
            }
        }

        if ($type === DynamicFormFieldType::Time) {
            if (filled($raw['min_time'] ?? null)) {
                $rules['min_time'] = trim((string) $raw['min_time']);
            }
            if (filled($raw['max_time'] ?? null)) {
                $rules['max_time'] = trim((string) $raw['max_time']);
            }
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalizeConditionalLogic(array $raw): array
    {
        $visibility = (array) ($raw['visibility'] ?? []);
        $enabled = (bool) ($visibility['enabled'] ?? false);
        $match = ($visibility['match'] ?? 'all') === 'any' ? 'any' : 'all';

        $rules = collect((array) ($visibility['rules'] ?? []))
            ->filter(fn ($rule): bool => is_array($rule) && filled($rule['source_field_key'] ?? null))
            ->map(fn (array $rule): array => [
                'source_field_key' => trim((string) $rule['source_field_key']),
                'operator' => in_array($rule['operator'] ?? 'equals', DynamicFormLogicEvaluator::OPERATORS, true)
                    ? (string) $rule['operator']
                    : 'equals',
                'value' => $rule['value'] ?? null,
            ])
            ->values()
            ->all();

        if ($rules === []) {
            $enabled = false;
        }

        return [
            'visibility' => [
                'enabled' => $enabled,
                'match' => $match,
                'rules' => $rules,
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractSnapshotSections(DynamicFormVersion $version): array
    {
        if (is_array($version->schema_snapshot) && isset($version->schema_snapshot['sections'])) {
            return array_values((array) $version->schema_snapshot['sections']);
        }

        return (array) ($this->buildSchemaSnapshot($version)['sections'] ?? []);
    }

    /**
     * @param  list<array<string, mixed>>  $a
     * @param  list<array<string, mixed>>  $b
     */
    private function sectionsEqual(array $a, array $b): bool
    {
        return json_encode($a) === json_encode($b);
    }
}
