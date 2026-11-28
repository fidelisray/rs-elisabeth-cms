<?php

namespace App\Enums;

enum DynamicFormTextFormatPreset: string
{
    case LettersSpaces = 'letters_spaces';
    case DigitsOnly = 'digits_only';
    case CodeDash = 'code_dash';

    public function label(): string
    {
        return match ($this) {
            self::LettersSpaces => __('Hanya Huruf & Spasi (Contoh: Nama Lengkap, dr. Budi)'),
            self::DigitsOnly => __('Hanya Angka / Digit (Contoh: NIK, No. BPJS, No. RM)'),
            self::CodeDash => __('Huruf, Angka & Tanda Hubung (Contoh: RM-001, IGD-02)'),
        };
    }

    public function regex(): string
    {
        return match ($this) {
            self::LettersSpaces => '/^[\p{L}\s.,\'-]+$/u',
            self::DigitsOnly => '/^[0-9]+$/',
            self::CodeDash => '/^[A-Za-z0-9-]+$/',
        };
    }

    public function matches(string $value): bool
    {
        return preg_match($this->regex(), $value) === 1;
    }

    public function errorMessage(string $fieldLabel): string
    {
        return match ($this) {
            self::LettersSpaces => "Pertanyaan [{$fieldLabel}] hanya boleh berisi huruf, spasi, dan tanda baca nama umum (., '-).",
            self::DigitsOnly => "Pertanyaan [{$fieldLabel}] hanya boleh berisi angka (0-9).",
            self::CodeDash => "Pertanyaan [{$fieldLabel}] hanya boleh berisi huruf, angka, dan tanda hubung (-).",
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * Pilihan tunggal "Format Jawaban" pada tipe Teks Singkat di Wizard CMS.
     *
     * @return array<string, string>
     */
    public static function unifiedShortTextOptions(): array
    {
        return [
            'text' => __('Teks Bebas (Semua karakter)'),
            self::LettersSpaces->value => self::LettersSpaces->label(),
            self::DigitsOnly->value => self::DigitsOnly->label(),
            self::CodeDash->value => self::CodeDash->label(),
            'phone' => __('Nomor Telepon Indonesia (08 / +628, 10-13 digit)'),
            'email' => __('Alamat Email'),
            'number' => __('Angka / Nilai Hitung (Dengan batas min/maks & statistik)'),
            'url' => __('Tautan URL (http/https)'),
        ];
    }

    public static function resolveShortTextFormat(?string $subtype, ?string $preset): string
    {
        $normalizedSubtype = in_array($subtype, ['text', 'email', 'phone', 'number', 'url'], true)
            ? $subtype
            : 'text';

        if ($normalizedSubtype === 'text' && $preset !== null && self::tryFrom($preset) !== null) {
            return $preset;
        }

        return $normalizedSubtype;
    }

    /**
     * @return array{subtype: string, format_preset: ?string}
     */
    public static function splitUnifiedFormat(?string $format): array
    {
        $value = (string) ($format ?? 'text');

        if (self::tryFrom($value) !== null) {
            return [
                'subtype' => 'text',
                'format_preset' => $value,
            ];
        }

        if (in_array($value, ['text', 'email', 'phone', 'number', 'url'], true)) {
            return [
                'subtype' => $value,
                'format_preset' => null,
            ];
        }

        return [
            'subtype' => 'text',
            'format_preset' => null,
        ];
    }
}
