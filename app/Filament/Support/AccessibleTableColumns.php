<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Tables\Columns\ImageColumn;
use Illuminate\Database\Eloquent\Model;

/**
 * Helper kolom tabel Filament untuk memastikan konsistensi aksesibilitas WCAG AA
 * dan stabilitas judul konten bilingual (ID sebagai baris utama).
 */
final class AccessibleTableColumns
{
    public static function thumbnail(
        string $column = 'image_path',
        int $width = 80,
        int $height = 50,
        string $titleAttribute = 'title',
    ): ImageColumn {
        return ImageColumn::make($column)
            ->label(__('Pratinjau'))
            ->disk(config('filesystems.media_disk'))
            ->width($width)
            ->height($height)
            ->disabledClick()
            ->extraImgAttributes(function (Model $record) use ($titleAttribute): array {
                $title = self::indonesianPrimary($record, $titleAttribute);

                return [
                    'alt' => $title !== ''
                        ? __('Pratinjau gambar :title', ['title' => $title])
                        : __('Pratinjau gambar'),
                ];
            });
    }

    public static function indonesianPrimary(Model $record, string $attribute): string
    {
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

        return trim((string) ($record->getAttribute($attribute) ?? ''));
    }

    public static function englishSubtitle(Model $record, string $attribute): ?string
    {
        if (! method_exists($record, 'getTranslation')) {
            return null;
        }

        $english = trim((string) $record->getTranslation($attribute, 'en', false));

        return $english !== '' ? 'EN: '.$english : null;
    }
}
