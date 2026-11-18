<?php

namespace App\Enums;

enum DynamicFormFieldType: string
{
    case ShortText = 'short_text';
    case LongText = 'long_text';
    case Radio = 'radio';
    case Checkbox = 'checkbox';
    case Dropdown = 'dropdown';
    case LinearScale = 'linear_scale';
    case Rating = 'rating';
    case MatrixGrid = 'matrix_grid';
    case Date = 'date';
    case Time = 'time';
    case FileUpload = 'file_upload';
    case InfoBlock = 'info_block';

    public function label(): string
    {
        return match ($this) {
            self::ShortText => 'Teks Singkat',
            self::LongText => 'Teks Panjang (Paragraf)',
            self::Radio => 'Pilihan Tunggal (Radio)',
            self::Checkbox => 'Kotak Centang (Checkbox)',
            self::Dropdown => 'Daftar Pilihan (Dropdown)',
            self::LinearScale => 'Skala Linier',
            self::Rating => 'Penilaian (Rating)',
            self::MatrixGrid => 'Kisi Pilihan (Matrix / Grid)',
            self::Date => 'Tanggal',
            self::Time => 'Jam / Waktu',
            self::FileUpload => 'Unggah Berkas',
            self::InfoBlock => 'Blok Informasi (Non-Input)',
        };
    }

    public function isInput(): bool
    {
        return $this !== self::InfoBlock;
    }

    public function supportsChoices(): bool
    {
        return in_array($this, [self::Radio, self::Checkbox, self::Dropdown], true);
    }

    public function supportsSectionJump(): bool
    {
        return in_array($this, [self::Radio, self::Dropdown], true);
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
}
