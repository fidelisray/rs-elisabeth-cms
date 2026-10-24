<?php

namespace App\Support;

use App\Jobs\SendCacheInvalidationWebhookJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Satu pintu untuk menghapus cache API dan memberi tahu Web Frontend.
 *
 * Diperlukan di tempat yang mengubah data tanpa memicu event model, misalnya
 * drag-and-drop urutan (reorderTable memakai query massal). Tanpa ini, urutan
 * baru baru tampil di web setelah TTL cache habis (hingga 6 jam).
 */
class ApiCacheInvalidator
{
    /**
     * Peta modul (nama modul webhook) ke prefix cache API.
     * Semua key data memiliki locale. Modul berpaginasi memakai satu versi bersama
     * agar seluruh bahasa/halaman lama dapat diinvalidasi tanpa memindai cache.
     *
     * @var array<string, string>
     */
    private const KEYS = [
        'news' => 'rs_web_cms_api_news',
        'articles' => 'rs_web_cms_api_articles',
        'promotions' => 'rs_web_cms_api_promotions',
        'banner_promotions' => 'rs_web_cms_api_banner_promotions',
        'facilities' => 'rs_web_cms_api_facility_services',
        'room_facilities' => 'rs_web_cms_api_room_facilities',
        'doctors' => 'rs_web_cms_api_doctors',
    ];

    private const PAGINATED_MODULES = ['news', 'articles', 'promotions'];

    public static function keyForModule(string $module, ?string $locale = null): string
    {
        return self::baseKey($module).'_'.TranslatedContentOrder::locale($locale ?? app()->getLocale());
    }

    public static function doctorListKey(?string $locale, ?string $specialty = null): string
    {
        $version = Cache::rememberForever(self::baseKey('doctors').'_version', fn (): string => (string) Str::uuid());

        return self::keyForModule('doctors', $locale).'_v'.$version.'_'.hash('sha256', $specialty ?? '');
    }

    public static function pageKeyForModule(string $module, int $page, ?string $locale = null, ?string $date = null): string
    {
        if (! in_array($module, self::PAGINATED_MODULES, true) || $page < 1) {
            throw new InvalidArgumentException('Invalid paginated cache key.');
        }

        if ($module === 'promotions' && (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date))) {
            throw new InvalidArgumentException('Promotion cache keys require the application date.');
        }

        $version = Cache::rememberForever(self::baseKey($module).'_version', fn (): string => (string) Str::uuid());

        return self::keyForModule($module, $locale).'_v'.$version
            .($module === 'promotions' ? '_'.$date : '').'_page_'.$page;
    }

    /**
     * Model events use this without dispatching a second webhook alongside the observer.
     */
    public static function forgetForModule(string $module): void
    {
        self::invalidate($module);

        // A concurrent reader can repopulate the old committed snapshot during a write.
        if (DB::transactionLevel() > 0) {
            DB::afterCommit(fn () => self::invalidate($module));
        }
    }

    private static function invalidate(string $module): void
    {
        // Also expire the pre-locale key during the transition from older application versions.
        Cache::forget(self::baseKey($module));

        if ($module === 'doctors' || in_array($module, self::PAGINATED_MODULES, true)) {
            // UUID avoids retaining a generation when two mutations happen in one second.
            Cache::forever(self::baseKey($module).'_version', (string) Str::uuid());

            return;
        }

        foreach (config('app.supported_locales', ['id', 'en']) as $locale) {
            Cache::forget(self::keyForModule($module, $locale));
        }
    }

    private static function baseKey(string $module): string
    {
        return self::KEYS[$module] ?? throw new InvalidArgumentException("Modul cache API tidak dikenal: {$module}");
    }

    /**
     * Hapus cache API modul tersebut dan kirim webhook ke Web Frontend.
     *
     * @throws InvalidArgumentException bila modul tidak dikenal, agar kesalahan
     *                                  penamaan tidak berujung pada invalidasi yang diam-diam tidak terjadi.
     */
    public static function forModule(string $module): void
    {
        self::forgetForModule($module);

        SendCacheInvalidationWebhookJob::dispatch($module, 'content.updated')->afterCommit();
    }
}
