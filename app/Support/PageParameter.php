<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Normalisasi parameter ?page untuk endpoint berpaginasi.
 *
 * Nilai mentah dari klien tidak boleh dipakai langsung di key cache: nilai
 * seperti "abc", "-1", atau "1.5" masing-masing akan membuat key cache baru,
 * sehingga tabel cache bisa dibengkakkan tanpa batas oleh klien yang sah
 * maupun yang secret-nya bocor.
 */
class PageParameter
{
    /**
     * Kembalikan bilangan bulat 1..max_page. Nilai tidak valid menjadi 1;
     * nilai di atas batas dijepit ke batas.
     */
    public static function from(Request $request): int
    {
        $raw = $request->query('page');

        // Hanya digit murni yang diterima. "1.5", "-1", "abc", "" dan "0" ditolak.
        if (! is_string($raw) || ! ctype_digit($raw)) {
            return 1;
        }

        // Cegah overflow integer sebelum dikonversi.
        if (strlen($raw) > 9) {
            return (int) config('cms_api.max_page', 1000);
        }

        $page = (int) $raw;

        if ($page < 1) {
            return 1;
        }

        return min($page, max(1, (int) config('cms_api.max_page', 1000)));
    }
}
