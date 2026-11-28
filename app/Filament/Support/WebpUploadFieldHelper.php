<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Forms\Components\Placeholder;
use Illuminate\Database\Eloquent\Model;

/**
 * Helper DRY untuk panduan konversi WebP otomatis dan status format gambar tersimpan
 * pada seluruh form unggah gambar konten publik.
 */
final class WebpUploadFieldHelper
{
    public static function helperText(?string $resolutionHint = null): string
    {
        if (filled($resolutionHint)) {
            return __('Format JPEG/JPG/PNG/WebP, maks 5 MB (resolusi ideal :resolution). Gambar JPEG/PNG otomatis dioptimalkan ke format WebP saat disimpan.', [
                'resolution' => $resolutionHint,
            ]);
        }

        return __('Format JPEG/JPG/PNG/WebP, maks 5 MB. Gambar JPEG/PNG otomatis dioptimalkan ke format WebP saat disimpan.');
    }

    public static function statusPlaceholder(string $field = 'image_path'): Placeholder
    {
        return Placeholder::make('webp_optimization_status')
            ->label(__('Status Optimasi WebP'))
            ->visible(fn (?Model $record): bool => filled($record?->getAttribute($field)))
            ->content(function (?Model $record) use ($field): string {
                $path = (string) ($record?->getAttribute($field) ?? '');
                $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

                if ($extension === 'webp') {
                    return __('Gambar tersimpan sudah berformat WebP teroptimasi.');
                }

                return __('Format gambar saat ini (:ext) akan otomatis dikonversi ke WebP ketika gambar baru diunggah dan disimpan.', [
                    'ext' => strtoupper($extension !== '' ? $extension : 'N/A'),
                ]);
            })
            ->columnSpanFull();
    }
}
