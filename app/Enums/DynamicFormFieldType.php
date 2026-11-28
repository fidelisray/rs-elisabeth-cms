<?php

namespace App\Enums;

use Illuminate\Container\Container;

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
            self::ShortText => self::t('Teks Singkat'),
            self::LongText => self::t('Teks Panjang (Paragraf)'),
            self::Radio => self::t('Pilihan Tunggal (Radio)'),
            self::Checkbox => self::t('Kotak Centang (Checkbox)'),
            self::Dropdown => self::t('Daftar Pilihan (Dropdown)'),
            self::LinearScale => self::t('Skala Linier'),
            self::Rating => self::t('Penilaian (Rating)'),
            self::MatrixGrid => self::t('Kisi Pilihan (Matrix / Grid)'),
            self::Date => self::t('Tanggal'),
            self::Time => self::t('Jam / Waktu'),
            self::FileUpload => self::t('Unggah Berkas'),
            self::InfoBlock => self::t('Blok Informasi (Non-Input)'),
        };
    }

    private static function t(string $key): string
    {
        return Container::getInstance()->bound('translator') ? __($key) : $key;
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
