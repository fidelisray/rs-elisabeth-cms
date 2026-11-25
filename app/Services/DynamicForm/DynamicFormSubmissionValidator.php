<?php

namespace App\Services\DynamicForm;

use App\Enums\DynamicFormFieldType;
use App\Enums\DynamicFormTextFormatPreset;
use App\Models\DynamicForm;
use App\Models\DynamicFormUpload;
use DateTimeImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DynamicFormSubmissionValidator
{
    public function __construct(
        private readonly DynamicFormLogicEvaluator $logicEvaluator,
    ) {}

    /**
     * Validasi jawaban terhadap schema_snapshot dan aturan percabangan.
     *
     * @param  array<string, mixed>  $schemaSnapshot
     * @param  array<string, mixed>  $rawAnswers
     * @return array{
     *     answers: array<string, mixed>,
     *     upload_ids: list<int>,
     *     active_section_keys: list<string>
     * }
     *
     * @throws ValidationException
     */
    public function validate(DynamicForm $form, array $schemaSnapshot, array $rawAnswers): array
    {
        $logicResult = $this->logicEvaluator->evaluate($schemaSnapshot, $rawAnswers);

        if ($logicResult['errors'] !== []) {
            throw ValidationException::withMessages([
                'answers' => $logicResult['errors'],
            ]);
        }

        $errors = [];
        $normalizedAnswers = [];
        $verifiedUploadIds = [];
        $usedUploadTokens = [];

        foreach ($logicResult['visible_fields'] as $field) {
            $fieldKey = (string) ($field['field_key'] ?? '');
            $type = DynamicFormFieldType::tryFrom((string) ($field['type'] ?? ''));

            if (! $type || ! $type->isInput()) {
                continue;
            }

            $label = (string) ($field['label'] ?? $fieldKey);
            $isRequired = (bool) ($field['is_required'] ?? false);
            $hasValue = array_key_exists($fieldKey, $logicResult['sanitized_answers']);
            $rawValue = $hasValue ? $logicResult['sanitized_answers'][$fieldKey] : null;

            $fieldErrors = [];
            $normalized = $this->validateField(
                $form,
                $field,
                $type,
                $label,
                $isRequired,
                $rawValue,
                $fieldErrors,
                $verifiedUploadIds,
                $usedUploadTokens,
            );

            if ($fieldErrors !== []) {
                $errors["answers.{$fieldKey}"] = $fieldErrors;

                continue;
            }

            if ($normalized !== null) {
                $normalizedAnswers[$fieldKey] = $normalized;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'answers' => $normalizedAnswers,
            'upload_ids' => $verifiedUploadIds,
            'active_section_keys' => $logicResult['active_section_keys'],
        ];
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  list<string>  $errors
     * @param  list<int>  $verifiedUploadIds
     * @param  array<string, bool>  $usedUploadTokens
     */
    private function validateField(
        DynamicForm $form,
        array $field,
        DynamicFormFieldType $type,
        string $label,
        bool $isRequired,
        mixed $rawValue,
        array &$errors,
        array &$verifiedUploadIds,
        array &$usedUploadTokens,
    ): mixed {
        $options = (array) ($field['options'] ?? []);
        $rules = (array) ($field['validation_rules'] ?? []);

        return match ($type) {
            DynamicFormFieldType::ShortText => $this->validateShortText($label, $isRequired, $rawValue, $options, $rules, $errors),
            DynamicFormFieldType::LongText => $this->validateLongText($label, $isRequired, $rawValue, $rules, $errors),
            DynamicFormFieldType::Radio => $this->validateRadio($label, $isRequired, $rawValue, $options, $errors),
            DynamicFormFieldType::Checkbox => $this->validateCheckbox($label, $isRequired, $rawValue, $options, $rules, $errors),
            DynamicFormFieldType::Dropdown => $this->validateDropdown($label, $isRequired, $rawValue, $options, $errors),
            DynamicFormFieldType::LinearScale => $this->validateLinearScale($label, $isRequired, $rawValue, $options, $errors),
            DynamicFormFieldType::Rating => $this->validateRating($label, $isRequired, $rawValue, $options, $errors),
            DynamicFormFieldType::MatrixGrid => $this->validateMatrixGrid($label, $isRequired, $rawValue, $options, $errors),
            DynamicFormFieldType::Date => $this->validateDate($label, $isRequired, $rawValue, $rules, $errors),
            DynamicFormFieldType::Time => $this->validateTime($label, $isRequired, $rawValue, $rules, $errors),
            DynamicFormFieldType::FileUpload => $this->validateFileUpload(
                $form,
                (string) $field['field_key'],
                $label,
                $isRequired,
                $rawValue,
                $options,
                $errors,
                $verifiedUploadIds,
                $usedUploadTokens,
            ),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $rules
     * @param  list<string>  $errors
     */
    private function validateShortText(string $label, bool $isRequired, mixed $rawValue, array $options, array $rules, array &$errors): mixed
    {
        if (is_array($rawValue) || is_object($rawValue) || is_bool($rawValue)) {
            $errors[] = "Format jawaban pada [{$label}] tidak valid.";

            return null;
        }

        $trimmed = $rawValue === null ? '' : trim((string) $rawValue);
        if ($trimmed === '') {
            if ($isRequired) {
                $errors[] = "Pertanyaan [{$label}] wajib diisi.";
            }

            return null;
        }

        $subtype = (string) ($options['subtype'] ?? 'text');

        if ($subtype === 'number') {
            if (! is_numeric($trimmed)) {
                $errors[] = "Pertanyaan [{$label}] harus berupa angka.";

                return null;
            }
            $num = str_contains($trimmed, '.') ? (float) $trimmed : (int) $trimmed;

            if (isset($rules['min_value']) && is_numeric($rules['min_value']) && $num < (float) $rules['min_value']) {
                $errors[] = "Nilai pada [{$label}] minimal {$rules['min_value']}.";
            }
            if (isset($rules['max_value']) && is_numeric($rules['max_value']) && $num > (float) $rules['max_value']) {
                $errors[] = "Nilai pada [{$label}] maksimal {$rules['max_value']}.";
            }

            return $num;
        }

        if ($subtype === 'email' && filter_var($trimmed, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = "Format email pada [{$label}] tidak valid.";
        }

        if ($subtype === 'url') {
            $validUrl = filter_var($trimmed, FILTER_VALIDATE_URL);
            $scheme = $validUrl ? strtolower((string) parse_url($trimmed, PHP_URL_SCHEME)) : '';
            if ($validUrl === false || ! in_array($scheme, ['http', 'https'], true)) {
                $errors[] = "Format URL pada [{$label}] harus diawali http:// atau https://.";
            }
        }

        if ($subtype === 'phone') {
            if (! self::isValidIndonesianPhone($trimmed)) {
                $errors[] = "Format nomor telepon Indonesia pada [{$label}] tidak valid. Gunakan awalan 08 atau +628 (10-13 digit angka).";
            }
        }

        $len = mb_strlen($trimmed);
        $minLength = isset($rules['min_length']) ? (int) $rules['min_length'] : null;
        $maxLength = isset($rules['max_length']) ? min(500, (int) $rules['max_length']) : 500;

        if ($subtype !== 'phone') {
            if ($minLength !== null && $len < $minLength) {
                $errors[] = "Jawaban [{$label}] minimal {$minLength} karakter.";
            }
            if ($len > $maxLength) {
                $errors[] = "Jawaban [{$label}] maksimal {$maxLength} karakter.";
            }
        }

        $preset = $subtype === 'text' && filled($rules['format_preset'] ?? null)
            ? DynamicFormTextFormatPreset::tryFrom((string) $rules['format_preset'])
            : null;

        if ($preset !== null) {
            if (! $preset->matches($trimmed)) {
                $errors[] = $preset->errorMessage($label);
            }
        } elseif (filled($rules['pattern'] ?? null)) {
            $pattern = (string) $rules['pattern'];
            $regex = str_starts_with($pattern, '/') ? $pattern : '/'.str_replace('/', '\/', $pattern).'/u';
            if (@preg_match($regex, $trimmed) !== 1) {
                $errors[] = "Format jawaban pada [{$label}] tidak sesuai pola yang diminta.";
            }
        }

        return $trimmed;
    }

    /**
     * Validasi nomor telepon seluler Indonesia: awalan 08, +628, atau 628 (digit ke-3 bernilai 1-9),
     * setara 10-13 digit lokal dengan toleransi pemisah spasi, tanda hubung (-), atau titik (.).
     */
    public static function isValidIndonesianPhone(string $value): bool
    {
        $trimmed = trim($value);
        if ($trimmed === '' || preg_match('/^\+?[0-9\s\-.]+$/', $trimmed) !== 1) {
            return false;
        }

        $compact = preg_replace('/[\s\-.]/', '', $trimmed) ?? '';

        return preg_match('/^(?:\+62|62|0)8[1-9][0-9]{7,10}$/', $compact) === 1;
    }

    /**
     * @param  array<string, mixed>  $rules
     * @param  list<string>  $errors
     */
    private function validateLongText(string $label, bool $isRequired, mixed $rawValue, array $rules, array &$errors): ?string
    {
        if (is_array($rawValue) || is_object($rawValue) || is_bool($rawValue)) {
            $errors[] = "Format jawaban pada [{$label}] tidak valid.";

            return null;
        }

        $trimmed = $rawValue === null ? '' : trim((string) $rawValue);
        if ($trimmed === '') {
            if ($isRequired) {
                $errors[] = "Pertanyaan [{$label}] wajib diisi.";
            }

            return null;
        }

        $len = mb_strlen($trimmed);
        $minLength = isset($rules['min_length']) ? (int) $rules['min_length'] : null;
        $maxLength = isset($rules['max_length']) ? min(5000, (int) $rules['max_length']) : 5000;

        if ($minLength !== null && $len < $minLength) {
            $errors[] = "Jawaban [{$label}] minimal {$minLength} karakter.";
        }
        if ($len > $maxLength) {
            $errors[] = "Jawaban [{$label}] maksimal {$maxLength} karakter.";
        }

        return $trimmed;
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  list<string>  $errors
     * @return array{selected: string, other_text?: string}|null
     */
    private function validateRadio(string $label, bool $isRequired, mixed $rawValue, array $options, array &$errors): ?array
    {
        $selected = null;
        $otherText = null;

        if (is_array($rawValue)) {
            $selected = isset($rawValue['selected']) ? trim((string) $rawValue['selected']) : null;
            $otherText = isset($rawValue['other_text']) ? trim((string) $rawValue['other_text']) : null;
        } elseif (is_scalar($rawValue) && ! is_bool($rawValue)) {
            $selected = trim((string) $rawValue);
        } elseif ($rawValue !== null) {
            $errors[] = "Format pilihan pada [{$label}] tidak valid.";

            return null;
        }

        if ($selected === null || $selected === '') {
            if ($isRequired) {
                $errors[] = "Pertanyaan [{$label}] wajib dipilih.";
            }

            return null;
        }

        $validKeys = collect((array) ($options['choices'] ?? []))->pluck('key')->map('strval')->all();
        $allowOther = (bool) ($options['allow_other'] ?? false);

        if ($selected === DynamicFormLogicEvaluator::OTHER_CHOICE_KEY) {
            if (! $allowOther) {
                $errors[] = "Opsi Lainnya tidak aktif pada pertanyaan [{$label}].";

                return null;
            }
            if ($otherText === null || $otherText === '') {
                $errors[] = "Isian teks untuk opsi Lainnya pada [{$label}] wajib diisi.";

                return null;
            }
            if (mb_strlen($otherText) > 500) {
                $errors[] = "Isian teks Lainnya pada [{$label}] maksimal 500 karakter.";

                return null;
            }

            return [
                'selected' => DynamicFormLogicEvaluator::OTHER_CHOICE_KEY,
                'other_text' => $otherText,
            ];
        }

        if (! in_array($selected, $validKeys, true)) {
            $errors[] = "Pilihan pada [{$label}] tidak valid.";

            return null;
        }

        return ['selected' => $selected];
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $rules
     * @param  list<string>  $errors
     * @return array{selected: list<string>, other_text?: string}|null
     */
    private function validateCheckbox(string $label, bool $isRequired, mixed $rawValue, array $options, array $rules, array &$errors): ?array
    {
        if ($rawValue === null) {
            if ($isRequired) {
                $errors[] = "Pertanyaan [{$label}] wajib dipilih minimal 1 opsi.";
            }

            return null;
        }

        if (! is_array($rawValue)) {
            $errors[] = "Format kotak centang pada [{$label}] harus berupa daftar pilihan.";

            return null;
        }

        if (array_key_exists('selected', $rawValue)) {
            if (! is_array($rawValue['selected'])) {
                $errors[] = "Format pilihan kotak centang pada [{$label}] tidak valid.";

                return null;
            }
            $rawSelected = $rawValue['selected'];
            $otherText = isset($rawValue['other_text']) ? trim((string) $rawValue['other_text']) : null;
        } else {
            $rawSelected = $rawValue;
            $otherText = null;
        }

        $selected = array_values(array_unique(array_filter(
            array_map(fn ($item) => is_scalar($item) && ! is_bool($item) ? trim((string) $item) : '', $rawSelected),
            fn (string $v): bool => $v !== ''
        )));

        if ($selected === []) {
            if ($isRequired) {
                $errors[] = "Pertanyaan [{$label}] wajib dipilih minimal 1 opsi.";
            }

            return null;
        }

        $validKeys = collect((array) ($options['choices'] ?? []))->pluck('key')->map('strval')->all();
        $allowOther = (bool) ($options['allow_other'] ?? false);

        foreach ($selected as $key) {
            if ($key === DynamicFormLogicEvaluator::OTHER_CHOICE_KEY) {
                if (! $allowOther) {
                    $errors[] = "Opsi Lainnya tidak diizinkan pada [{$label}].";
                } elseif ($otherText === null || $otherText === '') {
                    $errors[] = "Isian teks untuk opsi Lainnya pada [{$label}] wajib diisi.";
                } elseif (mb_strlen($otherText) > 500) {
                    $errors[] = "Isian teks Lainnya pada [{$label}] maksimal 500 karakter.";
                }

                continue;
            }

            if (! in_array($key, $validKeys, true)) {
                $errors[] = "Pilihan [{$key}] pada [{$label}] tidak valid.";
            }
        }

        $count = count($selected);
        $minSelected = isset($rules['min_selected']) ? (int) $rules['min_selected'] : null;
        $maxSelected = isset($rules['max_selected']) ? (int) $rules['max_selected'] : null;

        if ($minSelected !== null && $count < $minSelected) {
            $errors[] = "Pertanyaan [{$label}] membutuhkan minimal {$minSelected} pilihan.";
        }
        if ($maxSelected !== null && $count > $maxSelected) {
            $errors[] = "Pertanyaan [{$label}] maksimal {$maxSelected} pilihan.";
        }

        $result = ['selected' => $selected];
        if (in_array(DynamicFormLogicEvaluator::OTHER_CHOICE_KEY, $selected, true) && $otherText !== null && $otherText !== '') {
            $result['other_text'] = $otherText;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  list<string>  $errors
     */
    private function validateDropdown(string $label, bool $isRequired, mixed $rawValue, array $options, array &$errors): ?string
    {
        if (is_array($rawValue) || is_object($rawValue) || is_bool($rawValue)) {
            $errors[] = "Format pilihan pada [{$label}] tidak valid.";

            return null;
        }

        $selected = $rawValue === null ? '' : trim((string) $rawValue);
        if ($selected === '') {
            if ($isRequired) {
                $errors[] = "Pertanyaan [{$label}] wajib dipilih.";
            }

            return null;
        }

        $validKeys = collect((array) ($options['choices'] ?? []))->pluck('key')->map('strval')->all();
        if (! in_array($selected, $validKeys, true)) {
            $errors[] = "Pilihan pada [{$label}] tidak valid.";

            return null;
        }

        return $selected;
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  list<string>  $errors
     */
    private function validateLinearScale(string $label, bool $isRequired, mixed $rawValue, array $options, array &$errors): ?int
    {
        if ($rawValue === null || $rawValue === '') {
            if ($isRequired) {
                $errors[] = "Skala pada [{$label}] wajib dipilih.";
            }

            return null;
        }

        if (is_bool($rawValue) || is_array($rawValue) || filter_var($rawValue, FILTER_VALIDATE_INT) === false) {
            $errors[] = "Nilai skala pada [{$label}] harus berupa bilangan bulat.";

            return null;
        }

        $intVal = (int) $rawValue;
        $minVal = (int) ($options['min_value'] ?? 1);
        $maxVal = (int) ($options['max_value'] ?? 5);

        if ($intVal < $minVal || $intVal > $maxVal) {
            $errors[] = "Nilai skala pada [{$label}] harus antara {$minVal} hingga {$maxVal}.";

            return null;
        }

        return $intVal;
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  list<string>  $errors
     */
    private function validateRating(string $label, bool $isRequired, mixed $rawValue, array $options, array &$errors): ?int
    {
        if ($rawValue === null || $rawValue === '') {
            if ($isRequired) {
                $errors[] = "Penilaian pada [{$label}] wajib dipilih.";
            }

            return null;
        }

        if (is_bool($rawValue) || is_array($rawValue) || filter_var($rawValue, FILTER_VALIDATE_INT) === false) {
            $errors[] = "Nilai penilaian pada [{$label}] harus berupa bilangan bulat.";

            return null;
        }

        $intVal = (int) $rawValue;
        $maxStars = (int) ($options['max_stars'] ?? 5);

        if ($intVal < 1 || $intVal > $maxStars) {
            $errors[] = "Penilaian pada [{$label}] harus antara 1 hingga {$maxStars}.";

            return null;
        }

        return $intVal;
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  list<string>  $errors
     * @return array<string, mixed>|null
     */
    private function validateMatrixGrid(string $label, bool $isRequired, mixed $rawValue, array $options, array &$errors): ?array
    {
        if ($rawValue === null || $rawValue === []) {
            if ($isRequired) {
                $errors[] = "Kisi pertanyaan [{$label}] wajib diisi.";
            }

            return null;
        }

        if (! is_array($rawValue)) {
            $errors[] = "Format kisi pertanyaan [{$label}] tidak valid.";

            return null;
        }

        $selectionMode = ($options['selection_mode'] ?? 'single') === 'multiple' ? 'multiple' : 'single';
        $rowKeys = collect((array) ($options['rows'] ?? []))->pluck('key')->map('strval')->all();
        $colKeys = collect((array) ($options['columns'] ?? []))->pluck('key')->map('strval')->all();
        $requireAllRows = (bool) ($options['require_all_rows'] ?? false);

        foreach ($rawValue as $submittedRowKey => $_) {
            if (! in_array((string) $submittedRowKey, $rowKeys, true)) {
                $errors[] = "Baris [{$submittedRowKey}] pada [{$label}] tidak valid.";
            }
        }

        $normalized = [];
        foreach ($rowKeys as $rowKey) {
            if (! array_key_exists($rowKey, $rawValue) || $rawValue[$rowKey] === null || $rawValue[$rowKey] === '' || $rawValue[$rowKey] === []) {
                continue;
            }

            $cell = $rawValue[$rowKey];
            if ($selectionMode === 'single') {
                if (is_array($cell) || is_bool($cell)) {
                    $errors[] = "Baris [{$rowKey}] pada [{$label}] hanya mengizinkan satu pilihan.";

                    continue;
                }
                $col = trim((string) $cell);
                if (! in_array($col, $colKeys, true)) {
                    $errors[] = "Pilihan kolom pada baris [{$rowKey}] pertanyaan [{$label}] tidak valid.";

                    continue;
                }
                $normalized[$rowKey] = $col;
            } else {
                if (! is_array($cell)) {
                    $errors[] = "Format pilihan ganda pada baris [{$rowKey}] pertanyaan [{$label}] tidak valid.";

                    continue;
                }
                $selectedCols = array_values(array_unique(array_filter(
                    array_map(fn ($v) => is_scalar($v) && ! is_bool($v) ? trim((string) $v) : '', $cell),
                    fn (string $v): bool => $v !== ''
                )));
                foreach ($selectedCols as $col) {
                    if (! in_array($col, $colKeys, true)) {
                        $errors[] = "Pilihan kolom [{$col}] pada baris [{$rowKey}] pertanyaan [{$label}] tidak valid.";
                    }
                }
                if ($selectedCols !== []) {
                    $normalized[$rowKey] = $selectedCols;
                }
            }
        }

        if ($isRequired && $normalized === []) {
            $errors[] = "Kisi pertanyaan [{$label}] wajib diisi.";
        } elseif (($isRequired || $normalized !== []) && $requireAllRows && count($normalized) < count($rowKeys)) {
            $errors[] = "Seluruh baris pada kisi pertanyaan [{$label}] wajib diisi.";
        }

        return $normalized !== [] ? $normalized : null;
    }

    /**
     * @param  array<string, mixed>  $rules
     * @param  list<string>  $errors
     */
    private function validateDate(string $label, bool $isRequired, mixed $rawValue, array $rules, array &$errors): ?string
    {
        if ($rawValue === null || $rawValue === '') {
            if ($isRequired) {
                $errors[] = "Tanggal pada [{$label}] wajib diisi.";
            }

            return null;
        }

        if (! is_string($rawValue)) {
            $errors[] = "Format tanggal pada [{$label}] tidak valid.";

            return null;
        }

        $value = trim($rawValue);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (! $parsed || $parsed->format('Y-m-d') !== $value) {
            $errors[] = "Format tanggal pada [{$label}] harus YYYY-MM-DD.";

            return null;
        }

        $minDate = $this->resolveBoundDate($rules['min_date'] ?? null);
        $maxDate = $this->resolveBoundDate($rules['max_date'] ?? null);

        if ($minDate !== null && $value < $minDate) {
            $errors[] = "Tanggal pada [{$label}] tidak boleh sebelum {$minDate}.";
        }
        if ($maxDate !== null && $value > $maxDate) {
            $errors[] = "Tanggal pada [{$label}] tidak boleh setelah {$maxDate}.";
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $rules
     * @param  list<string>  $errors
     */
    private function validateTime(string $label, bool $isRequired, mixed $rawValue, array $rules, array &$errors): ?string
    {
        if ($rawValue === null || $rawValue === '') {
            if ($isRequired) {
                $errors[] = "Waktu pada [{$label}] wajib diisi.";
            }

            return null;
        }

        if (! is_string($rawValue)) {
            $errors[] = "Format waktu pada [{$label}] tidak valid.";

            return null;
        }

        $value = trim($rawValue);
        if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value)) {
            $errors[] = "Format waktu pada [{$label}] harus HH:MM (24 jam).";

            return null;
        }

        if (filled($rules['min_time'] ?? null) && $value < (string) $rules['min_time']) {
            $errors[] = "Waktu pada [{$label}] tidak boleh sebelum {$rules['min_time']}.";
        }
        if (filled($rules['max_time'] ?? null) && $value > (string) $rules['max_time']) {
            $errors[] = "Waktu pada [{$label}] tidak boleh setelah {$rules['max_time']}.";
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  list<string>  $errors
     * @param  list<int>  $verifiedUploadIds
     * @param  array<string, bool>  $usedUploadTokens
     * @return list<array<string, mixed>>|null
     */
    private function validateFileUpload(
        DynamicForm $form,
        string $fieldKey,
        string $label,
        bool $isRequired,
        mixed $rawValue,
        array $options,
        array &$errors,
        array &$verifiedUploadIds,
        array &$usedUploadTokens,
    ): ?array {
        if ($rawValue === null || $rawValue === []) {
            if ($isRequired) {
                $errors[] = "Lampiran berkas pada [{$label}] wajib diunggah.";
            }

            return null;
        }

        if (! is_array($rawValue)) {
            $errors[] = "Daftar token lampiran pada [{$label}] harus berupa array.";

            return null;
        }

        $tokens = array_values(array_filter(
            array_map(fn ($v) => is_string($v) ? trim($v) : '', $rawValue),
            fn (string $v): bool => $v !== ''
        ));

        if ($tokens === []) {
            if ($isRequired) {
                $errors[] = "Lampiran berkas pada [{$label}] wajib diunggah.";
            }

            return null;
        }

        $maxFiles = max(1, min(5, (int) ($options['max_files'] ?? 1)));
        if (count($tokens) > $maxFiles) {
            $errors[] = "Maksimal {$maxFiles} berkas pada [{$label}].";

            return null;
        }

        $fileRefs = [];
        foreach ($tokens as $token) {
            if (isset($usedUploadTokens[$token])) {
                $errors[] = "Token unggahan [{$token}] terduplikasi.";

                continue;
            }
            $usedUploadTokens[$token] = true;

            $upload = DynamicFormUpload::where('token', $token)->first();
            if (! $upload) {
                $errors[] = "Token berkas [{$token}] pada [{$label}] tidak ditemukan.";

                continue;
            }

            if ($upload->dynamic_form_id !== $form->id || $upload->field_key !== $fieldKey) {
                $errors[] = "Token berkas [{$token}] tidak sesuai dengan formulir atau pertanyaan ini.";

                continue;
            }

            if ($upload->submission_id !== null || $upload->attached_at !== null) {
                $errors[] = "Token berkas [{$token}] sudah pernah digunakan.";

                continue;
            }

            if ($upload->expires_at && $upload->expires_at->isPast()) {
                $errors[] = "Token berkas [{$token}] telah kedaluwarsa. Silakan unggah ulang berkas.";

                continue;
            }

            if (! Storage::disk($upload->disk)->exists($upload->storage_path)) {
                $errors[] = "Berkas untuk token [{$token}] tidak ditemukan di penyimpanan.";

                continue;
            }

            $verifiedUploadIds[] = $upload->id;
            $fileRefs[] = [
                'upload_id' => $upload->id,
                'token' => $upload->token,
                'original_name' => $upload->original_name,
                'mime_type' => $upload->mime_type,
                'size_bytes' => $upload->size_bytes,
            ];
        }

        return $fileRefs !== [] ? $fileRefs : null;
    }

    private function resolveBoundDate(mixed $bound): ?string
    {
        if (! is_string($bound) || trim($bound) === '') {
            return null;
        }

        $trimmed = strtolower(trim($bound));
        if ($trimmed === 'today') {
            return now()->format('Y-m-d');
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed) === 1 ? $trimmed : null;
    }
}
