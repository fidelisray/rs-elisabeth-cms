<?php

namespace App\Filament\Resources\DynamicForms\Schemas;

use App\Enums\DynamicFormFieldType;
use App\Enums\DynamicFormMode;
use App\Services\DynamicForm\DynamicFormLogicEvaluator;
use App\Services\DynamicForm\DynamicFormVersionService;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
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
            self::stepConditionalLogic(),
            self::stepScheduleAndPublication($isEdit),
        ];
    }

    private static function stepBasicInfo(): Step
    {
        return Step::make('Info Dasar & Mode')
            ->description('Identitas formulir, mode operasional, bahasa tunggal, dan awalan tiket.')
            ->schema([
                Section::make('Identitas Formulir')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label('Judul Formulir')
                            ->placeholder('Contoh: Survei Kepuasan Layanan Rawat Jalan')
                            ->required()
                            ->clearErrorOnInput()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $operation, ?string $state): void {
                                if (($operation === 'create' || empty($get('slug'))) && filled($state)) {
                                    $set('slug', Str::slug($state));
                                }
                            }),

                        TextInput::make('slug')
                            ->label('Slug URL Web')
                            ->placeholder('survei-kepuasan-rawat-jalan')
                            ->required()
                            ->clearErrorOnInput()
                            ->maxLength(255)
                            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                            ->unique(table: 'dynamic_forms', column: 'slug', ignoreRecord: true)
                            ->validationMessages([
                                'unique' => 'Slug formulir sudah digunakan. Gunakan slug lain.',
                                'regex' => 'Slug hanya boleh berisi huruf kecil, angka, dan tanda hubung (-).',
                            ])
                            ->helperText('Menjadi alamat publik /formulir/{slug} di Web Frontend.'),

                        Select::make('mode')
                            ->label('Mode Formulir')
                            ->options(DynamicFormMode::options())
                            ->default(DynamicFormMode::Questionnaire->value)
                            ->required()
                            ->live()
                            ->helperText('Mode Kritik & Saran otomatis menghasilkan nomor tiket tindak lanjut.'),

                        Select::make('locale')
                            ->label('Bahasa Formulir')
                            ->options([
                                'id' => 'Bahasa Indonesia (ID)',
                                'en' => 'English (EN)',
                            ])
                            ->default('id')
                            ->required()
                            ->helperText('Setiap formulir menggunakan satu bahasa. Gunakan aksi Duplikat Form untuk versi bahasa lain.'),

                        TextInput::make('ticket_prefix')
                            ->label('Awalan Nomor Tiket')
                            ->default('DFB')
                            ->maxLength(15)
                            ->regex('/^[A-Za-z0-9]+$/')
                            ->visible(fn (Get $get): bool => self::enumValue($get('mode')) === DynamicFormMode::Feedback->value)
                            ->helperText('Contoh: DFB akan menghasilkan tiket DFB-YYYYMMDD-XXXXXX.'),

                        Textarea::make('description')
                            ->label('Deskripsi Formulir (Opsional)')
                            ->rows(3)
                            ->maxLength(2000)
                            ->placeholder('Penjelasan singkat tujuan pengisian formulir untuk responden.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    private static function stepSectionsAndFields(): Step
    {
        return Step::make('Section & Pertanyaan')
            ->description('Kelompokkan pertanyaan ke dalam satu atau lebih bagian (section) dengan 12 pilihan tipe input.')
            ->afterValidation(function (Get $get): void {
                $sections = (array) ($get('sections') ?? []);
                $dupErrors = app(DynamicFormLogicEvaluator::class)->validateUniqueRawKeys($sections);

                if ($dupErrors !== []) {
                    Notification::make()
                        ->danger()
                        ->title('Terdapat Kunci Duplikat pada Formulir')
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
                    ->label('Daftar Bagian (Section)')
                    ->required()
                    ->minItems(1)
                    ->defaultItems(1)
                    ->reorderable()
                    ->collapsible()
                    ->cloneable()
                    ->itemLabel(fn (array $state): string => filled($state['title'] ?? null)
                        ? (string) $state['title']
                        : 'Bagian Baru')
                    ->schema([
                        TextInput::make('title')
                            ->label('Judul Bagian')
                            ->placeholder('Contoh: Data Kunjungan Pasien')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
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

                        TextInput::make('section_key')
                            ->label('Kunci Bagian (section_key)')
                            ->required()
                            ->default(fn (): string => 'sec_auto_'.Str::lower(Str::random(5)))
                            ->regex('/^[a-z0-9_]+$/')
                            ->maxLength(80)
                            ->helperText('Huruf kecil, angka, atau garis bawah (_). Dipakai untuk aturan lompat section.'),

                        Textarea::make('description')
                            ->label('Deskripsi Bagian (Opsional)')
                            ->rows(2)
                            ->maxLength(1500)
                            ->columnSpanFull(),

                        Repeater::make('fields')
                            ->label('Daftar Pertanyaan & Blok Informasi')
                            ->required()
                            ->minItems(1)
                            ->defaultItems(1)
                            ->reorderable()
                            ->collapsible()
                            ->cloneable()
                            ->columnSpanFull()
                            ->itemLabel(function (array $state): string {
                                $label = filled($state['label'] ?? null) ? (string) $state['label'] : 'Pertanyaan Baru';
                                $type = DynamicFormFieldType::tryFrom((string) ($state['type'] ?? ''));

                                return $type ? "{$label} ({$type->label()})" : $label;
                            })
                            ->schema([
                                Select::make('type')
                                    ->label('Tipe Input')
                                    ->options(DynamicFormFieldType::options())
                                    ->default(DynamicFormFieldType::ShortText->value)
                                    ->required()
                                    ->live(),

                                TextInput::make('label')
                                    ->label('Pertanyaan / Judul Blok')
                                    ->placeholder('Tuliskan pertanyaan atau judul informasi')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
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

                                TextInput::make('field_key')
                                    ->label('Kunci Field (field_key)')
                                    ->required()
                                    ->default(fn (): string => 'fld_auto_'.Str::lower(Str::random(5)))
                                    ->regex('/^[a-z0-9_]+$/')
                                    ->maxLength(80)
                                    ->helperText('Jaga agar stabil lintas versi untuk penyelarasan analitik.'),

                                Toggle::make('is_required')
                                    ->label('Wajib Diisi')
                                    ->default(false)
                                    ->visible(fn (Get $get): bool => self::fieldType($get) !== DynamicFormFieldType::InfoBlock),

                                TextInput::make('help_text')
                                    ->label('Teks Bantuan (Opsional)')
                                    ->maxLength(500)
                                    ->visible(fn (Get $get): bool => self::fieldType($get) !== DynamicFormFieldType::InfoBlock),

                                TextInput::make('placeholder')
                                    ->label('Placeholder Input (Opsional)')
                                    ->maxLength(255)
                                    ->visible(fn (Get $get): bool => in_array(self::fieldType($get), [
                                        DynamicFormFieldType::ShortText,
                                        DynamicFormFieldType::LongText,
                                        DynamicFormFieldType::Dropdown,
                                        DynamicFormFieldType::Date,
                                        DynamicFormFieldType::Time,
                                    ], true)),

                                // Konfigurasi Short Text
                                Select::make('options.subtype')
                                    ->label('Sub-Validasi Teks Singkat')
                                    ->options([
                                        'text' => 'Teks Bebas',
                                        'email' => 'Alamat Email',
                                        'phone' => 'Nomor Telepon',
                                        'number' => 'Angka / Numerik',
                                        'url' => 'Tautan URL (http/https)',
                                    ])
                                    ->default('text')
                                    ->live()
                                    ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::ShortText),

                                TextInput::make('validation_rules.min_length')
                                    ->label('Panjang Karakter Minimum')
                                    ->numeric()
                                    ->minValue(0)
                                    ->visible(fn (Get $get): bool => (
                                        self::fieldType($get) === DynamicFormFieldType::ShortText
                                        && ($get('options.subtype') ?? 'text') !== 'number'
                                    ) || self::fieldType($get) === DynamicFormFieldType::LongText),

                                TextInput::make('validation_rules.max_length')
                                    ->label('Panjang Karakter Maksimum')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(fn (Get $get): int => self::fieldType($get) === DynamicFormFieldType::LongText ? 5000 : 500)
                                    ->rule(fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                        $min = $get('validation_rules.min_length');
                                        if (is_numeric($value) && is_numeric($min) && (int) $value < (int) $min) {
                                            $fail('Panjang maksimum tidak boleh lebih kecil dari panjang minimum.');
                                        }
                                    })
                                    ->visible(fn (Get $get): bool => (
                                        self::fieldType($get) === DynamicFormFieldType::ShortText
                                        && ($get('options.subtype') ?? 'text') !== 'number'
                                    ) || self::fieldType($get) === DynamicFormFieldType::LongText),

                                TextInput::make('validation_rules.min_value')
                                    ->label('Nilai Angka Minimum')
                                    ->numeric()
                                    ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::ShortText
                                        && ($get('options.subtype') ?? 'text') === 'number'),

                                TextInput::make('validation_rules.max_value')
                                    ->label('Nilai Angka Maksimum')
                                    ->numeric()
                                    ->rule(fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                        $min = $get('validation_rules.min_value');
                                        if (is_numeric($value) && is_numeric($min) && (float) $value < (float) $min) {
                                            $fail('Nilai maksimum tidak boleh lebih kecil dari nilai minimum.');
                                        }
                                    })
                                    ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::ShortText
                                        && ($get('options.subtype') ?? 'text') === 'number'),

                                TextInput::make('validation_rules.pattern')
                                    ->label('Pola Regex Opsional')
                                    ->placeholder('Contoh: ^[A-Z0-9-]+$')
                                    ->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                                        if (! is_string($value) || trim($value) === '') {
                                            return;
                                        }
                                        $pattern = trim($value);
                                        $regex = str_starts_with($pattern, '/') ? $pattern : '/'.str_replace('/', '\/', $pattern).'/u';
                                        if (@preg_match($regex, '') === false) {
                                            $fail('Pola regex tidak valid dan gagal dikompilasi.');
                                        }
                                    })
                                    ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::ShortText
                                        && ($get('options.subtype') ?? 'text') === 'text'),

                                // Konfigurasi Pilihan (Radio, Checkbox, Dropdown)
                                Repeater::make('options.choices')
                                    ->label('Daftar Opsi Pilihan')
                                    ->columns(2)
                                    ->defaultItems(2)
                                    ->minItems(1)
                                    ->reorderable()
                                    ->columnSpanFull()
                                    ->visible(fn (Get $get): bool => self::fieldType($get)?->supportsChoices() ?? false)
                                    ->schema([
                                        TextInput::make('label')
                                            ->label('Label Pilihan')
                                            ->required()
                                            ->maxLength(255)
                                            ->live(onBlur: true)
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
                                        TextInput::make('key')
                                            ->label('Kunci Pilihan')
                                            ->required()
                                            ->default(fn (): string => 'opt_auto_'.Str::lower(Str::random(5)))
                                            ->regex('/^[a-z0-9_]+$/')
                                            ->maxLength(80),
                                    ]),

                                Toggle::make('options.allow_other')
                                    ->label('Sediakan Opsi "Lainnya"')
                                    ->default(false)
                                    ->live()
                                    ->visible(fn (Get $get): bool => in_array(self::fieldType($get), [
                                        DynamicFormFieldType::Radio,
                                        DynamicFormFieldType::Checkbox,
                                    ], true)),

                                TextInput::make('options.other_label')
                                    ->label('Label Opsi Lainnya')
                                    ->default('Lainnya')
                                    ->maxLength(100)
                                    ->visible(fn (Get $get): bool => in_array(self::fieldType($get), [
                                        DynamicFormFieldType::Radio,
                                        DynamicFormFieldType::Checkbox,
                                    ], true) && (bool) $get('options.allow_other')),

                                TextInput::make('validation_rules.min_selected')
                                    ->label('Minimal Pilihan Dicentang')
                                    ->numeric()
                                    ->minValue(0)
                                    ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::Checkbox),

                                TextInput::make('validation_rules.max_selected')
                                    ->label('Maksimal Pilihan Dicentang')
                                    ->numeric()
                                    ->minValue(1)
                                    ->rule(fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                        $min = $get('validation_rules.min_selected');
                                        if (is_numeric($value) && is_numeric($min) && (int) $value < (int) $min) {
                                            $fail('Maksimal pilihan tidak boleh lebih kecil dari minimal pilihan.');
                                        }
                                    })
                                    ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::Checkbox),

                                // Konfigurasi Linear Scale
                                Select::make('options.min_value')
                                    ->label('Nilai Awal Skala')
                                    ->options([0 => '0', 1 => '1'])
                                    ->default(1)
                                    ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::LinearScale),

                                Select::make('options.max_value')
                                    ->label('Nilai Maksimum Skala')
                                    ->options(array_combine(range(2, 10), array_map('strval', range(2, 10))))
                                    ->default(5)
                                    ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::LinearScale),

                                TextInput::make('options.min_label')
                                    ->label('Label Nilai Terendah (Opsional)')
                                    ->placeholder('Contoh: Sangat Tidak Puas')
                                    ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::LinearScale),

                                TextInput::make('options.max_label')
                                    ->label('Label Nilai Tertinggi (Opsional)')
                                    ->placeholder('Contoh: Sangat Puas')
                                    ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::LinearScale),

                                // Konfigurasi Rating
                                Select::make('options.max_stars')
                                    ->label('Jumlah Maksimal Ikon Penilaian')
                                    ->options(array_combine(range(3, 10), array_map('strval', range(3, 10))))
                                    ->default(5)
                                    ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::Rating),

                                Select::make('options.icon')
                                    ->label('Bentuk Ikon Penilaian')
                                    ->options([
                                        'star' => 'Bintang',
                                        'heart' => 'Hati',
                                        'thumb' => 'Jempol',
                                    ])
                                    ->default('star')
                                    ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::Rating),

                                // Konfigurasi Matrix Grid
                                Select::make('options.selection_mode')
                                    ->label('Mode Pilihan Matrix')
                                    ->options([
                                        'single' => 'Satu pilihan per baris',
                                        'multiple' => 'Banyak pilihan per baris (checkbox grid)',
                                    ])
                                    ->default('single')
                                    ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::MatrixGrid),

                                Toggle::make('options.require_all_rows')
                                    ->label('Wajib Isi Setiap Baris')
                                    ->default(false)
                                    ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::MatrixGrid),

                                Repeater::make('options.rows')
                                    ->label('Daftar Baris Matrix (Butir Pertanyaan)')
                                    ->columns(2)
                                    ->defaultItems(2)
                                    ->minItems(1)
                                    ->columnSpanFull()
                                    ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::MatrixGrid)
                                    ->schema([
                                        TextInput::make('label')
                                            ->label('Label Baris')
                                            ->required()
                                            ->maxLength(255)
                                            ->live(onBlur: true)
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
                                        TextInput::make('key')
                                            ->label('Kunci Baris')
                                            ->required()
                                            ->default(fn (): string => 'row_auto_'.Str::lower(Str::random(5)))
                                            ->regex('/^[a-z0-9_]+$/')
                                            ->maxLength(80),
                                    ]),

                                Repeater::make('options.columns')
                                    ->label('Daftar Kolom Matrix (Skala / Pilihan)')
                                    ->columns(3)
                                    ->defaultItems(3)
                                    ->minItems(1)
                                    ->columnSpanFull()
                                    ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::MatrixGrid)
                                    ->schema([
                                        TextInput::make('label')
                                            ->label('Label Kolom')
                                            ->required()
                                            ->maxLength(255)
                                            ->live(onBlur: true)
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
                                        TextInput::make('key')
                                            ->label('Kunci Kolom')
                                            ->required()
                                            ->default(fn (): string => 'col_auto_'.Str::lower(Str::random(5)))
                                            ->regex('/^[a-z0-9_]+$/')
                                            ->maxLength(80),
                                        TextInput::make('score')
                                            ->label('Skor Numerik (Opsional)')
                                            ->numeric(),
                                    ]),

                                // Konfigurasi Tanggal & Jam
                                TextInput::make('validation_rules.min_date')
                                    ->label('Batas Tanggal Awal (YYYY-MM-DD atau today)')
                                    ->placeholder('Contoh: 2026-01-01 atau today')
                                    ->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                                        if (! self::isValidDateBound($value)) {
                                            $fail('Format batas tanggal harus YYYY-MM-DD atau kata kunci today.');
                                        }
                                    })
                                    ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::Date),

                                TextInput::make('validation_rules.max_date')
                                    ->label('Batas Tanggal Akhir (YYYY-MM-DD atau today)')
                                    ->placeholder('Contoh: today')
                                    ->rule(fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                        if (! self::isValidDateBound($value)) {
                                            $fail('Format batas tanggal harus YYYY-MM-DD atau kata kunci today.');

                                            return;
                                        }
                                        $min = trim((string) ($get('validation_rules.min_date') ?? ''));
                                        $max = trim((string) $value);
                                        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $min) === 1 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $max) === 1 && $max < $min) {
                                            $fail('Batas tanggal akhir tidak boleh sebelum tanggal awal.');
                                        }
                                    })
                                    ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::Date),

                                TextInput::make('validation_rules.min_time')
                                    ->label('Batas Jam Awal (HH:MM)')
                                    ->placeholder('07:00')
                                    ->regex('/^([01]\d|2[0-3]):[0-5]\d$/')
                                    ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::Time),

                                TextInput::make('validation_rules.max_time')
                                    ->label('Batas Jam Akhir (HH:MM)')
                                    ->placeholder('21:00')
                                    ->regex('/^([01]\d|2[0-3]):[0-5]\d$/')
                                    ->rule(fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                        $min = trim((string) ($get('validation_rules.min_time') ?? ''));
                                        $max = trim((string) $value);
                                        if ($min !== '' && $max !== '' && $max < $min) {
                                            $fail('Batas jam akhir tidak boleh sebelum jam awal.');
                                        }
                                    })
                                    ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::Time),

                                // Konfigurasi File Upload
                                Select::make('options.allowed_extensions')
                                    ->label('Ekstensi Berkas yang Diizinkan')
                                    ->multiple()
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
                                        'txt' => 'Teks (.txt)',
                                    ])
                                    ->default(['pdf', 'jpg', 'jpeg', 'png'])
                                    ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::FileUpload),

                                TextInput::make('options.max_file_size_mb')
                                    ->label('Ukuran Maksimal per Berkas (MB)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(10)
                                    ->default(5)
                                    ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::FileUpload),

                                TextInput::make('options.max_files')
                                    ->label('Jumlah Maksimal Berkas')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(5)
                                    ->default(1)
                                    ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::FileUpload),

                                // Konfigurasi Info Block
                                Textarea::make('options.content')
                                    ->label('Isi Konten Informasi')
                                    ->rows(4)
                                    ->columnSpanFull()
                                    ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::InfoBlock),

                                TextInput::make('options.media_url')
                                    ->label('Tautan Media / Banner Opsional')
                                    ->url()
                                    ->columnSpanFull()
                                    ->visible(fn (Get $get): bool => self::fieldType($get) === DynamicFormFieldType::InfoBlock),
                            ])
                            ->columns(2),
                    ]),
            ]);
    }

    private static function stepConditionalLogic(): Step
    {
        return Step::make('Percabangan Logika')
            ->description('Atur tampilan bersyarat antar-pertanyaan dan lompatan antar-bagian secara deklaratif.')
            ->afterValidation(function (Get $get): void {
                $sections = (array) ($get('sections') ?? []);
                $logicConfig = (array) ($get('logic_config') ?? []);
                $evaluator = app(DynamicFormLogicEvaluator::class);

                $dupErrors = $evaluator->validateUniqueRawKeys($sections);
                if ($dupErrors !== []) {
                    Notification::make()
                        ->danger()
                        ->title('Terdapat Kunci Duplikat pada Formulir')
                        ->body(implode(' ', $dupErrors))
                        ->send();

                    throw ValidationException::withMessages([
                        'data.sections' => $dupErrors,
                        'sections' => $dupErrors,
                    ]);
                }

                $merged = app(DynamicFormVersionService::class)->mergeLogicConfigIntoSections($sections, $logicConfig);
                $errors = $evaluator->validateSchemaLogic($merged);

                if ($errors !== []) {
                    Notification::make()
                        ->danger()
                        ->title('Aturan Percabangan Logika Tidak Valid')
                        ->body(implode(' ', $errors))
                        ->send();

                    throw ValidationException::withMessages([
                        'data.logic_config.visibility_rules' => $errors,
                        'logic_config' => $errors,
                    ]);
                }
            })
            ->schema([
                Section::make('1. Aturan Tampil/Sembunyi Pertanyaan (Visibility)')
                    ->description('Tampilkan pertanyaan tertentu hanya jika jawaban pertanyaan sebelumnya memenuhi kondisi.')
                    ->schema([
                        Repeater::make('logic_config.visibility_rules')
                            ->label('Daftar Aturan Visibilitas')
                            ->defaultItems(0)
                            ->collapsible()
                            ->addActionLabel('Tambah Aturan Visibilitas')
                            ->schema([
                                Select::make('target_field_key')
                                    ->label('Pertanyaan Target')
                                    ->options(fn (Get $get, Component $component): array => self::fieldSelectOptions(
                                        self::sectionsState($get, $component),
                                        includeInfoBlocks: true
                                    ))
                                    ->required()
                                    ->searchable(),

                                Select::make('match')
                                    ->label('Kombinasi Syarat')
                                    ->options([
                                        'all' => 'Semua kondisi harus terpenuhi (AND)',
                                        'any' => 'Salah satu kondisi terpenuhi (OR)',
                                    ])
                                    ->default('all')
                                    ->required(),

                                Repeater::make('rules')
                                    ->label('Kondisi Pemicu')
                                    ->minItems(1)
                                    ->defaultItems(1)
                                    ->columns(3)
                                    ->columnSpanFull()
                                    ->schema([
                                        Select::make('source_field_key')
                                            ->label('Berdasarkan Jawaban Pertanyaan')
                                            ->options(fn (Get $get, Component $component): array => self::fieldSelectOptions(
                                                self::sectionsState($get, $component),
                                                includeInfoBlocks: false
                                            ))
                                            ->required()
                                            ->searchable(),

                                        Select::make('operator')
                                            ->label('Operator')
                                            ->options(DynamicFormLogicEvaluator::operatorOptions())
                                            ->default('equals')
                                            ->required()
                                            ->live(),

                                        TextInput::make('value')
                                            ->label('Nilai Pembanding')
                                            ->placeholder('Kunci opsi atau teks/angka')
                                            ->hidden(fn (Get $get): bool => in_array((string) $get('operator'), ['is_empty', 'is_not_empty'], true)),
                                    ]),
                            ])
                            ->columns(2),
                    ]),

                Section::make('2. Lompatan Bagian Berdasarkan Jawaban (Radio / Dropdown)')
                    ->description('Arahkan responden ke bagian tertentu atau langsung kirim formulir berdasarkan opsi yang dipilih.')
                    ->schema([
                        Repeater::make('logic_config.choice_jumps')
                            ->label('Daftar Lompatan Pilihan')
                            ->defaultItems(0)
                            ->columns(3)
                            ->addActionLabel('Tambah Lompatan Pilihan')
                            ->schema([
                                Select::make('source_field_key')
                                    ->label('Pertanyaan Sumber (Radio/Dropdown)')
                                    ->options(fn (Get $get, Component $component): array => self::jumpableFieldOptions(
                                        self::sectionsState($get, $component)
                                    ))
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set) => $set('choice_key', null)),

                                Select::make('choice_key')
                                    ->label('Saat Memilih Opsi')
                                    ->options(fn (Get $get, Component $component): array => self::choiceOptionsForField(
                                        self::sectionsState($get, $component),
                                        (string) ($get('source_field_key') ?? '')
                                    ))
                                    ->required(),

                                Select::make('go_to_section_key')
                                    ->label('Lompat ke Bagian')
                                    ->options(fn (Get $get, Component $component): array => self::sectionTargetOptions(
                                        self::sectionsState($get, $component)
                                    ))
                                    ->required(),
                            ]),
                    ]),

                Section::make('3. Aturan Lanjut di Akhir Bagian')
                    ->description('Atur tujuan default setelah responden menyelesaikan sebuah bagian (jika tidak ada lompatan opsi).')
                    ->schema([
                        Repeater::make('logic_config.section_jumps')
                            ->label('Aturan Transisi Bagian')
                            ->defaultItems(0)
                            ->columns(3)
                            ->addActionLabel('Tambah Aturan Akhir Bagian')
                            ->schema([
                                Select::make('section_key')
                                    ->label('Setelah Bagian Ini')
                                    ->options(fn (Get $get, Component $component): array => self::sectionOptions(
                                        self::sectionsState($get, $component)
                                    ))
                                    ->required(),

                                Select::make('action')
                                    ->label('Tindakan Berikutnya')
                                    ->options([
                                        'next' => 'Lanjut ke bagian berikutnya',
                                        'jump' => 'Lompat ke bagian tertentu',
                                        'submit' => 'Langsung kirim formulir',
                                    ])
                                    ->default('next')
                                    ->required()
                                    ->live(),

                                Select::make('target_section_key')
                                    ->label('Bagian Tujuan')
                                    ->options(fn (Get $get, Component $component): array => self::sectionTargetOptions(
                                        self::sectionsState($get, $component)
                                    ))
                                    ->visible(fn (Get $get): bool => $get('action') === 'jump')
                                    ->required(fn (Get $get): bool => $get('action') === 'jump'),
                            ]),
                    ]),
            ]);
    }

    private static function stepScheduleAndPublication(bool $isEdit): Step
    {
        return Step::make('Jadwal, Kuota & Publikasi')
            ->description('Tentukan jadwal buka-tutup, kuota maksimal, pesan kustom, dan status publikasi.')
            ->schema([
                Section::make('Jadwal & Batasan Pengisian')
                    ->columns(2)
                    ->schema([
                        DateTimePicker::make('starts_at')
                            ->label('Waktu Mulai Pengisian (Opsional)')
                            ->seconds(false),

                        DateTimePicker::make('ends_at')
                            ->label('Waktu Berakhir Pengisian (Opsional)')
                            ->seconds(false)
                            ->afterOrEqual('starts_at'),

                        TextInput::make('max_responses')
                            ->label('Kuota Maksimal Respons (Opsional)')
                            ->numeric()
                            ->minValue(1)
                            ->helperText('Kosongkan jika jumlah respons tidak dibatasi.'),

                        Toggle::make('allow_multiple_submissions')
                            ->label('Izinkan pengisian berulang oleh responden yang sama')
                            ->default(true)
                            ->helperText('Jika dimatikan, backend memvalidasi respondent_key agar tidak ganda.'),
                    ]),

                Section::make('Pesan Konfirmasi & Penutupan')
                    ->columns(1)
                    ->schema([
                        Textarea::make('confirmation_message')
                            ->label('Pesan Konfirmasi Setelah Mengirim (Opsional)')
                            ->rows(2)
                            ->placeholder('Terima kasih. Jawaban Anda telah berhasil direkam.'),

                        Textarea::make('closed_message')
                            ->label('Pesan Saat Formulir Ditutup / Kuota Penuh (Opsional)')
                            ->rows(2)
                            ->placeholder('Mohon maaf, periode pengisian formulir ini sudah berakhir.'),
                    ]),

                Section::make('Ringkasan Kesiapan Publikasi')
                    ->schema([
                        Placeholder::make('readiness_summary')
                            ->label('Ringkasan Konfigurasi Saat Ini')
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

                                return sprintf(
                                    'Mode: %s | Bagian: %d | Pertanyaan Input: %d | Aturan Logika: %d',
                                    $modeLabel,
                                    count($sections),
                                    $fieldCount,
                                    $ruleCount
                                );
                            }),

                        Toggle::make('is_public_listed')
                            ->label('Tampilkan di Katalog Formulir Publik (/formulir)')
                            ->default(false)
                            ->helperText('Jika dimatikan, formulir hanya dapat diakses via tautan langsung atau QR Code dan tidak diindeks mesin pencari.'),

                        Toggle::make('publish_now')
                            ->label($isEdit
                                ? 'Publikasikan draf versi ini segera setelah disimpan'
                                : 'Publikasikan formulir langsung setelah disimpan')
                            ->default(false)
                            ->helperText('Jika dimatikan, perubahan tersimpan sebagai draf tanpa mengubah versi yang sedang aktif di API.'),
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
        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }
            $key = (string) ($section['section_key'] ?? '');
            if ($key === '') {
                continue;
            }
            $title = (string) ($section['title'] ?? $key);
            $out[$key] = "{$title} ({$key})";
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
        $options[DynamicFormLogicEvaluator::SUBMIT_SENTINEL] = 'Kirim Formulir (Selesai)';

        return $options;
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
        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }
            $sectionTitle = (string) ($section['title'] ?? 'Bagian');
            foreach ((array) ($section['fields'] ?? []) as $field) {
                if (! is_array($field)) {
                    continue;
                }
                $key = (string) ($field['field_key'] ?? '');
                $isInfoBlock = ($field['type'] ?? '') === DynamicFormFieldType::InfoBlock->value;
                if ($key === '' || (! $includeInfoBlocks && $isInfoBlock)) {
                    continue;
                }
                $label = (string) ($field['label'] ?? $key);
                $out[$key] = "[{$sectionTitle}] {$label} ({$key})";
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
        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }
            foreach ((array) ($section['fields'] ?? []) as $field) {
                if (! is_array($field)) {
                    continue;
                }
                $type = (string) ($field['type'] ?? '');
                if (! in_array($type, [DynamicFormFieldType::Radio->value, DynamicFormFieldType::Dropdown->value], true)) {
                    continue;
                }
                $key = (string) ($field['field_key'] ?? '');
                if ($key !== '') {
                    $out[$key] = (string) ($field['label'] ?? $key)." ({$key})";
                }
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
                $choices = [];
                foreach ((array) ($field['options']['choices'] ?? []) as $choice) {
                    if (! is_array($choice)) {
                        continue;
                    }
                    $k = (string) ($choice['key'] ?? '');
                    if ($k !== '') {
                        $label = filled($choice['label'] ?? null) ? (string) $choice['label'] : $k;
                        $choices[$k] = "{$label} ({$k})";
                    }
                }

                return $choices;
            }
        }

        return [];
    }
}
