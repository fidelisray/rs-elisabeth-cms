<?php

namespace App\Livewire;

use App\Enums\DynamicFormFieldType;
use App\Models\DynamicForm;
use App\Services\DynamicForm\DynamicFormHtmlSanitizer;
use App\Services\DynamicForm\DynamicFormLogicEvaluator;
use App\Services\DynamicForm\DynamicFormSubmissionValidator;
use App\Services\DynamicForm\DynamicFormVersionService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class DynamicFormLivePreview extends Component
{
    public int $formId;

    public string $formTitle = '';

    public ?string $formDescription = null;

    public string $formMode = 'questionnaire';

    public string $sourceLabel = 'Draft Terbaru';

    /**
     * @var array<string, mixed>
     */
    public array $schema = [];

    /**
     * @var array<string, mixed>
     */
    public array $answers = [];

    public int $currentSectionIndex = 0;

    /**
     * @var list<string>
     */
    public array $sectionHistory = [];

    /**
     * @var array<string, list<string>>
     */
    public array $validationErrors = [];

    public bool $isCompleted = false;

    public ?string $previewError = null;

    /**
     * @param  array<string, mixed>|null  $customSchema
     */
    public function mount(int $formId, ?array $customSchema = null): void
    {
        $this->formId = $formId;
        $form = DynamicForm::find($formId);

        if (! $form) {
            $this->previewError = 'Formulir tidak ditemukan untuk pratinjau.';

            return;
        }

        $this->formTitle = $form->title;
        $this->formDescription = app(DynamicFormHtmlSanitizer::class)->sanitize($form->description);
        $this->formMode = $form->mode->value;

        if ($customSchema !== null) {
            $this->schema = $customSchema;
            $this->sourceLabel = 'Skema Editor';
            if (array_key_exists('description', $customSchema)) {
                $this->formDescription = app(DynamicFormHtmlSanitizer::class)->sanitize($customSchema['description']);
            }
        } else {
            $version = $form->latestDraftVersion() ?? $form->publishedVersion()->first() ?? $form->latestVersion();
            if (! $version) {
                $this->schema = ['sections' => []];
                $this->sourceLabel = 'Belum Ada Versi';
            } else {
                $versionService = app(DynamicFormVersionService::class);
                $this->schema = is_array($version->schema_snapshot) && ! empty($version->schema_snapshot['sections'])
                    ? $version->schema_snapshot
                    : $versionService->buildSchemaSnapshot($version);
                $this->sourceLabel = $version->status === 'published'
                    ? "Versi Terpublikasi v{$version->version_number}"
                    : "Draf v{$version->version_number}";
            }
        }

        $sections = $this->orderedSections();
        if ($sections !== []) {
            $this->sectionHistory = [(string) $sections[0]['section_key']];
        }
    }

    public function updatedAnswers(): void
    {
        $this->validationErrors = [];
    }

    public function nextSection(): void
    {
        $this->validationErrors = [];
        $sections = $this->orderedSections();
        $current = $sections[$this->currentSectionIndex] ?? null;

        if (! $current) {
            return;
        }

        $visibleFields = $this->visibleFieldsForSection($current);
        $stepErrors = $this->validateVisibleStepFields($visibleFields);

        if ($stepErrors !== []) {
            $this->validationErrors = $stepErrors;

            return;
        }

        $nextKey = $this->resolveNextSectionKey($current, $visibleFields, $sections);

        if ($nextKey === null || $nextKey === DynamicFormLogicEvaluator::SUBMIT_SENTINEL) {
            $this->completePreview();

            return;
        }

        $targetIndex = $this->findSectionIndex($sections, $nextKey);
        if ($targetIndex === null || in_array($nextKey, $this->sectionHistory, true)) {
            $this->previewError = "Target lompatan [{$nextKey}] tidak valid atau membentuk loop.";

            return;
        }

        $this->currentSectionIndex = $targetIndex;
        $this->sectionHistory[] = $nextKey;
    }

    public function previousSection(): void
    {
        $this->validationErrors = [];
        $this->previewError = null;
        $this->isCompleted = false;

        if (count($this->sectionHistory) <= 1) {
            return;
        }

        array_pop($this->sectionHistory);
        $prevKey = end($this->sectionHistory);
        $sections = $this->orderedSections();
        $idx = $this->findSectionIndex($sections, (string) $prevKey);
        $this->currentSectionIndex = $idx ?? 0;
    }

    public function resetPreview(): void
    {
        $this->answers = [];
        $this->validationErrors = [];
        $this->previewError = null;
        $this->isCompleted = false;
        $this->currentSectionIndex = 0;
        $sections = $this->orderedSections();
        $this->sectionHistory = $sections !== [] ? [(string) $sections[0]['section_key']] : [];
    }

    private function completePreview(): void
    {
        $form = DynamicForm::find($this->formId);
        if (! $form) {
            $this->previewError = 'Formulir tidak ditemukan.';

            return;
        }

        try {
            $validator = app(DynamicFormSubmissionValidator::class);
            // Pada pratinjau, file_upload disimulasikan tanpa token DB agar staf dapat menguji alur penuh.
            $sanitizedSchema = $this->schemaWithoutRequiredFileUploads($this->schema);
            $validator->validate($form, $sanitizedSchema, $this->answers);
            $this->isCompleted = true;
        } catch (ValidationException $e) {
            $this->validationErrors = $e->errors();
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function orderedSections(): array
    {
        $sections = array_values((array) ($this->schema['sections'] ?? []));
        usort($sections, fn (array $a, array $b): int => ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0)));

        return $sections;
    }

    /**
     * @param  array<string, mixed>  $section
     * @return list<array<string, mixed>>
     */
    private function visibleFieldsForSection(array $section): array
    {
        $evaluator = app(DynamicFormLogicEvaluator::class);
        $evaluation = $evaluator->evaluate($this->schema, $this->answers);
        $targetKey = (string) ($section['section_key'] ?? '');

        if (in_array($targetKey, $evaluation['active_section_keys'], true)) {
            $visibleKeys = array_flip($evaluation['visible_field_keys']);
            $fields = array_values((array) ($section['fields'] ?? []));
            usort($fields, fn (array $a, array $b): int => ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0)));

            return array_values(array_filter(
                $fields,
                fn (array $f): bool => isset($visibleKeys[(string) ($f['field_key'] ?? '')])
            ));
        }

        $effectiveAnswers = $evaluation['sanitized_answers'];
        $fields = array_values((array) ($section['fields'] ?? []));
        usort($fields, fn (array $a, array $b): int => ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0)));

        $visible = [];
        foreach ($fields as $field) {
            $fKey = (string) ($field['field_key'] ?? '');
            if ($evaluator->evaluateVisibility($field, $effectiveAnswers)) {
                $visible[] = $field;
                if (array_key_exists($fKey, $this->answers)) {
                    $effectiveAnswers[$fKey] = $this->answers[$fKey];
                }
            } else {
                unset($effectiveAnswers[$fKey]);
            }
        }

        return $visible;
    }

    /**
     * @param  list<array<string, mixed>>  $visibleFields
     * @return array<string, list<string>>
     */
    private function validateVisibleStepFields(array $visibleFields): array
    {
        $form = DynamicForm::find($this->formId);
        if (! $form) {
            return [];
        }

        $stepSchema = $this->schemaWithoutRequiredFileUploads([
            'sections' => [[
                'section_key' => 'step_check',
                'title' => 'Step Check',
                'sort_order' => 1,
                'next_section_rule' => ['action' => 'submit', 'target_section_key' => null],
                'fields' => array_map(function (array $f): array {
                    $f['conditional_logic'] = ['visibility' => ['enabled' => false, 'match' => 'all', 'rules' => []]];

                    return $f;
                }, $visibleFields),
            ]],
        ]);

        try {
            app(DynamicFormSubmissionValidator::class)->validate($form, $stepSchema, $this->answers);

            return [];
        } catch (ValidationException $e) {
            return $e->errors();
        }
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  list<array<string, mixed>>  $visibleFields
     * @param  list<array<string, mixed>>  $sections
     */
    private function resolveNextSectionKey(array $current, array $visibleFields, array $sections): ?string
    {
        $choiceJump = null;
        foreach ($visibleFields as $field) {
            $type = (string) ($field['type'] ?? '');
            if (! in_array($type, [DynamicFormFieldType::Radio->value, DynamicFormFieldType::Dropdown->value], true)) {
                continue;
            }
            $fieldKey = (string) ($field['field_key'] ?? '');
            $val = $this->answers[$fieldKey] ?? null;
            $selected = is_array($val) ? (string) ($val['selected'] ?? '') : (string) $val;
            if ($selected === '') {
                continue;
            }
            foreach ((array) ($field['options']['choices'] ?? []) as $choice) {
                if ((string) ($choice['key'] ?? '') === $selected && filled($choice['go_to_section_key'] ?? null)) {
                    $choiceJump = trim((string) $choice['go_to_section_key']);
                }
            }
        }

        if ($choiceJump !== null) {
            return $choiceJump;
        }

        $rule = (array) ($current['next_section_rule'] ?? []);
        $action = (string) ($rule['action'] ?? 'next');
        $target = trim((string) ($rule['target_section_key'] ?? ''));

        if ($action === 'submit' || $target === DynamicFormLogicEvaluator::SUBMIT_SENTINEL) {
            return DynamicFormLogicEvaluator::SUBMIT_SENTINEL;
        }

        if ($action === 'jump' && $target !== '') {
            return $target;
        }

        return isset($sections[$this->currentSectionIndex + 1]['section_key'])
            ? (string) $sections[$this->currentSectionIndex + 1]['section_key']
            : null;
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     */
    private function findSectionIndex(array $sections, string $key): ?int
    {
        foreach ($sections as $idx => $section) {
            if ((string) ($section['section_key'] ?? '') === $key) {
                return $idx;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function schemaWithoutRequiredFileUploads(array $schema): array
    {
        $schema['sections'] = array_map(function (array $section): array {
            $section['fields'] = array_map(function (array $field): array {
                if (($field['type'] ?? '') === DynamicFormFieldType::FileUpload->value) {
                    $field['is_required'] = false;
                }

                return $field;
            }, (array) ($section['fields'] ?? []));

            return $section;
        }, (array) ($schema['sections'] ?? []));

        return $schema;
    }

    public function render(): View
    {
        $sections = $this->orderedSections();
        $currentSection = $sections[$this->currentSectionIndex] ?? null;
        $visibleFields = $currentSection ? $this->visibleFieldsForSection($currentSection) : [];
        $evaluation = app(DynamicFormLogicEvaluator::class)->evaluate($this->schema, $this->answers);

        return view('filament.resources.dynamic-forms.components.live-preview', [
            'sections' => $sections,
            'currentSection' => $currentSection,
            'visibleFields' => $visibleFields,
            'evaluation' => $evaluation,
        ]);
    }
}
