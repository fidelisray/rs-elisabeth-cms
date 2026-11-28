<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

/**
 * Mempertahankan judul record konten bilingual tetap berjangkar pada Bahasa Indonesia ('id')
 * dengan fallback 'en' pada kedua mode UI (ID & EN) agar judul halaman Edit stabil.
 */
trait FormatsBilingualRecordTitle
{
    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        if (! $record) {
            return static::getModelLabel();
        }

        $attribute = static::getRecordTitleAttribute();
        if (! is_string($attribute) || $attribute === '') {
            return static::getModelLabel();
        }

        if (method_exists($record, 'getTranslation')) {
            $indonesian = trim((string) $record->getTranslation($attribute, 'id', false));
            if ($indonesian !== '') {
                return $indonesian;
            }

            $english = trim((string) $record->getTranslation($attribute, 'en', false));
            if ($english !== '') {
                return $english;
            }
        }

        $value = $record->getAttribute($attribute);

        return filled($value) ? (string) $value : static::getModelLabel();
    }
}
