<?php

namespace App\Support\Theme;

use Filament\Support\Colors\Color;

final class BrandColors
{
    /**
     * RS Elisabeth Medical Navy (acuan docs/DESIGN_SYSTEM.md):
     * - Shade 600 = #095988 (hsl 204 88% 30%, light --primary; kontras vs #ffffff = 7.51:1 WCAG AAA)
     * - Shade 500 = #0d74b0 (light hover; kontras vs #ffffff = 5.07:1 WCAG AA)
     * - Shade 400 = #1997e6 (hsl 204 80% 50%, dark --primary; kontras vs #0f172a = 5.62:1 WCAG AA)
     *
     * @return array<int, string>
     */
    public static function primary(): array
    {
        return [
            50 => '#f0f8fc',
            100 => '#dceff9',
            200 => '#bae0f4',
            300 => '#89c9ea',
            400 => '#1997e6',
            500 => '#0d74b0',
            600 => '#095988',
            700 => '#07476e',
            800 => '#063a5a',
            900 => '#052d47',
            950 => '#031d2f',
        ];
    }

    /**
     * RS Elisabeth Fresh Emerald (acuan docs/DESIGN_SYSTEM.md):
     * - Shade 500 = #159e74 (hsl 162 76% 36%, --secondary; kontras vs #0f172a = 5.25:1 WCAG AA)
     * - Shade 600 = #0f7a59 (dikalibrasi agar teks putih tombol light memenuhi WCAG AA = 5.32:1)
     *
     * @return array<int, string>
     */
    public static function secondary(): array
    {
        return [
            50 => '#ecfdf5',
            100 => '#d1fae5',
            200 => '#a7f3d0',
            300 => '#6ee7b7',
            400 => '#34d399',
            500 => '#159e74',
            600 => '#0f7a59',
            700 => '#0c6147',
            800 => '#094d38',
            900 => '#063d2d',
            950 => '#052e21',
        ];
    }

    /**
     * Pemetaan lengkap palet semantik Filament 5.7.
     *
     * @return array<string, array<int, string>>
     */
    public static function filamentColors(): array
    {
        return [
            'primary' => self::primary(),
            'gray' => Color::Slate,
            'info' => Color::Sky,
            'success' => self::secondary(),
            'warning' => Color::Amber,
            'danger' => Color::Rose,
        ];
    }
}
