<?php

namespace App\Filament\Resources\DynamicForms\Schemas;

use App\Enums\DynamicFormFieldType;
use App\Enums\DynamicFormMode;
use App\Enums\DynamicFormTextFormatPreset;
use App\Services\DynamicForm\DynamicFormLogicEvaluator;
use App\Services\DynamicForm\DynamicFormVersionService;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Js;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DynamicFormWizardSchema
{
    /**
     * Susun 4 langkah Wizard Filament untuk CreateDynamicForm dan EditDynamicForm.
     *
     * @return list<Step>
     */
    public static function steps(bool $isEdit = false): array
    {
        return [
            self::stepBasicInfo(),
            self::stepSectionsAndFields(),
            self::stepConditionalLogic($isEdit),
            self::stepScheduleAndPublication($isEdit),
        ];
    }

    private static function stepBasicInfo(): Step
    {
        return Step::make(__('Info Dasar & Mode'))
            ->description(__('Identitas formulir, mode operasional, dan bahasa tunggal.'))
            ->extraAttributes(['wire:ignore.self' => true])
            ->schema([
                Section::make(__('Identitas Formulir'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label(__('Judul Formulir'))
                            ->placeholder(__('Contoh: Survei Kepuasan Layanan Rawat Jalan'))
                            ->required()
                            ->clearErrorOnInput()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->skipRenderAfterStateUpdated()
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $operation, ?string $state): void {
                                if (($operation === 'create' || empty($get('slug'))) && filled($state)) {
                                    $set('slug', Str::slug($state));
                                }
                            }),

                        TextInput::make('slug')
                            ->label(__('Slug URL Web'))
                            ->placeholder('survei-kepuasan-rawat-jalan')
                            ->required()
                            ->clearErrorOnInput()
                            ->maxLength(255)
                            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                            ->unique(table: 'dynamic_forms', column: 'slug', ignoreRecord: true)
                            ->validationMessages([
                                'unique' => __('Slug formulir sudah digunakan. Gunakan slug lain.'),
                                'regex' => __('Slug hanya boleh berisi huruf kecil, angka, dan tanda hubung (-).'),
                            ])
                            ->helperText(__('Menjadi alamat publik /formulir/{slug} di Web Frontend.')),

                        Select::make('mode')
                            ->label(__('Mode Formulir'))
                            ->options(DynamicFormMode::options())
                            ->default(DynamicFormMode::Questionnaire->value)
                            ->required()
                            ->helperText(__('Pilih kategori peruntukan formulir (Kuesioner / Survei atau Kritik & Saran).')),

                        Select::make('locale')
                            ->label(__('Bahasa Formulir'))
                            ->options([
                                'id' => __('Bahasa Indonesia (ID)'),
                                'en' => __('English (EN)'),
                            ])
                            ->default('id')
                            ->required()
                            ->helperText(__('Setiap formulir menggunakan satu bahasa. Gunakan aksi Duplikat Form untuk versi bahasa lain.')),

                        self::makeDescriptionRichEditor(
                            'description',
                            __('Deskripsi Formulir (Opsional)'),
                            __('Penjelasan singkat tujuan pengisian atau petunjuk umum formulir untuk responden.'),
                        ),
                    ]),
            ]);
    }

    private static function makeDescriptionRichEditor(string $name, string $label, string $placeholder): RichEditor
    {
        return RichEditor::make($name)
            ->label($label)
            ->placeholder($placeholder)
            ->toolbarButtons([
                ['bold', 'italic', 'underline', 'strike'],
                ['h2', 'h3', 'blockquote'],
                ['bulletList', 'orderedList', 'link'],
                ['undo', 'redo'],
            ])
            ->maxLength(8000)
            ->columnSpanFull();
    }

    private static function stepSectionsAndFields(): Step
    {
        $preventEnter = ['x-on:keydown.enter.prevent' => ''];
        $newSectionFallback = Js::from(__('Bagian Baru'))->toHtml();
        $newFieldFallback = Js::from(__('Pertanyaan Baru'))->toHtml();
        $syncSectionHeaderJs = "const item = \$el.closest('.fi-fo-repeater-item'); const heading = item?.querySelector(':scope > .fi-fo-repeater-item-header .fi-fo-repeater-item-header-label'); if (heading) { const list = item.parentElement; const idx = list ? Array.from(list.children).filter((c) => c.classList.contains('fi-fo-repeater-item')).indexOf(item) + 1 : 0; const title = (\$el.value || '').trim() || {$newSectionFallback}; heading.textContent = idx > 0 ? title + ' ' + idx : title; }";
        $syncFieldHeaderJs = "const item = \$el.closest('.fi-fo-repeater-item'); const heading = item?.querySelector(':scope > .fi-fo-repeater-item-header .fi-fo-repeater-item-header-label'); if (heading) { const list = item.parentElement; const idx = list ? Array.from(list.children).filter((c) => c.classList.contains('fi-fo-repeater-item')).indexOf(item) + 1 : 0; const labelInput = item.querySelector('[data-df-field-label]'); const typeSelect = item.querySelector('[data-df-field-type]'); const label = (labelInput?.value || '').trim() || {$newFieldFallback}; const typeText = typeSelect?.selectedOptions?.[0]?.text?.trim() || ''; const base = typeText ? label + ' (' + typeText + ')' : label; heading.textContent = idx > 0 ? base + ' ' + idx : base; }";

        return Step::make(__('Section & Pertanyaan'))
            ->description(__('Kelompokkan pertanyaan ke dalam satu atau lebih bagian (section) dengan 12 pilihan tipe input.'))
            ->extraAttributes(['wire:ignore.self' => true])
            ->afterValidation(function (Get $get): void {
                $sections = (array) ($get('sections') ?? []);
                $dupErrors = app(DynamicFormLogicEvaluator::class)->validateUniqueRawKeys($sections);

                if ($dupErrors !== []) {
                    Notification::make()
                        ->danger()
                        ->title(__('Terdapat Kunci Duplikat pada Formulir'))
                        ->body(implode(' ', $dupErrors))
                        ->send();

                    throw ValidationException::withMessages([
                        'data.sections' => $dupErrors,
                        'sections' => $dupErrors,
                    ]);
                }
            })
            ->schema([
                Repeater::make('sections')
                    ->label(__('Daftar Bagian (Section)'))
                    ->addActionLabel(__('Tambah Bagian (Section)'))
                    ->addAction(fn (Action $action): Action => $action->color('primary')->icon(Heroicon::OutlinedFolderPlus))
                    ->cloneAction(fn (Action $action): Action => $action->action(function (array $arguments, Repeater $component): void {
                        $items = (array) ($component->getRawState() ?? []);
                        $sourceKey = $arguments['item'] ?? null;
                        if ($sourceKey === null || ! isset($items[$sourceKey]) || ! is_array($items[$sourceKey])) {
                            return;
                        }

                        $cloned = $items[$sourceKey];
                        $cloned['section_key'] = 'sec_auto_'.Str::lower(Str::random(5));

                        if (is_array($cloned['fields'] ?? null)) {
                            $rekeyedFields = [];
                            foreach ($cloned['fields'] as $fieldItem) {
                                if (! is_array($fieldItem)) {
                                    continue;
                                }
                                $fieldItem['field_key'] = 'fld_auto_'.Str::lower(Str::random(5));
                                $rekeyedFields[(string) Str::uuid()] = $fieldItem;
                            }
                            $cloned['fields'] = $rekeyedFields;
                        }

                        $newUuid = $component->generateUuid();
                        if ($newUuid) {
                            $items[$newUuid] = $cloned;
                        } else {
                            $items[] = $cloned;
                        }

                        $component->rawState($items);
                        $component->collapsed(false, shouldMakeComponentCollapsible: false);
                        $component->callAfterStateUpdated();
                        $component->shouldPartiallyRenderAfterActionsCalled() ? $component->partiallyRender() : null;
                    }))
                    ->required()
                    ->minItems(1)
                    ->defaultItems(1)
                    ->reorderable()
                    ->collapsible()
                    ->cloneable()
                    ->itemNumbers()
                    ->extraAttributes([
                        'style' => 'overflow-anchor: none;',
                        'class' => 'rse-df-sections-repeater',
                    ])
                    ->itemLabel(fn (array $state): string => filled($state['title'] ?? null)
                        ? (string) $state['title']
                        : __('Bagian Baru'))
                    ->schema([
                        Hidden::make('section_key')
                            ->default(fn (): string => 'sec_auto_'.Str::lower(Str::random(5))),

                        TextInput::make('title')
                            ->label(__('Judul Bagian'))
                            ->placeholder(__('Contoh: Data Kunjungan Pasien'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->live(onBlur: true)
                            ->skipRenderAfterStateUpdated()
                            ->extraInputAttributes($preventEnter + ['x-on:input' => $syncSectionHeaderJs])
                            ->afterStateUpdated(function (Set $set, Get $get, Component $component, ?string $state): void {
                                $currentKey = (string) ($get('section_key') ?? '');
                                if (filled($state) && ($currentKey === '' || str_starts_with($currentKey, 'sec_auto_'))) {
                                    $slug = Str::slug($state, '_');
                                    if ($slug !== '') {
                                        $existing = self::siblingRepeaterKeys($get, $component, 'section_key');
                                        $set('section_key', self::deduplicateKey(substr($slug, 0, 60), $existing));
                                    }
                                }
                            }),

                        self::makeDescriptionRichEditor(
                            'description',
                            __('Deskripsi Bagian (Opsional)'),
                            __('Tambahkan petunjuk pengisian atau informasi pembuka untuk bagian ini.'),
                        ),

                        Repeater::make('fields')
                            ->label(__('Daftar Pertanyaan & Blok Informasi'))
                            ->addActionLabel(__('Tambah Pertanyaan'))
                            ->addAction(fn (Action $action): Action => $action->color('success')->icon(Heroicon::OutlinedPlusCircle))
                            ->cloneAction(fn (Action $action): Action => $action->action(function (array $arguments, Repeater $component): void {
                                $items = (array) ($component->getRawState() ?? []);
                                $sourceKey = $arguments['item'] ?? null;
                                if ($sourceKey === null || ! isset($items[$sourceKey]) || ! is_array($items[$sourceKey])) {
                                    return;
                                }

                                $cloned = $items[$sourceKey];
                                $cloned['field_key'] = 'fld_auto_'.Str::lower(Str::random(5));

                                $newUuid = $component->generateUuid();
                                if ($newUuid) {
                                    $items[$newUuid] = $cloned;
                                } else {
                                    $items[] = $cloned;
                                }

                                $component->rawState($items);
                                $component->collapsed(false, shouldMakeComponentCollapsible: false);
                                $component->callAfterStateUpdated();
                                $component->shouldPartiallyRenderAfterActionsCalled() ? $component->partiallyRender() : null;
                            }))
                            ->required()
                            ->minItems(1)
                            ->defaultItems(1)
                            ->reorderable()
                            ->collapsible()
                            ->cloneable()
                            ->itemNumbers()
                            ->columnSpanFull()
                            ->extraAttributes([
                                'style' => 'overflow-anchor: none;',
                                'class' => 'rse-df-fields-repeater',
                            ])
                            ->itemLabel(function (array $state): string {
                                $label = filled($state['label'] ?? null) ? (string) $state['label'] : __('Pertanyaan Baru');
                                $type = DynamicFormFieldType::tryFrom((string) ($state['type'] ?? ''));

                                return $type ? "{$label} ({$type->label()})" : $label;
                            })
                            ->schema([
                                Hidden::make('field_key')
                                    ->default(fn (): string => 'fld_auto_'.Str::lower(Str::random(5))),

                                TextInput::make('label')
                                    ->label(__('Pertanyaan / Judul Blok'))
                                    ->placeholder(__('Tuliskan pertanyaan atau judul informasi'))
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(['default' => 1, 'md' => 2])
                                    ->live(onBlur: true)
                                    ->skipRenderAfterStateUpdated()
                                    ->extraInputAttributes($preventEnter + [
                                        'data-df-field-label' => '1',
                                        'x-on:input' => $syncFieldHeaderJs,
                                    ])
                                    ->afterStateUpdated(function (Set $set, Get $get, Component $component, ?string $state): void {
                                        $currentKey = (string) ($get('field_key') ?? '');
                                        if (filled($state) && ($currentKey === '' || str_starts_with($currentKey, 'fld_auto_'))) {
                                            $slug = Str::slug($state, '_');
                                            if ($slug !== '') {
                                                $existing = self::siblingFieldKeys($get, $component);
                                                $set('field_key', self::deduplicateKey(substr($slug, 0, 60), $existing));
                                            }
                                        }
                                    }),

                                Select::make('type')
                                    ->label(__('Tipe Input'))
                                    ->options(DynamicFormFieldType::options())
                                    ->default(DynamicFormFieldType::ShortText->value)
                                    ->required()
                                    ->columnSpan(['default' => 1, 'md' => 1])
                                    ->live()
                                    ->afterStateUpdated(static function (Component $component): void {
                                        $component->getContainer()->getComponent('field_config', withHidden: true)?->partiallyRender();
                                    })
                                    ->extraInputAttributes([
                                        'data-df-field-type' => '1',
                                        'x-on:change' => $syncFieldHeaderJs,
                                    ]),

                                Group::make([
                                    // Konfigurasi Short Text (1 dropdown tunggal Format Jawaban)
                                    Hidden::make('options.subtype')
                                        ->default('text'),

                                    Hidden::make('validation_rules.format_preset'),

                                    Select::make('options.text_format')
                                        ->label(__('Format Jawaban'))
                                        ->options(DynamicFormTextFormatPreset::unifiedShortTextOptions())
                                        ->default('text')
                                        ->selectablePlaceholder(false)
                                        ->helperText(__('Tentukan jenis data atau batas karakter yang diterima responden.'))
                                        ->columnSpanFull()
                                        ->live()
                                        ->afterStateHydrated(static function (Select $component, Get $get): void {
                                            if (blank($component->getState())) {
                                                $component->state(self::shortTextFormat($get));
                                            }
                                        })
                                        ->afterStateUpdated(static function (Set $set, Component $component, ?string $state): void {
                                            $split = DynamicFormTextFormatPreset::splitUnifiedFormat($state);
                                            $set('options.subtype', $split['subtype']);
                                            $set('validation_rules.format_preset', $split['format_preset']);
                                            $component->getContainer()->getParentComponent()?->partiallyRender();
                                        })
                                        ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::ShortText),

                                    TextInput::make('validation_rules.min_length')
                                        ->label(__('Panjang Karakter Minimum'))
                                        ->numeric()
                                        ->minValue(0)
                                        ->extraInputAttributes($preventEnter)
                                        ->visible(fn (Get $get): bool => (
                                            self::fieldType($get) === DynamicFormFieldType::ShortText
                                            && ! in_array(self::shortTextFormat($get), ['number', 'phone'], true)
                                        ) || self::fieldType($get) === DynamicFormFieldType::LongText),

                                    TextInput::make('validation_rules.max_length')
                                        ->label(__('Panjang Karakter Maksimum'))
                                        ->numeric()
                                        ->minValue(1)
                                        ->extraInputAttributes($preventEnter)
                                        ->maxValue(fn (Get $get): int => self::fieldType($get) === DynamicFormFieldType::LongText ? 5000 : 500)
                                        ->rule(fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                            $min = $get('validation_rules.min_length');
                                            if (is_numeric($value) && is_numeric($min) && (int) $value < (int) $min) {
                                                $fail(__('Panjang maksimum tidak boleh lebih kecil dari panjang minimum.'));
                                            }
                                        })
                                        ->visible(fn (Get $get): bool => (
                                            self::fieldType($get) === DynamicFormFieldType::ShortText
                                            && ! in_array(self::shortTextFormat($get), ['number', 'phone'], true)
                                        ) || self::fieldType($get) === DynamicFormFieldType::LongText),

                                    TextInput::make('validation_rules.min_value')
                                        ->label(__('Nilai Angka Minimum'))
                                        ->numeric()
                                        ->extraInputAttributes($preventEnter)
                                        ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::ShortText
                                            && self::shortTextFormat($get) === 'number'),

                                    TextInput::make('validation_rules.max_value')
                                        ->label(__('Nilai Angka Maksimum'))
                                        ->numeric()
                                        ->extraInputAttributes($preventEnter)
                                        ->rule(fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                            $min = $get('validation_rules.min_value');
                                            if (is_numeric($value) && is_numeric($min) && (float) $value < (float) $min) {
                                                $fail(__('Nilai maksimum tidak boleh lebih kecil dari nilai minimum.'));
                                            }
                                        })
                                        ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::ShortText
                                            && self::shortTextFormat($get) === 'number'),

                                    // Konfigurasi Pilihan (Radio, Checkbox, Dropdown)
                                    Repeater::make('options.choices')
                                        ->label(__('Daftar Opsi Pilihan'))
                                        ->addActionLabel(__('Tambah Opsi Pilihan'))
                                        ->columns(1)
                                        ->defaultItems(2)
                                        ->minItems(1)
                                        ->reorderable()
                                        ->columnSpanFull()
                                        ->extraAttributes(['style' => 'overflow-anchor: none;'])
                                        ->visible(fn (Get $get): bool => self::fieldType($get)?->supportsChoices() ?? false)
                                        ->schema([
                                            Hidden::make('key')
                                                ->default(fn (): string => 'opt_auto_'.Str::lower(Str::random(5))),
                                            TextInput::make('label')
                                                ->label(__('Label Pilihan'))
                                                ->placeholder(__('Tuliskan teks pilihan jawaban'))
                                                ->required()
                                                ->maxLength(255)
                                                ->live(onBlur: true)
                                                ->skipRenderAfterStateUpdated()
                                                ->extraInputAttributes($preventEnter)
                                                ->afterStateUpdated(function (Set $set, Get $get, Component $component, ?string $state): void {
                                                    $currentKey = (string) ($get('key') ?? '');
                                                    if (filled($state) && ($currentKey === '' || str_starts_with($currentKey, 'opt_auto_'))) {
                                                        $slug = Str::slug($state, '_');
                                                        if ($slug !== '') {
                                                            $existing = self::siblingRepeaterKeys($get, $component, 'key');
                                                            $set('key', self::deduplicateKey(substr($slug, 0, 60), $existing));
                                                        }
                                                    }
                                                }),
                                        ]),

                                    Toggle::make('options.allow_other')
                                        ->label(__('Sediakan Opsi "Lainnya"'))
                                        ->default(false)
                                        ->live()
                                        ->afterStateUpdated(static function (Component $component): void {
                                            $component->getContainer()->getParentComponent()?->partiallyRender();
                                        })
                                        ->visible(fn (Get $get): bool => in_array(self::fieldType($get), [
                                            DynamicFormFieldType::Radio,
                                            DynamicFormFieldType::Checkbox,
                                        ], true)),

                                    TextInput::make('options.other_label')
                                        ->label(__('Label Opsi Lainnya'))
                                        ->default('Lainnya')
                                        ->maxLength(100)
                                        ->extraInputAttributes($preventEnter)
                                        ->visible(fn (Get $get): bool => in_array(self::fieldType($get), [
                                            DynamicFormFieldType::Radio,
                                            DynamicFormFieldType::Checkbox,
                                        ], true) && (bool) $get('options.allow_other')),

                                    TextInput::make('validation_rules.min_selected')
                                        ->label(__('Minimal Pilihan Dicentang'))
                                        ->numeric()
                                        ->minValue(0)
                                        ->extraInputAttributes($preventEnter)
                                        ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::Checkbox),

                                    TextInput::make('validation_rules.max_selected')
                                        ->label(__('Maksimal Pilihan Dicentang'))
                                        ->numeric()
                                        ->minValue(1)
                                        ->extraInputAttributes($preventEnter)
                                        ->rule(fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                            $min = $get('validation_rules.min_selected');
                                            if (is_numeric($value) && is_numeric($min) && (int) $value < (int) $min) {
                                                $fail(__('Maksimal pilihan tidak boleh lebih kecil dari minimal pilihan.'));
                                            }
                                        })
                                        ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::Checkbox),

                                    // Konfigurasi Linear Scale
                                    Select::make('options.min_value')
                                        ->label(__('Nilai Awal Skala'))
                                        ->options([0 => '0', 1 => '1'])
                                        ->default(1)
                                        ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::LinearScale),

                                    Select::make('options.max_value')
                                        ->label(__('Nilai Maksimum Skala'))
                                        ->options(array_combine(range(2, 10), array_map('strval', range(2, 10))))
                                        ->default(5)
                                        ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::LinearScale),

                                    TextInput::make('options.min_label')
                                        ->label(__('Label Nilai Terendah (Opsional)'))
                                        ->placeholder(__('Contoh: Sangat Tidak Puas'))
                                        ->extraInputAttributes($preventEnter)
                                        ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::LinearScale),

                                    TextInput::make('options.max_label')
                                        ->label(__('Label Nilai Tertinggi (Opsional)'))
                                        ->placeholder(__('Contoh: Sangat Puas'))
                                        ->extraInputAttributes($preventEnter)
                                        ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::LinearScale),

                                    // Konfigurasi Rating
                                    Select::make('options.max_stars')
                                        ->label(__('Jumlah Maksimal Ikon Penilaian'))
                                        ->options(array_combine(range(3, 10), array_map('strval', range(3, 10))))
                                        ->default(5)
                                        ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::Rating),

                                    Select::make('options.icon')
                                        ->label(__('Bentuk Ikon Penilaian'))
                                        ->options([
                                            'star' => __('Bintang'),
                                            'heart' => __('Hati'),
                                            'thumb' => __('Jempol'),
                                        ])
                                        ->default('star')
                                        ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::Rating),

                                    // Konfigurasi Matrix Grid
                                    Select::make('options.selection_mode')
                                        ->label(__('Mode Pilihan Matrix'))
                                        ->options([
                                            'single' => __('Satu pilihan per baris'),
                                            'multiple' => __('Banyak pilihan per baris (checkbox grid)'),
                                        ])
                                        ->default('single')
                                        ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::MatrixGrid),

                                    Toggle::make('options.require_all_rows')
                                        ->label(__('Wajib Isi Setiap Baris'))
                                        ->default(false)
                                        ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::MatrixGrid),

                                    Repeater::make('options.rows')
                                        ->label(__('Daftar Baris Matrix (Butir Pertanyaan)'))
                                        ->addActionLabel(__('Tambah Baris Matrix'))
                                        ->columns(1)
                                        ->defaultItems(2)
                                        ->minItems(1)
                                        ->columnSpanFull()
                                        ->extraAttributes(['style' => 'overflow-anchor: none;'])
                                        ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::MatrixGrid)
                                        ->schema([
                                            Hidden::make('key')
                                                ->default(fn (): string => 'row_auto_'.Str::lower(Str::random(5))),
                                            TextInput::make('label')
                                                ->label(__('Label Baris'))
                                                ->placeholder(__('Contoh: Keramahan Petugas'))
                                                ->required()
                                                ->maxLength(255)
                                                ->live(onBlur: true)
                                                ->skipRenderAfterStateUpdated()
                                                ->extraInputAttributes($preventEnter)
                                                ->afterStateUpdated(function (Set $set, Get $get, Component $component, ?string $state): void {
                                                    $currentKey = (string) ($get('key') ?? '');
                                                    if (filled($state) && ($currentKey === '' || str_starts_with($currentKey, 'row_auto_'))) {
                                                        $slug = Str::slug($state, '_');
                                                        if ($slug !== '') {
                                                            $existing = self::siblingRepeaterKeys($get, $component, 'key');
                                                            $set('key', self::deduplicateKey(substr($slug, 0, 60), $existing));
                                                        }
                                                    }
                                                }),
                                        ]),

                                    Repeater::make('options.columns')
                                        ->label(__('Daftar Kolom Matrix (Skala / Pilihan)'))
                                        ->addActionLabel(__('Tambah Kolom Matrix'))
                                        ->columns(['default' => 1, 'sm' => 2])
                                        ->defaultItems(3)
                                        ->minItems(1)
                                        ->columnSpanFull()
                                        ->extraAttributes(['style' => 'overflow-anchor: none;'])
                                        ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::MatrixGrid)
                                        ->schema([
                                            Hidden::make('key')
                                                ->default(fn (): string => 'col_auto_'.Str::lower(Str::random(5))),
                                            TextInput::make('label')
                                                ->label(__('Label Kolom'))
                                                ->placeholder(__('Contoh: Sangat Puas'))
                                                ->required()
                                                ->maxLength(255)
                                                ->live(onBlur: true)
                                                ->skipRenderAfterStateUpdated()
                                                ->extraInputAttributes($preventEnter)
                                                ->afterStateUpdated(function (Set $set, Get $get, Component $component, ?string $state): void {
                                                    $currentKey = (string) ($get('key') ?? '');
                                                    if (filled($state) && ($currentKey === '' || str_starts_with($currentKey, 'col_auto_'))) {
                                                        $slug = Str::slug($state, '_');
                                                        if ($slug !== '') {
                                                            $existing = self::siblingRepeaterKeys($get, $component, 'key');
                                                            $set('key', self::deduplicateKey(substr($slug, 0, 60), $existing));
                                                        }
                                                    }
                                                }),
                                            TextInput::make('score')
                                                ->label(__('Skor Numerik (Opsional)'))
                                                ->numeric()
                                                ->extraInputAttributes($preventEnter),
                                        ]),

                                    // Konfigurasi Tanggal & Jam
                                    TextInput::make('validation_rules.min_date')
                                        ->label(__('Batas Tanggal Awal (YYYY-MM-DD atau today)'))
                                        ->placeholder(__('Contoh: 2026-01-01 atau today'))
                                        ->extraInputAttributes($preventEnter)
                                        ->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                                            if (! self::isValidDateBound($value)) {
                                                $fail(__('Format batas tanggal harus YYYY-MM-DD atau kata kunci today.'));
                                            }
                                        })
                                        ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::Date),

                                    TextInput::make('validation_rules.max_date')
                                        ->label(__('Batas Tanggal Akhir (YYYY-MM-DD atau today)'))
                                        ->placeholder(__('Contoh: today'))
                                        ->extraInputAttributes($preventEnter)
                                        ->rule(fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                            if (! self::isValidDateBound($value)) {
                                                $fail(__('Format batas tanggal harus YYYY-MM-DD atau kata kunci today.'));

                                                return;
                                            }
                                            $min = trim((string) ($get('validation_rules.min_date') ?? ''));
                                            $max = trim((string) $value);
                                            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $min) === 1 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $max) === 1 && $max < $min) {
                                                $fail(__('Batas tanggal akhir tidak boleh sebelum tanggal awal.'));
                                            }
                                        })
                                        ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::Date),

                                    TextInput::make('validation_rules.min_time')
                                        ->label(__('Batas Jam Awal (HH:MM)'))
                                        ->placeholder('07:00')
                                        ->regex('/^([01]\d|2[0-3]):[0-5]\d$/')
                                        ->extraInputAttributes($preventEnter)
                                        ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::Time),

                                    TextInput::make('validation_rules.max_time')
                                        ->label(__('Batas Jam Akhir (HH:MM)'))
                                        ->placeholder('21:00')
                                        ->regex('/^([01]\d|2[0-3]):[0-5]\d$/')
                                        ->extraInputAttributes($preventEnter)
                                        ->rule(fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                            $min = trim((string) ($get('validation_rules.min_time') ?? ''));
                                            $max = trim((string) $value);
                                            if ($min !== '' && $max !== '' && $max < $min) {
                                                $fail(__('Batas jam akhir tidak boleh sebelum jam awal.'));
                                            }
                                        })
                                        ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::Time),

                                    // Konfigurasi File Upload
                                    Select::make('options.allowed_extensions')
                                        ->label(__('Ekstensi Berkas yang Diizinkan'))
                                        ->multiple()
                                        ->columnSpanFull()
                                        ->options([
                                            'pdf' => 'PDF (.pdf)',
                                            'jpg' => 'JPG (.jpg)',
                                            'jpeg' => 'JPEG (.jpeg)',
                                            'png' => 'PNG (.png)',
                                            'webp' => 'WebP (.webp)',
                                            'doc' => 'Word (.doc)',
                                            'docx' => 'Word (.docx)',
                                            'xls' => 'Excel (.xls)',
                                            'xlsx' => 'Excel (.xlsx)',
                                            'txt' => __('Teks (.txt)'),
                                        ])
                                        ->default(['pdf', 'jpg', 'jpeg', 'png'])
                                        ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::FileUpload),

                                    TextInput::make('options.max_file_size_mb')
                                        ->label(__('Ukuran Maksimal per Berkas (MB)'))
                                        ->numeric()
                                        ->minValue(1)
                                        ->maxValue(10)
                                        ->default(5)
                                        ->extraInputAttributes($preventEnter)
                                        ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::FileUpload),

                                    TextInput::make('options.max_files')
                                        ->label(__('Jumlah Maksimal Berkas'))
                                        ->numeric()
                                        ->minValue(1)
                                        ->maxValue(5)
                                        ->default(1)
                                        ->extraInputAttributes($preventEnter)
                                        ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::FileUpload),

                                    // Konfigurasi Info Block
                                    Textarea::make('options.content')
                                        ->label(__('Isi Konten Informasi'))
                                        ->rows(4)
                                        ->columnSpanFull()
                                        ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::InfoBlock),

                                    TextInput::make('options.media_url')
                                        ->label(__('Tautan Media / Banner Opsional'))
                                        ->url()
                                        ->columnSpanFull()
                                        ->extraInputAttributes($preventEnter)
                                        ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::InfoBlock),

                                    // Pengaturan Bantuan & Status Wajib di bagian bawah kartu
                                    TextInput::make('placeholder')
                                        ->label(__('Placeholder Input (Opsional)'))
                                        ->placeholder(__('Contoh teks samar di dalam kotak input'))
                                        ->maxLength(255)
                                        ->extraInputAttributes($preventEnter)
                                        ->visible(fn (Get $get): bool => in_array(self::fieldType($get), [
                                            DynamicFormFieldType::ShortText,
                                            DynamicFormFieldType::LongText,
                                            DynamicFormFieldType::Dropdown,
                                            DynamicFormFieldType::Date,
                                            DynamicFormFieldType::Time,
                                        ], true)),

                                    TextInput::make('help_text')
                                        ->label(__('Teks Bantuan (Opsional)'))
                                        ->placeholder(__('Petunjuk singkat di bawah pertanyaan'))
                                        ->maxLength(500)
                                        ->extraInputAttributes($preventEnter)
                                        ->columnSpan(fn (Get $get): array => in_array(self::fieldType($get), [
                                            DynamicFormFieldType::ShortText,
                                            DynamicFormFieldType::LongText,
                                            DynamicFormFieldType::Dropdown,
                                            DynamicFormFieldType::Date,
                                            DynamicFormFieldType::Time,
                                        ], true) ? ['default' => 1, 'md' => 1] : ['default' => 1, 'md' => 2])
                                        ->visible(fn (Get $get): bool => self::fieldType($get) !== DynamicFormFieldType::InfoBlock),

                                    Toggle::make('is_required')
                                        ->label(__('Wajib Diisi'))
                                        ->default(false)
                                        ->columnSpanFull()
                                        ->visible(fn (Get $get): bool => self::fieldType($get) !== DynamicFormFieldType::InfoBlock),
                                ])
                                    ->key('field_config', isInheritable: false)
                                    ->columnSpanFull()
                                    ->columns(['default' => 1, 'md' => 2]),
                            ])
                            ->columns(['default' => 1, 'md' => 3]),
                    ]),
            ]);
    }

    private static function stepConditionalLogic(bool $isEdit = false): Step
    {
        return Step::make(__('Percabangan Logika'))
            ->description(__('Atur tampilan bersyarat antar-pertanyaan dan lompatan antar-bagian secara deklaratif.'))
            ->extraAttributes(function (Step $component) use ($isEdit): array {
                $attrs = ['wire:ignore.self' => true];

                if ($isEdit) {
                    $stepKey = Js::from($component->getKey())->toHtml();
                    $targetKey = Js::from($component->resolveRelativeKey('step_conditional_logic_body'))->toHtml();
                    $attrs['x-init'] = "\$watch('step', (next, prev) => { if (next === {$stepKey} && prev && next !== prev) { \$wire.partiallyRenderSchemaComponent({$targetKey}) } })";
                }

                return $attrs;
            })
            ->afterValidation(function (Get $get): void {
                $sections = (array) ($get('sections') ?? []);
                $logicConfig = (array) ($get('logic_config') ?? []);
                $evaluator = app(DynamicFormLogicEvaluator::class);

                $dupErrors = $evaluator->validateUniqueRawKeys($sections);
                if ($dupErrors !== []) {
                    Notification::make()
                        ->danger()
                        ->title(__('Terdapat Kunci Duplikat pada Formulir'))
                        ->body(implode(' ', $dupErrors))
                        ->send();

                    throw ValidationException::withMessages([
                        'data.sections' => $dupErrors,
                        'sections' => $dupErrors,
                    ]);
                }

                $rawLogicErrors = $evaluator->validateRawLogicConfig($logicConfig);
                $merged = app(DynamicFormVersionService::class)->mergeLogicConfigIntoSections($sections, $logicConfig);
                $errors = array_values(array_unique([
                    ...$rawLogicErrors,
                    ...$evaluator->validateSchemaLogic($merged),
                ]));

                if ($errors !== []) {
                    Notification::make()
                        ->danger()
                        ->title(__('Aturan Percabangan Logika Tidak Valid'))
                        ->body(implode(' ', $errors))
                        ->send();

                    throw ValidationException::withMessages([
                        'data.logic_config.visibility_rules' => $errors,
                        'logic_config' => $errors,
                    ]);
                }
            })
            ->schema([
                Group::make([
                    Placeholder::make('logic_guide')
                        ->hiddenLabel()
                        ->content(static function (Get $get, Component $component): HtmlString {
                            $sections = self::sectionsState($get, $component);

                            return new HtmlString(view('filament.resources.dynamic-forms.components.logic-guide', [
                                'sectionCount' => count($sections),
                            ])->render());
                        }),

                    Section::make(__('1. Aturan Tampil/Sembunyi Pertanyaan (Visibility)'))
                        ->description(__('Tampilkan pertanyaan tertentu secara langsung (real-time) hanya jika jawaban pertanyaan sebelumnya memenuhi kondisi. Cocok untuk pertanyaan lanjutan di halaman yang sama atau berikutnya.'))
                        ->schema([
                            Repeater::make('logic_config.visibility_rules')
                                ->label(__('Daftar Aturan Visibilitas'))
                                ->defaultItems(0)
                                ->collapsible()
                                ->addActionLabel(__('Tambah Aturan Visibilitas'))
                                ->itemLabel(static function (array $state, Get $get, Component $component): string {
                                    $targetKey = trim((string) ($state['target_field_key'] ?? ''));
                                    if ($targetKey === '') {
                                        return __('Aturan Visibilitas Baru');
                                    }

                                    $sections = self::sectionsState($get, $component);
                                    $targetLabel = self::fieldDisplayLabel($sections, $targetKey);
                                    $matchLabel = ($state['match'] ?? 'all') === 'any' ? __('salah satu') : __('semua');
                                    $ruleCount = count((array) ($state['rules'] ?? []));

                                    return __('Tampilkan ":target" jika :match syarat terpenuhi (:count syarat)', [
                                        'target' => $targetLabel,
                                        'match' => $matchLabel,
                                        'count' => $ruleCount,
                                    ]);
                                })
                                ->schema([
                                    Select::make('target_field_key')
                                        ->label(__('Pertanyaan Target'))
                                        ->options(fn (Get $get, Component $component): array => self::fieldSelectOptions(
                                            self::sectionsState($get, $component),
                                            includeInfoBlocks: true
                                        ))
                                        ->placeholder(__('Pilih pertanyaan atau blok informasi yang diatur'))
                                        ->required()
                                        ->searchable()
                                        ->live()
                                        ->afterStateUpdated(function (Set $set, Get $get, Component $component, ?string $state): void {
                                            $sections = self::sectionsState($get, $component);
                                            $allowedSources = array_map(
                                                'strval',
                                                array_keys(self::visibilitySourceFieldOptions($sections, (string) ($state ?? '')))
                                            );

                                            $rules = (array) ($get('rules') ?? []);
                                            $changed = false;

                                            foreach ($rules as $ruleKey => $rule) {
                                                if (! is_array($rule)) {
                                                    continue;
                                                }
                                                $sourceKey = trim((string) ($rule['source_field_key'] ?? ''));
                                                if ($sourceKey !== '' && ! in_array($sourceKey, $allowedSources, true)) {
                                                    $rules[$ruleKey]['source_field_key'] = null;
                                                    $rules[$ruleKey]['operator'] = 'equals';
                                                    $rules[$ruleKey]['value'] = null;
                                                    $changed = true;
                                                }
                                            }

                                            if ($changed) {
                                                $set('rules', $rules);
                                                $component->getContainer()->getComponent('rules', withHidden: true)?->clearCachedChildSchemas();
                                            }
                                        }),

                                    Select::make('match')
                                        ->label(__('Kombinasi Syarat'))
                                        ->options([
                                            'all' => __('Semua kondisi harus terpenuhi (AND)'),
                                            'any' => __('Salah satu kondisi terpenuhi (OR)'),
                                        ])
                                        ->default('all')
                                        ->live()
                                        ->required(),

                                    Repeater::make('rules')
                                        ->key('rules')
                                        ->label(__('Kondisi Pemicu'))
                                        ->minItems(1)
                                        ->defaultItems(1)
                                        ->columns(3)
                                        ->columnSpanFull()
                                        ->schema([
                                            Select::make('source_field_key')
                                                ->label(__('Berdasarkan Jawaban Pertanyaan'))
                                                ->options(fn (Get $get, Component $component): array => self::visibilitySourceFieldOptions(
                                                    self::sectionsState($get, $component),
                                                    (string) ($get('../../target_field_key') ?? '')
                                                ))
                                                ->placeholder(fn (Get $get, Component $component): string => self::visibilitySourcePlaceholder(
                                                    self::sectionsState($get, $component),
                                                    (string) ($get('../../target_field_key') ?? '')
                                                ))
                                                ->noOptionsMessage(__('Tidak ada pertanyaan sebelumnya yang dapat dijadikan pemicu untuk target ini.'))
                                                ->required()
                                                ->searchable()
                                                ->live()
                                                ->afterStateUpdated(function (Set $set, Get $get, Component $component, ?string $state): void {
                                                    $sections = self::sectionsState($get, $component);
                                                    $sourceField = self::findFieldByKey($sections, (string) ($state ?? ''));

                                                    if ($sourceField === null) {
                                                        $set('operator', 'equals');
                                                        $set('value', null);
                                                    } else {
                                                        $allowed = DynamicFormLogicEvaluator::allowedOperatorsForField($sourceField);
                                                        $currentOperator = (string) ($get('operator') ?? 'equals');
                                                        $nextOperator = in_array($currentOperator, $allowed, true)
                                                            ? $currentOperator
                                                            : ($allowed[0] ?? 'equals');

                                                        if ($nextOperator !== $currentOperator) {
                                                            $set('operator', $nextOperator);
                                                        }

                                                        if (in_array($nextOperator, ['is_empty', 'is_not_empty'], true)) {
                                                            $set('value', null);
                                                        } elseif (
                                                            DynamicFormLogicEvaluator::fieldSupportsSelectValue($sourceField)
                                                            && in_array($nextOperator, ['in', 'not_in'], true)
                                                        ) {
                                                            $set('value', []);
                                                        } else {
                                                            $set('value', null);
                                                        }
                                                    }

                                                    $component->getContainer()->getComponent('rule_value_group', withHidden: true)?->clearCachedChildSchemas();
                                                }),

                                            Select::make('operator')
                                                ->label(__('Operator'))
                                                ->options(fn (Get $get, Component $component): array => DynamicFormLogicEvaluator::operatorOptionsForField(
                                                    self::findFieldByKey(
                                                        self::sectionsState($get, $component),
                                                        (string) ($get('source_field_key') ?? '')
                                                    )
                                                ))
                                                ->default('equals')
                                                ->required()
                                                ->live()
                                                ->afterStateUpdated(function (Set $set, Get $get, Component $component, ?string $state): void {
                                                    $operator = (string) ($state ?? 'equals');
                                                    $sourceField = self::findFieldByKey(
                                                        self::sectionsState($get, $component),
                                                        (string) ($get('source_field_key') ?? '')
                                                    );

                                                    if (in_array($operator, ['is_empty', 'is_not_empty'], true)) {
                                                        $set('value', null);
                                                    } elseif (DynamicFormLogicEvaluator::fieldSupportsSelectValue($sourceField)) {
                                                        $currentVal = $get('value');
                                                        $validKeys = array_map('strval', array_keys(self::valueSelectOptionsForField($sourceField)));

                                                        if (in_array($operator, ['in', 'not_in'], true)) {
                                                            $items = is_array($currentVal)
                                                                ? $currentVal
                                                                : (is_string($currentVal) && trim($currentVal) !== '' ? explode(',', $currentVal) : []);

                                                            $filtered = array_values(array_unique(array_filter(
                                                                array_map(fn ($v): string => is_scalar($v) ? trim((string) $v) : '', $items),
                                                                fn (string $v): bool => $v !== '' && in_array($v, $validKeys, true)
                                                            )));

                                                            $set('value', $filtered);
                                                        } elseif (is_array($currentVal)) {
                                                            $firstValid = null;
                                                            foreach ($currentVal as $item) {
                                                                $str = is_scalar($item) ? trim((string) $item) : '';
                                                                if ($str !== '' && in_array($str, $validKeys, true)) {
                                                                    $firstValid = $str;
                                                                    break;
                                                                }
                                                            }
                                                            $set('value', $firstValid);
                                                        } elseif (filled($currentVal) && ! in_array(trim((string) $currentVal), $validKeys, true)) {
                                                            $set('value', null);
                                                        }
                                                    } elseif (
                                                        $sourceField !== null
                                                        && (string) ($sourceField['type'] ?? '') === DynamicFormFieldType::ShortText->value
                                                        && (string) ($sourceField['options']['subtype'] ?? 'text') === 'number'
                                                        && ! in_array($operator, ['in', 'not_in'], true)
                                                    ) {
                                                        $rawContainerState = $component->getContainer()->getRawState();
                                                        $currentVal = is_array($rawContainerState)
                                                            ? ($rawContainerState['value'] ?? null)
                                                            : $get('value');
                                                        if (is_array($currentVal) || (filled($currentVal) && ! is_numeric(trim((string) $currentVal)))) {
                                                            $set('value', null);
                                                        }
                                                    }

                                                    $component->getContainer()->getComponent('rule_value_group', withHidden: true)?->clearCachedChildSchemas();
                                                }),

                                            Group::make()
                                                ->key('rule_value_group')
                                                ->hidden(fn (Get $get): bool => in_array((string) ($get('operator') ?? 'equals'), ['is_empty', 'is_not_empty'], true))
                                                ->schema(fn (Get $get, Component $component): array => self::visibilityRuleValueSchema($get, $component)),
                                        ]),
                                ])
                                ->columns(2),
                        ]),

                    Section::make(__('2. Lompatan Bagian Berdasarkan Jawaban (Radio / Dropdown)'))
                        ->description(__('Arahkan responden melompati halaman/Bagian tertentu atau langsung kirim formulir saat menekan Lanjutkan berdasarkan opsi Radio/Dropdown. Maksimal 1 pertanyaan pemicu lompatan per Bagian.'))
                        ->schema([
                            Repeater::make('logic_config.choice_jumps')
                                ->label(__('Daftar Lompatan Pilihan'))
                                ->defaultItems(0)
                                ->collapsible()
                                ->columns(3)
                                ->addActionLabel(__('Tambah Lompatan Pilihan'))
                                ->itemLabel(static function (array $state, Get $get, Component $component): string {
                                    $sourceKey = trim((string) ($state['source_field_key'] ?? ''));
                                    if ($sourceKey === '') {
                                        return __('Lompatan Pilihan Baru');
                                    }

                                    $sections = self::sectionsState($get, $component);
                                    $sourceLabel = self::fieldDisplayLabel($sections, $sourceKey);
                                    $choiceKey = trim((string) ($state['choice_key'] ?? ''));
                                    $choiceLabel = $choiceKey !== ''
                                        ? (self::choiceOptionsForField($sections, $sourceKey)[$choiceKey] ?? $choiceKey)
                                        : '...';
                                    $targetKey = trim((string) ($state['go_to_section_key'] ?? ''));
                                    $targetLabel = $targetKey !== ''
                                        ? self::sectionTargetDisplayLabel($sections, $targetKey)
                                        : '...';

                                    return __('Jika ":source" = ":choice" → :target', [
                                        'source' => $sourceLabel,
                                        'choice' => $choiceLabel,
                                        'target' => $targetLabel,
                                    ]);
                                })
                                ->schema([
                                    Select::make('source_field_key')
                                        ->label(__('Pertanyaan Sumber (Radio/Dropdown)'))
                                        ->options(fn (Get $get, Component $component): array => self::jumpableFieldOptions(
                                            self::sectionsState($get, $component)
                                        ))
                                        ->placeholder(__('Pilih pertanyaan Radio atau Dropdown'))
                                        ->noOptionsMessage(__('Belum ada pertanyaan bertipe Radio atau Dropdown pada formulir ini.'))
                                        ->searchable()
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(function (Set $set, Get $get, Component $component, ?string $state): void {
                                            $set('choice_key', null);

                                            $sections = self::sectionsState($get, $component);
                                            $sourceSectionKey = self::sectionKeyForField($sections, (string) ($state ?? ''));
                                            $allowedTargets = array_map(
                                                'strval',
                                                array_keys(self::forwardSectionTargetOptions($sections, $sourceSectionKey))
                                            );
                                            $currentTarget = trim((string) ($get('go_to_section_key') ?? ''));

                                            if ($currentTarget !== '' && ! in_array($currentTarget, $allowedTargets, true)) {
                                                $set('go_to_section_key', null);
                                            }
                                        }),

                                    Select::make('choice_key')
                                        ->label(__('Saat Memilih Opsi'))
                                        ->options(fn (Get $get, Component $component): array => self::choiceOptionsForField(
                                            self::sectionsState($get, $component),
                                            (string) ($get('source_field_key') ?? '')
                                        ))
                                        ->placeholder(fn (Get $get): string => filled($get('source_field_key'))
                                            ? __('Pilih opsi jawaban')
                                            : __('Pilih pertanyaan sumber terlebih dahulu'))
                                        ->noOptionsMessage(__('Pertanyaan ini belum memiliki opsi pilihan.'))
                                        ->searchable()
                                        ->live()
                                        ->required(),

                                    Select::make('go_to_section_key')
                                        ->label(__('Lompat ke Bagian'))
                                        ->options(function (Get $get, Component $component): array {
                                            $sections = self::sectionsState($get, $component);
                                            $sourceSectionKey = self::sectionKeyForField(
                                                $sections,
                                                (string) ($get('source_field_key') ?? '')
                                            );

                                            return self::forwardSectionTargetOptions($sections, $sourceSectionKey);
                                        })
                                        ->in(fn (Get $get, Component $component): array => array_keys(
                                            self::sectionTargetOptions(self::sectionsState($get, $component))
                                        ))
                                        ->placeholder(__('Pilih Bagian tujuan atau Kirim Formulir'))
                                        ->helperText(__('Hanya menampilkan Bagian setelah posisi pertanyaan ini atau Kirim Formulir.'))
                                        ->searchable()
                                        ->live()
                                        ->required(),
                                ]),
                        ]),

                    Section::make(__('3. Aturan Lanjut di Akhir Bagian'))
                        ->description(__('Atur tujuan default setelah responden menyelesaikan sebuah Bagian apabila tidak ada lompatan dari Menu 2 yang terpicu. Sangat penting sebagai penutup cabang agar responden tidak masuk ke Bagian cabang lain.'))
                        ->schema([
                            Repeater::make('logic_config.section_jumps')
                                ->label(__('Aturan Transisi Bagian'))
                                ->defaultItems(0)
                                ->collapsible()
                                ->columns(3)
                                ->addActionLabel(__('Tambah Aturan Akhir Bagian'))
                                ->itemLabel(static function (array $state, Get $get, Component $component): string {
                                    $sectionKey = trim((string) ($state['section_key'] ?? ''));
                                    if ($sectionKey === '') {
                                        return __('Aturan Akhir Bagian Baru');
                                    }

                                    $sections = self::sectionsState($get, $component);
                                    $sectionLabel = self::sectionDisplayLabel($sections, $sectionKey);
                                    $action = (string) ($state['action'] ?? 'next');

                                    $destination = match ($action) {
                                        'submit' => __('Langsung kirim formulir'),
                                        'jump' => __('Lompat ke :target', [
                                            'target' => self::sectionTargetDisplayLabel(
                                                $sections,
                                                (string) ($state['target_section_key'] ?? '')
                                            ),
                                        ]),
                                        default => __('Lanjut ke bagian berikutnya'),
                                    };

                                    return __('Setelah ":section" → :destination', [
                                        'section' => $sectionLabel,
                                        'destination' => $destination,
                                    ]);
                                })
                                ->schema([
                                    Select::make('section_key')
                                        ->label(__('Setelah Bagian Ini'))
                                        ->options(fn (Get $get, Component $component): array => self::sectionOptions(
                                            self::sectionsState($get, $component)
                                        ))
                                        ->placeholder(__('Pilih Bagian'))
                                        ->searchable()
                                        ->live()
                                        ->required()
                                        ->afterStateUpdated(function (Set $set, Get $get, Component $component, ?string $state): void {
                                            if ((string) ($get('action') ?? 'next') !== 'jump') {
                                                return;
                                            }

                                            $sections = self::sectionsState($get, $component);
                                            $allowedTargets = array_map(
                                                'strval',
                                                array_keys(self::forwardSectionTargetOptions($sections, (string) ($state ?? '')))
                                            );
                                            $currentTarget = trim((string) ($get('target_section_key') ?? ''));

                                            if ($currentTarget !== '' && ! in_array($currentTarget, $allowedTargets, true)) {
                                                $set('target_section_key', null);
                                            }
                                        }),

                                    Select::make('action')
                                        ->label(__('Tindakan Berikutnya'))
                                        ->options([
                                            'next' => __('Lanjut ke bagian berikutnya'),
                                            'jump' => __('Lompat ke bagian tertentu'),
                                            'submit' => __('Langsung kirim formulir'),
                                        ])
                                        ->default('next')
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(function (Set $set, ?string $state): void {
                                            if ($state !== 'jump') {
                                                $set('target_section_key', null);
                                            }
                                        }),

                                    Select::make('target_section_key')
                                        ->label(__('Bagian Tujuan'))
                                        ->options(fn (Get $get, Component $component): array => self::forwardSectionTargetOptions(
                                            self::sectionsState($get, $component),
                                            (string) ($get('section_key') ?? '')
                                        ))
                                        ->in(fn (Get $get, Component $component): array => array_keys(
                                            self::sectionTargetOptions(self::sectionsState($get, $component))
                                        ))
                                        ->placeholder(__('Pilih Bagian tujuan atau Kirim Formulir'))
                                        ->helperText(__('Hanya menampilkan Bagian setelah Bagian yang dipilih atau Kirim Formulir.'))
                                        ->searchable()
                                        ->live()
                                        ->visible(fn (Get $get): bool => $get('action') === 'jump')
                                        ->required(fn (Get $get): bool => $get('action') === 'jump'),
                                ]),
                        ]),

                    Section::make(__('Peta Alur Bagian (Ringkasan Langsung)'))
                        ->description(__('Ringkasan otomatis perjalanan responden dari setiap Bagian berdasarkan konfigurasi Menu 1, 2, dan 3 saat ini.'))
                        ->collapsible()
                        ->schema([
                            Placeholder::make('logic_flow_map')
                                ->hiddenLabel()
                                ->content(static function (Get $get, Component $component): HtmlString {
                                    $sections = self::sectionsState($get, $component);
                                    $logicConfig = self::logicConfigState($get, $component);

                                    return new HtmlString(view('filament.resources.dynamic-forms.components.logic-flow-map', [
                                        'flowSections' => self::buildLogicFlowSummary($sections, $logicConfig),
                                    ])->render());
                                }),
                        ]),
                ])->key('step_conditional_logic_body', isInheritable: false),
            ]);
    }

    private static function stepScheduleAndPublication(bool $isEdit): Step
    {
        return Step::make(__('Jadwal, Kuota & Publikasi'))
            ->description(__('Tentukan jadwal buka-tutup, kuota maksimal, pesan kustom, dan status publikasi.'))
            ->extraAttributes(function (Step $component) use ($isEdit): array {
                $attrs = ['wire:ignore.self' => true];

                if ($isEdit) {
                    $stepKey = Js::from($component->getKey())->toHtml();
                    $targetKey = Js::from($component->resolveRelativeKey('step_readiness_summary'))->toHtml();
                    $attrs['x-init'] = "\$watch('step', (next, prev) => { if (next === {$stepKey} && prev && next !== prev) { \$wire.partiallyRenderSchemaComponent({$targetKey}) } })";
                }

                return $attrs;
            })
            ->schema([
                Section::make(__('Jadwal & Batasan Pengisian'))
                    ->columns(2)
                    ->schema([
                        DateTimePicker::make('starts_at')
                            ->label(__('Waktu Mulai Pengisian (Opsional)'))
                            ->seconds(false),

                        DateTimePicker::make('ends_at')
                            ->label(__('Waktu Berakhir Pengisian (Opsional)'))
                            ->seconds(false)
                            ->afterOrEqual('starts_at'),

                        TextInput::make('max_responses')
                            ->label(__('Kuota Maksimal Respons (Opsional)'))
                            ->numeric()
                            ->minValue(1)
                            ->helperText(__('Kosongkan jika jumlah respons tidak dibatasi.')),

                        Toggle::make('allow_multiple_submissions')
                            ->label(__('Izinkan pengisian berulang oleh responden yang sama'))
                            ->default(true)
                            ->helperText(__('Jika dimatikan, backend memvalidasi respondent_key agar tidak ganda.')),
                    ]),

                Section::make(__('Pesan Konfirmasi & Penutupan'))
                    ->columns(1)
                    ->schema([
                        Textarea::make('confirmation_message')
                            ->label(__('Pesan Konfirmasi Setelah Mengirim (Opsional)'))
                            ->rows(2)
                            ->placeholder(__('Terima kasih. Jawaban Anda telah berhasil direkam.')),

                        Textarea::make('closed_message')
                            ->label(__('Pesan Saat Formulir Ditutup / Kuota Penuh (Opsional)'))
                            ->rows(2)
                            ->placeholder(__('Mohon maaf, periode pengisian formulir ini sudah berakhir.')),
                    ]),

                Section::make(__('Ringkasan Kesiapan Publikasi'))
                    ->schema([
                        Placeholder::make('readiness_summary')
                            ->key('step_readiness_summary')
                            ->label(__('Ringkasan Konfigurasi Saat Ini'))
                            ->content(function (Get $get): string {
                                $sections = (array) ($get('sections') ?? []);
                                $fieldCount = collect($sections)
                                    ->flatMap(fn ($s) => (array) ($s['fields'] ?? []))
                                    ->filter(fn ($f) => ($f['type'] ?? '') !== DynamicFormFieldType::InfoBlock->value)
                                    ->count();
                                $logic = (array) ($get('logic_config') ?? []);
                                $ruleCount = count((array) ($logic['visibility_rules'] ?? []))
                                    + count((array) ($logic['choice_jumps'] ?? []))
                                    + count((array) ($logic['section_jumps'] ?? []));
                                $modeLabel = DynamicFormMode::tryFrom(self::enumValue($get('mode')))?->label() ?? '-';

                                return __('Mode: :mode | Bagian: :sections | Pertanyaan Input: :fields | Aturan Logika: :rules', [
                                    'mode' => $modeLabel,
                                    'sections' => count($sections),
                                    'fields' => $fieldCount,
                                    'rules' => $ruleCount,
                                ]);
                            }),

                        Toggle::make('is_public_listed')
                            ->label(__('Tampilkan di Katalog Formulir Publik (/formulir)'))
                            ->default(false)
                            ->helperText(__('Jika dimatikan, formulir hanya dapat diakses via tautan langsung atau QR Code dan tidak diindeks mesin pencari.')),

                        Toggle::make('publish_now')
                            ->label($isEdit
                                ? __('Publikasikan draf versi ini segera setelah disimpan')
                                : __('Publikasikan formulir langsung setelah disimpan'))
                            ->default(false)
                            ->helperText(__('Jika dimatikan, perubahan tersimpan sebagai draf tanpa mengubah versi yang sedang aktif di API.')),
                    ]),
            ]);
    }

    private static function fieldType(Get $get): ?DynamicFormFieldType
    {
        return DynamicFormFieldType::tryFrom((string) ($get('type') ?? ''));
    }

    private static function enumValue(mixed $value): string
    {
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        return (string) ($value ?? '');
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<string, string>
     */
    private static function sectionOptions(array $sections): array
    {
        $out = [];
        $sectionNum = 0;

        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }
            $key = (string) ($section['section_key'] ?? '');
            if ($key === '') {
                continue;
            }
            $sectionNum++;
            $title = trim((string) ($section['title'] ?? ''));
            $out[$key] = $title !== ''
                ? __('Bagian :number · :title', ['number' => $sectionNum, 'title' => $title])
                : __('Bagian :number', ['number' => $sectionNum]);
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<string, string>
     */
    private static function sectionTargetOptions(array $sections): array
    {
        $options = self::sectionOptions($sections);
        $options[DynamicFormLogicEvaluator::SUBMIT_SENTINEL] = __('Kirim Formulir (Selesai)');

        return $options;
    }

    /**
     * Saring opsi target lompatan agar hanya menampilkan Bagian setelah $fromSectionKey
     * ditambah opsi Kirim Formulir (mencegah lompatan ke Bagian yang sama atau mundur).
     *
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<string, string>
     */
    private static function forwardSectionTargetOptions(array $sections, ?string $fromSectionKey): array
    {
        $allSections = self::sectionOptions($sections);
        $fromKey = trim((string) ($fromSectionKey ?? ''));

        if ($fromKey === '' || ! array_key_exists($fromKey, $allSections)) {
            return self::sectionTargetOptions($sections);
        }

        $out = [];
        $passedSource = false;

        foreach ($allSections as $key => $label) {
            if ($key === $fromKey) {
                $passedSource = true;

                continue;
            }
            if ($passedSource) {
                $out[$key] = $label;
            }
        }

        $out[DynamicFormLogicEvaluator::SUBMIT_SENTINEL] = __('Kirim Formulir (Selesai)');

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     */
    private static function sectionKeyForField(array $sections, string $fieldKey): ?string
    {
        $targetKey = trim($fieldKey);
        if ($targetKey === '') {
            return null;
        }

        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }
            $sectionKey = (string) ($section['section_key'] ?? '');
            foreach ((array) ($section['fields'] ?? []) as $field) {
                if (is_array($field) && (string) ($field['field_key'] ?? '') === $targetKey) {
                    return $sectionKey !== '' ? $sectionKey : null;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     */
    private static function sectionDisplayLabel(array $sections, string $sectionKey): string
    {
        $options = self::sectionOptions($sections);

        return $options[$sectionKey] ?? ($sectionKey !== '' ? $sectionKey : __('Bagian'));
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     */
    private static function sectionTargetDisplayLabel(array $sections, string $targetKey): string
    {
        if ($targetKey === DynamicFormLogicEvaluator::SUBMIT_SENTINEL) {
            return __('Kirim Formulir (Selesai)');
        }

        return $targetKey !== '' ? self::sectionDisplayLabel($sections, $targetKey) : '...';
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     */
    private static function fieldDisplayLabel(array $sections, string $fieldKey): string
    {
        $field = self::findFieldByKey($sections, $fieldKey);
        if ($field === null) {
            return $fieldKey;
        }

        $label = trim((string) ($field['label'] ?? ''));

        return $label !== '' ? $label : $fieldKey;
    }

    /**
     * @return array<string, mixed>
     */
    private static function logicConfigState(Get $get, Component $component): array
    {
        $rootPath = $component->getRootContainer()->getStatePath();
        $logicPath = filled($rootPath) ? "{$rootPath}.logic_config" : 'logic_config';

        return (array) ($get($logicPath, isAbsolute: true) ?? []);
    }

    /**
     * Susun ringkasan Peta Alur Bagian untuk panel live read-only di bagian bawah Step 3.
     *
     * @param  array<int, array<string, mixed>>  $sections
     * @param  array<string, mixed>  $logicConfig
     * @return list<array{
     *     section_key: string,
     *     label: string,
     *     field_count: int,
     *     visibility_count: int,
     *     choice_branches: list<array{field_label: string, choice_label: string, target_label: string}>,
     *     default_next_label: string,
     *     warnings: list<string>
     * }>
     */
    private static function buildLogicFlowSummary(array $sections, array $logicConfig): array
    {
        $orderedSections = array_values(array_filter(
            $sections,
            static fn ($s): bool => is_array($s) && trim((string) ($s['section_key'] ?? '')) !== ''
        ));

        if ($orderedSections === []) {
            return [];
        }

        $sectionLabels = self::sectionOptions($orderedSections);
        $sectionIndexMap = [];
        $fieldToSection = [];
        $fieldLabelsByKey = [];
        $choiceLabelsByField = [];
        $inputFieldCountBySection = [];

        foreach ($orderedSections as $idx => $section) {
            $sKey = (string) ($section['section_key'] ?? '');
            $sectionIndexMap[$sKey] = $idx;
            $inputCount = 0;

            foreach ((array) ($section['fields'] ?? []) as $field) {
                if (! is_array($field)) {
                    continue;
                }
                $type = (string) ($field['type'] ?? '');
                if ($type !== DynamicFormFieldType::InfoBlock->value) {
                    $inputCount++;
                }
                $fKey = (string) ($field['field_key'] ?? '');
                if ($fKey === '') {
                    continue;
                }
                $fieldToSection[$fKey] = $sKey;
                $label = trim((string) ($field['label'] ?? ''));
                $fieldLabelsByKey[$fKey] = $label !== '' ? $label : $fKey;

                if (in_array($type, [DynamicFormFieldType::Radio->value, DynamicFormFieldType::Dropdown->value], true)) {
                    $choiceLabelsByField[$fKey] = self::buildChoiceLabelMap((array) ($field['options']['choices'] ?? []));
                }
            }

            $inputFieldCountBySection[$sKey] = $inputCount;
        }

        $resolveTargetLabel = static function (string $targetKey) use ($sectionLabels): string {
            if ($targetKey === DynamicFormLogicEvaluator::SUBMIT_SENTINEL) {
                return __('Kirim Formulir (Selesai)');
            }

            return $targetKey !== '' ? ($sectionLabels[$targetKey] ?? $targetKey) : '...';
        };

        $visibilityCountBySection = [];
        foreach ((array) ($logicConfig['visibility_rules'] ?? []) as $rule) {
            if (! is_array($rule)) {
                continue;
            }
            $targetKey = trim((string) ($rule['target_field_key'] ?? ''));
            $ownerSection = $fieldToSection[$targetKey] ?? null;
            if ($ownerSection !== null) {
                $visibilityCountBySection[$ownerSection] = ($visibilityCountBySection[$ownerSection] ?? 0) + 1;
            }
        }

        $choiceBranchesBySection = [];
        $jumpSourcesBySection = [];
        $warningsBySection = [];

        foreach ((array) ($logicConfig['choice_jumps'] ?? []) as $jump) {
            if (! is_array($jump)) {
                continue;
            }
            $sourceKey = trim((string) ($jump['source_field_key'] ?? ''));
            $choiceKey = trim((string) ($jump['choice_key'] ?? ''));
            $targetKey = trim((string) ($jump['go_to_section_key'] ?? ''));
            $ownerSection = $fieldToSection[$sourceKey] ?? null;

            if ($sourceKey === '' || $ownerSection === null) {
                continue;
            }

            $fieldLabel = $fieldLabelsByKey[$sourceKey] ?? $sourceKey;
            $choiceLabel = $choiceKey !== ''
                ? ($choiceLabelsByField[$sourceKey][$choiceKey] ?? $choiceKey)
                : __('(opsi belum dipilih)');
            $targetLabel = $targetKey !== ''
                ? $resolveTargetLabel($targetKey)
                : __('(tujuan belum dipilih)');

            $choiceBranchesBySection[$ownerSection][] = [
                'field_label' => $fieldLabel,
                'choice_label' => $choiceLabel,
                'target_label' => $targetLabel,
            ];

            if ($targetKey !== '') {
                $jumpSourcesBySection[$ownerSection][$sourceKey] = $fieldLabel;

                if (
                    $targetKey !== DynamicFormLogicEvaluator::SUBMIT_SENTINEL
                    && isset($sectionIndexMap[$targetKey])
                    && $sectionIndexMap[$targetKey] <= $sectionIndexMap[$ownerSection]
                ) {
                    $warningsBySection[$ownerSection][] = __('Lompatan dari ":field" ke ":target" tidak valid (tidak boleh mundur atau mengarah ke Bagian yang sama).', [
                        'field' => $fieldLabel,
                        'target' => $targetLabel,
                    ]);
                }
            }
        }

        foreach ($jumpSourcesBySection as $sKey => $sources) {
            if (count($sources) > 1) {
                $warningsBySection[$sKey][] = __('Konflik Menu 2: terdapat lebih dari 1 pertanyaan pemicu lompatan di Bagian ini (:sources). Sisakan maksimal 1 pertanyaan pemicu lompatan per Bagian.', [
                    'sources' => implode(', ', array_values($sources)),
                ]);
            }
        }

        $sectionJumpByKey = [];
        foreach ((array) ($logicConfig['section_jumps'] ?? []) as $sJump) {
            if (! is_array($sJump)) {
                continue;
            }
            $sKey = trim((string) ($sJump['section_key'] ?? ''));
            if ($sKey === '') {
                continue;
            }
            if (isset($sectionJumpByKey[$sKey])) {
                $warningsBySection[$sKey][] = __('Terdapat lebih dari satu aturan akhir Bagian untuk Bagian ini di Menu 3.');
            }
            $sectionJumpByKey[$sKey] = $sJump;
        }

        $result = [];
        $totalSections = count($orderedSections);

        foreach ($orderedSections as $idx => $section) {
            $sKey = (string) ($section['section_key'] ?? '');
            $sJump = $sectionJumpByKey[$sKey] ?? null;
            $action = is_array($sJump) ? (string) ($sJump['action'] ?? 'next') : 'next';
            $targetKey = is_array($sJump) ? trim((string) ($sJump['target_section_key'] ?? '')) : '';

            $warnings = $warningsBySection[$sKey] ?? [];

            if ($action === 'submit' || $targetKey === DynamicFormLogicEvaluator::SUBMIT_SENTINEL) {
                $defaultNext = __('Kirim Formulir (Selesai)');
            } elseif ($action === 'jump') {
                $defaultNext = $targetKey !== ''
                    ? __('Lompat ke :target', ['target' => $resolveTargetLabel($targetKey)])
                    : __('Lompat (Bagian tujuan belum dipilih)');

                if (
                    $targetKey !== ''
                    && isset($sectionIndexMap[$targetKey])
                    && $sectionIndexMap[$targetKey] <= $idx
                ) {
                    $warnings[] = __('Aturan akhir Bagian (Menu 3) mengarah ke Bagian yang sama atau sebelumnya.');
                }
            } elseif ($idx + 1 < $totalSections) {
                $nextSectionKey = (string) ($orderedSections[$idx + 1]['section_key'] ?? '');
                $defaultNext = __('Lanjut ke :section', [
                    'section' => $sectionLabels[$nextSectionKey] ?? __('Bagian berikutnya'),
                ]);
            } else {
                $defaultNext = __('Kirim Formulir (Bagian terakhir)');
            }

            $result[] = [
                'section_key' => $sKey,
                'label' => $sectionLabels[$sKey] ?? $sKey,
                'field_count' => $inputFieldCountBySection[$sKey] ?? 0,
                'visibility_count' => $visibilityCountBySection[$sKey] ?? 0,
                'choice_branches' => $choiceBranchesBySection[$sKey] ?? [],
                'default_next_label' => $defaultNext,
                'warnings' => array_values(array_unique($warnings)),
            ];
        }

        return $result;
    }

    private static function shortTextFormat(Get $get): string
    {
        $rawFormat = $get('options.text_format');
        if (is_string($rawFormat) && $rawFormat !== '') {
            return $rawFormat;
        }

        return DynamicFormTextFormatPreset::resolveShortTextFormat(
            is_string($get('options.subtype')) ? $get('options.subtype') : null,
            is_string($get('validation_rules.format_preset')) ? $get('validation_rules.format_preset') : null,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function sectionsState(Get $get, Component $component): array
    {
        $rootPath = $component->getRootContainer()->getStatePath();
        $sectionsPath = filled($rootPath) ? "{$rootPath}.sections" : 'sections';

        return array_values((array) ($get($sectionsPath, isAbsolute: true) ?? []));
    }

    /**
     * @return list<string>
     */
    private static function siblingRepeaterKeys(Get $get, Component $component, string $keyField): array
    {
        $currentItemKey = $component->getParentRepeaterItem()?->getStatePath(isAbsolute: false);
        $siblings = $component->getParentRepeater()?->getRawState();

        if (! is_array($siblings)) {
            $siblings = $get('../');
        }

        if (! is_array($siblings)) {
            return [];
        }

        $keys = [];
        foreach ($siblings as $itemKey => $item) {
            if ($currentItemKey !== null && (string) $itemKey === $currentItemKey) {
                continue;
            }
            if (! is_array($item)) {
                continue;
            }
            $k = strtolower(trim((string) ($item[$keyField] ?? '')));
            if ($k !== '') {
                $keys[] = $k;
            }
        }

        return $keys;
    }

    /**
     * @return list<string>
     */
    private static function siblingFieldKeys(Get $get, Component $component): array
    {
        $currentFieldItemKey = $component->getParentRepeaterItem()?->getStatePath(isAbsolute: false);
        $currentSectionItemKey = $component->getParentRepeater()?->getParentRepeaterItem()?->getStatePath(isAbsolute: false);

        $sections = $component->getParentRepeater()?->getParentRepeater()?->getRawState();
        if (! is_array($sections)) {
            $rootPath = $component->getRootContainer()->getStatePath();
            $sectionsPath = filled($rootPath) ? "{$rootPath}.sections" : 'sections';
            $sections = (array) ($get($sectionsPath, isAbsolute: true) ?? []);
        }

        $keys = [];
        foreach ($sections as $sItemKey => $section) {
            if (! is_array($section)) {
                continue;
            }
            foreach ((array) ($section['fields'] ?? []) as $fItemKey => $field) {
                if (
                    $currentSectionItemKey !== null
                    && $currentFieldItemKey !== null
                    && (string) $sItemKey === $currentSectionItemKey
                    && (string) $fItemKey === $currentFieldItemKey
                ) {
                    continue;
                }
                if (! is_array($field)) {
                    continue;
                }
                $k = strtolower(trim((string) ($field['field_key'] ?? '')));
                if ($k !== '') {
                    $keys[] = $k;
                }
            }
        }

        return $keys;
    }

    /**
     * @param  array<int, mixed>  $existingKeys
     */
    private static function deduplicateKey(string $baseKey, array $existingKeys): string
    {
        $existingMap = array_flip(array_map('strval', $existingKeys));
        $base = substr($baseKey, 0, 80);
        if (! isset($existingMap[$base])) {
            return $base;
        }

        $counter = 2;
        do {
            $suffix = '_'.$counter;
            $candidate = substr($base, 0, 80 - strlen($suffix)).$suffix;
            $counter++;
        } while (isset($existingMap[$candidate]));

        return $candidate;
    }

    private static function isValidDateBound(mixed $value): bool
    {
        if (! is_string($value) || trim($value) === '') {
            return true;
        }

        $trimmed = strtolower(trim($value));
        if ($trimmed === 'today') {
            return true;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed) !== 1) {
            return false;
        }

        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $trimmed);

        return $parsed !== false && $parsed->format('Y-m-d') === $trimmed;
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<string, string>
     */
    private static function fieldSelectOptions(array $sections, bool $includeInfoBlocks = false): array
    {
        $out = [];
        $sectionNum = 0;

        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }
            $sectionNum++;
            $sectionTitle = trim((string) ($section['title'] ?? ''));
            $sectionPrefix = $sectionTitle !== ''
                ? __('Bagian :number: :title', ['number' => $sectionNum, 'title' => $sectionTitle])
                : __('Bagian :number', ['number' => $sectionNum]);
            $fields = array_values(array_filter((array) ($section['fields'] ?? []), 'is_array'));
            $labelCounts = self::countFieldLabels($fields, $includeInfoBlocks);
            $seenLabels = [];

            foreach ($fields as $field) {
                $key = (string) ($field['field_key'] ?? '');
                $isInfoBlock = ($field['type'] ?? '') === DynamicFormFieldType::InfoBlock->value;
                if ($key === '' || (! $includeInfoBlocks && $isInfoBlock)) {
                    continue;
                }
                $label = trim((string) ($field['label'] ?? ''));
                $baseLabel = $label !== '' ? $label : __('Pertanyaan tanpa judul');
                $display = $baseLabel;
                if (($labelCounts[$baseLabel] ?? 0) > 1) {
                    $seenLabels[$baseLabel] = ($seenLabels[$baseLabel] ?? 0) + 1;
                    $display = "{$baseLabel} (#{$seenLabels[$baseLabel]})";
                }
                $out[$key] = "[{$sectionPrefix}] {$display}";
            }
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<string, string>
     */
    private static function jumpableFieldOptions(array $sections): array
    {
        $out = [];
        $sectionNum = 0;

        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }
            $sectionNum++;
            $sectionTitle = trim((string) ($section['title'] ?? ''));
            $sectionPrefix = $sectionTitle !== ''
                ? __('Bagian :number: :title', ['number' => $sectionNum, 'title' => $sectionTitle])
                : __('Bagian :number', ['number' => $sectionNum]);
            $fields = array_values(array_filter(
                (array) ($section['fields'] ?? []),
                static fn ($field): bool => is_array($field)
                    && in_array((string) ($field['type'] ?? ''), [DynamicFormFieldType::Radio->value, DynamicFormFieldType::Dropdown->value], true)
            ));
            $labelCounts = self::countFieldLabels($fields, includeInfoBlocks: false);
            $seenLabels = [];

            foreach ($fields as $field) {
                $key = (string) ($field['field_key'] ?? '');
                if ($key === '') {
                    continue;
                }
                $label = trim((string) ($field['label'] ?? ''));
                $baseLabel = $label !== '' ? $label : __('Pertanyaan tanpa judul');
                $display = $baseLabel;
                if (($labelCounts[$baseLabel] ?? 0) > 1) {
                    $seenLabels[$baseLabel] = ($seenLabels[$baseLabel] ?? 0) + 1;
                    $display = "{$baseLabel} (#{$seenLabels[$baseLabel]})";
                }
                $out[$key] = "[{$sectionPrefix}] {$display}";
            }
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<string, string>
     */
    private static function choiceOptionsForField(array $sections, string $fieldKey): array
    {
        if ($fieldKey === '') {
            return [];
        }

        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }
            foreach ((array) ($section['fields'] ?? []) as $field) {
                if (! is_array($field) || (string) ($field['field_key'] ?? '') !== $fieldKey) {
                    continue;
                }

                return self::buildChoiceLabelMap((array) ($field['options']['choices'] ?? []));
            }
        }

        return [];
    }

    /**
     * Saring opsi Pertanyaan Sumber agar hanya memuat pertanyaan input (non-info_block)
     * yang posisinya berada sebelum Pertanyaan Target.
     *
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<string, string>
     */
    private static function visibilitySourceFieldOptions(array $sections, string $targetFieldKey): array
    {
        $targetKey = trim($targetFieldKey);
        $targetFound = $targetKey !== '' && self::findFieldByKey($sections, $targetKey) !== null;

        $out = [];
        $sectionNum = 0;

        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }
            $sectionNum++;
            $sectionTitle = trim((string) ($section['title'] ?? ''));
            $sectionPrefix = $sectionTitle !== ''
                ? __('Bagian :number: :title', ['number' => $sectionNum, 'title' => $sectionTitle])
                : __('Bagian :number', ['number' => $sectionNum]);
            $fields = array_values(array_filter((array) ($section['fields'] ?? []), 'is_array'));
            $labelCounts = self::countFieldLabels($fields, includeInfoBlocks: false);
            $seenLabels = [];

            foreach ($fields as $field) {
                $key = (string) ($field['field_key'] ?? '');
                if ($targetFound && $key === $targetKey) {
                    return $out;
                }
                $isInfoBlock = ($field['type'] ?? '') === DynamicFormFieldType::InfoBlock->value;
                if ($key === '' || $isInfoBlock) {
                    continue;
                }
                $label = trim((string) ($field['label'] ?? ''));
                $baseLabel = $label !== '' ? $label : __('Pertanyaan tanpa judul');
                $display = $baseLabel;
                if (($labelCounts[$baseLabel] ?? 0) > 1) {
                    $seenLabels[$baseLabel] = ($seenLabels[$baseLabel] ?? 0) + 1;
                    $display = "{$baseLabel} (#{$seenLabels[$baseLabel]})";
                }
                $out[$key] = "[{$sectionPrefix}] {$display}";
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return array<string, int>
     */
    private static function countFieldLabels(array $fields, bool $includeInfoBlocks): array
    {
        $counts = [];
        foreach ($fields as $field) {
            $key = (string) ($field['field_key'] ?? '');
            $isInfoBlock = ($field['type'] ?? '') === DynamicFormFieldType::InfoBlock->value;
            if ($key === '' || (! $includeInfoBlocks && $isInfoBlock)) {
                continue;
            }
            $label = trim((string) ($field['label'] ?? ''));
            $baseLabel = $label !== '' ? $label : __('Pertanyaan tanpa judul');
            $counts[$baseLabel] = ($counts[$baseLabel] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Format label opsi pilihan bersih tanpa kunci internal; tambahkan penanda (Opsi #N)
     * hanya apabila ada lebih dari satu opsi berlabel identik dalam pertanyaan yang sama.
     *
     * @param  array<int|string, mixed>  $rawChoices
     * @return array<string, string>
     */
    private static function buildChoiceLabelMap(array $rawChoices): array
    {
        $validChoices = [];
        $labelCounts = [];

        foreach ($rawChoices as $choice) {
            if (! is_array($choice)) {
                continue;
            }
            $key = trim((string) ($choice['key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $label = filled($choice['label'] ?? null) ? trim((string) $choice['label']) : $key;
            $validChoices[] = ['key' => $key, 'label' => $label];
            $labelCounts[$label] = ($labelCounts[$label] ?? 0) + 1;
        }

        $out = [];
        $seenLabels = [];

        foreach ($validChoices as $choice) {
            $label = $choice['label'];
            if (($labelCounts[$label] ?? 0) > 1) {
                $seenLabels[$label] = ($seenLabels[$label] ?? 0) + 1;
                $out[$choice['key']] = __(':label (Opsi #:number)', [
                    'label' => $label,
                    'number' => $seenLabels[$label],
                ]);
            } else {
                $out[$choice['key']] = $label;
            }
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     */
    private static function visibilitySourcePlaceholder(array $sections, string $targetFieldKey): string
    {
        $targetKey = trim($targetFieldKey);
        if ($targetKey !== '' && self::visibilitySourceFieldOptions($sections, $targetKey) === []) {
            return __('Tidak ada pertanyaan sebelumnya untuk target ini');
        }

        return __('Pilih pertanyaan pemicu');
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<string, mixed>|null
     */
    private static function findFieldByKey(array $sections, string $fieldKey): ?array
    {
        $key = trim($fieldKey);
        if ($key === '') {
            return null;
        }

        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }
            foreach ((array) ($section['fields'] ?? []) as $field) {
                if (is_array($field) && (string) ($field['field_key'] ?? '') === $key) {
                    return $field;
                }
            }
        }

        return null;
    }

    /**
     * Bangun daftar opsi Nilai Pembanding untuk pertanyaan bertipe pilihan, skala linier, atau rating.
     *
     * @param  array<string, mixed>  $field
     * @return array<string, string>
     */
    private static function valueSelectOptionsForField(array $field): array
    {
        $type = DynamicFormFieldType::tryFrom((string) ($field['type'] ?? ''));
        $options = (array) ($field['options'] ?? []);

        if ($type !== null && $type->supportsChoices()) {
            $out = self::buildChoiceLabelMap((array) ($options['choices'] ?? []));

            $allowOther = in_array($type, [DynamicFormFieldType::Radio, DynamicFormFieldType::Checkbox], true)
                && (bool) ($options['allow_other'] ?? false);

            if ($allowOther) {
                $otherFallback = __('Lainnya');
                $otherLabel = filled($options['other_label'] ?? null) ? trim((string) $options['other_label']) : $otherFallback;
                $out[DynamicFormLogicEvaluator::OTHER_CHOICE_KEY] = (strcasecmp($otherLabel, 'Lainnya') === 0 || strcasecmp($otherLabel, $otherFallback) === 0)
                    ? $otherLabel
                    : "{$otherLabel} ({$otherFallback})";
            }

            return $out;
        }

        if ($type === DynamicFormFieldType::LinearScale) {
            $min = max(0, min(1, (int) ($options['min_value'] ?? 1)));
            $max = max(2, min(10, (int) ($options['max_value'] ?? 5)));
            $minLabel = filled($options['min_label'] ?? null) ? trim((string) $options['min_label']) : null;
            $maxLabel = filled($options['max_label'] ?? null) ? trim((string) $options['max_label']) : null;

            $out = [];
            for ($i = $min; $i <= $max; $i++) {
                $label = (string) $i;
                if ($i === $min && $minLabel !== null) {
                    $label .= " ({$minLabel})";
                } elseif ($i === $max && $maxLabel !== null) {
                    $label .= " ({$maxLabel})";
                }
                $out[(string) $i] = $label;
            }

            return $out;
        }

        if ($type === DynamicFormFieldType::Rating) {
            $maxStars = max(3, min(10, (int) ($options['max_stars'] ?? 5)));
            $out = [];
            for ($i = 1; $i <= $maxStars; $i++) {
                $out[(string) $i] = (string) $i;
            }

            return $out;
        }

        return [];
    }

    /**
     * @return list<Component>
     */
    private static function visibilityRuleValueSchema(Get $get, Component $component): array
    {
        $operator = (string) ($get('operator') ?? 'equals');
        if (in_array($operator, ['is_empty', 'is_not_empty'], true)) {
            return [];
        }

        $sections = self::sectionsState($get, $component);
        $sourceKey = trim((string) ($get('source_field_key') ?? ''));
        $sourceField = self::findFieldByKey($sections, $sourceKey);
        $sourceType = $sourceField ? DynamicFormFieldType::tryFrom((string) ($sourceField['type'] ?? '')) : null;

        if ($sourceField !== null && DynamicFormLogicEvaluator::fieldSupportsSelectValue($sourceField)) {
            $isMultiple = in_array($operator, ['in', 'not_in'], true);
            $options = self::valueSelectOptionsForField($sourceField);
            $optionsSignature = substr(md5($sourceKey.'|'.json_encode($options)), 0, 12);
            $helperText = $sourceType?->supportsChoices()
                ? __('Perbandingan pilihan memakai kunci opsi secara otomatis.')
                : __('Pilih nilai angka sesuai rentang pertanyaan sumber.');

            $select = Select::make('value')
                ->key(($isMultiple ? 'value_select_multiple_' : 'value_select_single_').$optionsSignature)
                ->label(__('Nilai Pembanding'))
                ->options(static function (Get $get, Component $component): array {
                    $field = self::findFieldByKey(
                        self::sectionsState($get, $component),
                        (string) ($get('source_field_key') ?? '')
                    );

                    return $field !== null ? self::valueSelectOptionsForField($field) : [];
                })
                ->multiple($isMultiple)
                ->searchable()
                ->required()
                ->placeholder($isMultiple ? __('Pilih satu atau lebih nilai') : __('Pilih nilai pembanding'))
                ->helperText($helperText);

            if ($isMultiple) {
                $select->formatStateUsing(static function (mixed $state): array {
                    if (is_array($state)) {
                        return array_values(array_filter(
                            array_map(fn ($v): string => is_scalar($v) ? trim((string) $v) : '', $state),
                            fn (string $v): bool => $v !== ''
                        ));
                    }

                    if (is_scalar($state) && trim((string) $state) !== '') {
                        return array_values(array_filter(
                            array_map('trim', explode(',', (string) $state)),
                            fn (string $v): bool => $v !== ''
                        ));
                    }

                    return [];
                });
            } else {
                $select->formatStateUsing(static function (mixed $state): ?string {
                    if (is_array($state)) {
                        foreach ($state as $item) {
                            if (is_scalar($item) && trim((string) $item) !== '') {
                                return trim((string) $item);
                            }
                        }

                        return null;
                    }

                    return is_scalar($state) && trim((string) $state) !== '' ? trim((string) $state) : null;
                });
            }

            return [$select];
        }

        $isListOperator = in_array($operator, ['in', 'not_in'], true);
        $isNumberSubtype = $sourceType === DynamicFormFieldType::ShortText
            && (string) ($sourceField['options']['subtype'] ?? 'text') === 'number';

        $placeholder = match (true) {
            $sourceType === DynamicFormFieldType::Date => __('YYYY-MM-DD (contoh: 2026-05-01)'),
            $sourceType === DynamicFormFieldType::Time => __('HH:MM (contoh: 08:30)'),
            $isNumberSubtype && $isListOperator => __('Contoh: 10, 20, 30'),
            $isNumberSubtype => __('Contoh: 10'),
            $isListOperator => __('Contoh: nilai1, nilai2'),
            $operator === 'contains' => __('Masukkan kata atau frasa yang dicari'),
            $sourceField === null => __('Pilih pertanyaan sumber terlebih dahulu'),
            default => __('Masukkan teks pembanding'),
        };

        $helperText = match (true) {
            $sourceType === DynamicFormFieldType::Date => __('Gunakan format tanggal YYYY-MM-DD.'),
            $sourceType === DynamicFormFieldType::Time => __('Gunakan format waktu 24-jam HH:MM.'),
            $isNumberSubtype && $isListOperator => __('Masukkan beberapa nilai angka, pisahkan dengan tanda koma (,).'),
            $isNumberSubtype => __('Masukkan nilai angka pembanding.'),
            $isListOperator => __('Pisahkan beberapa nilai pembanding dengan tanda koma (,).'),
            $operator === 'contains' => __('Pencocokan teks tidak membedakan huruf besar dan kecil.'),
            $sourceField === null => __('Jenis input akan menyesuaikan tipe pertanyaan sumber yang dipilih.'),
            default => __('Membandingkan isi jawaban teks secara persis (spasi awal dan akhir pada nilai pembanding dirapikan otomatis).'),
        };

        $textMode = match (true) {
            $sourceType === DynamicFormFieldType::Date => 'date',
            $sourceType === DynamicFormFieldType::Time => 'time',
            $isNumberSubtype && $isListOperator => 'number_list',
            $isNumberSubtype => 'number',
            $isListOperator => 'text_list',
            $sourceField === null => 'unset',
            default => 'text',
        };

        $input = TextInput::make('value')
            ->key('value_text_'.$textMode)
            ->label(__('Nilai Pembanding'))
            ->required()
            ->placeholder($placeholder)
            ->helperText($helperText);

        if ($isNumberSubtype && ! $isListOperator) {
            $input->numeric();
        }

        return [$input];
    }
}
