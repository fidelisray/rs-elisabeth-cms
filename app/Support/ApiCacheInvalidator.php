<?php

namespace App\Support;

use App\Jobs\SendCacheInvalidationWebhookJob;
use Illuminate\Support\Facades\Cache;
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
     * Fasilitas/ruangan memakai suffix locale untuk menjaga urutan sesuai bahasa.
     * Hanya modul tanpa paginasi; modul berpaginasi memakai key berversi
     * yang dibersihkan lewat event model.
     *
     * @var array<string, string>
     */
    private const KEYS = [
        'banner_promotions' => 'rs_web_cms_api_banner_promotions',
        'facilities' => 'rs_web_cms_api_facility_services',
        'room_facilities' => 'rs_web_cms_api_room_facilities',
    ];

    private const LOCALIZED_MODULES = ['facilities', 'room_facilities'];

    public static function keyForModule(string $module, ?string $locale = null): string
    {
        $key = self::baseKey($module);

        return in_array($module, self::LOCALIZED_MODULES, true)
            ? $key.'_'.TranslatedContentOrder::locale($locale ?? app()->getLocale())
            : $key;
    }

    /**
     * Model events use this without dispatching a second webhook alongside the observer.
     */
    public static function forgetForModule(string $module): void
    {
        // Also expire the pre-locale key during the transition from older application versions.
        Cache::forget(self::baseKey($module));

        if (in_array($module, self::LOCALIZED_MODULES, true)) {
            foreach (config('app.supported_locales', ['id', 'en']) as $locale) {
                Cache::forget(self::keyForModule($module, $locale));
            }
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
