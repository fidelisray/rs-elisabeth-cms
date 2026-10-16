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
     * Peta modul (nama modul webhook) ke key cache tunggal yang dibaca API.
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

    /**
     * Hapus cache API modul tersebut dan kirim webhook ke Web Frontend.
     *
     * @throws InvalidArgumentException bila modul tidak dikenal, agar kesalahan
     *                                  penamaan tidak berujung pada invalidasi yang diam-diam tidak terjadi.
     */
    public static function forModule(string $module): void
    {
        if (! array_key_exists($module, self::KEYS)) {
            throw new InvalidArgumentException("Modul cache API tidak dikenal: {$module}");
        }

        Cache::forget(self::KEYS[$module]);

        SendCacheInvalidationWebhookJob::dispatch($module, 'content.updated')->afterCommit();
    }
}
