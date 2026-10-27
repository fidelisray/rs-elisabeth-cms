<?php

namespace App\Support;

/** Satu aturan cakupan penarikan untuk warm-up dan sinkronisasi dokter. */
final class DoctorSpecialtyScope
{
    public static function isExcluded(string $code): bool
    {
        $excluded = array_values(array_unique(array_filter(array_map(
            fn ($value): string => is_string($value) ? trim($value) : '',
            (array) config('rsapi.blacklist_specialties', [])
        ), fn (string $value): bool => $value !== '')));

        return in_array(trim($code), $excluded, true);
    }

    /** Tidak mengubah tanda hubung, spasi internal, atau kapitalisasi kode sumber. */
    public static function select(array $master): array
    {
        $specialties = [];
        $excluded = [];
        $invalid = 0;

        foreach ($master as $entry) {
            $entry = (array) $entry;
            $code = $entry['Code'] ?? null;
            if (! is_string($code) || trim($code) === '') {
                $invalid++;

                continue;
            }
            $code = trim($code);
            if (self::isExcluded($code)) {
                $excluded[] = $code;

                continue;
            }
            $entry['Code'] = $code;
            $specialties[$code] = $entry;
        }

        return [
            'specialties' => array_values($specialties),
            'source_specialties' => count($master),
            'excluded_specialties' => array_values(array_unique($excluded)),
            'invalid_specialties' => $invalid,
        ];
    }
}
