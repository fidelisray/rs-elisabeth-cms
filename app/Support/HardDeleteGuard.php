<?php

namespace App\Support;

use App\Exceptions\HardDeleteForbiddenException;

/**
 * Pengaman hard delete konten (I18N-9.3, I-17).
 *
 * - Token hanya dipasang oleh App\Actions\HardDeleteContent, dan selalu dilepas di finally.
 * - Selalu ditolak di console (artisan, tinker, queue worker), termasuk bila token ada.
 *   Pengecualian hanya untuk test PHPUnit melalui flag konfigurasi, bukan ENV produksi.
 */
final class HardDeleteGuard
{
    private static int $depth = 0;

    public static function allow(callable $callback): mixed
    {
        self::$depth++;

        try {
            return $callback();
        } finally {
            self::$depth--;
        }
    }

    public static function assertAllowed(): void
    {
        if (self::isConsoleBlocked() || self::$depth === 0) {
            throw HardDeleteForbiddenException::bypassed();
        }
    }

    private static function isConsoleBlocked(): bool
    {
        if (! app()->runningInConsole()) {
            return false;
        }

        return ! app()->runningUnitTests();
    }
}
