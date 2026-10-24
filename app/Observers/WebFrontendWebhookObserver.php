<?php

namespace App\Observers;

use App\Jobs\SendCacheInvalidationWebhookJob;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\BannerPromotion;
use App\Models\FacilityService;
use App\Models\News;
use App\Models\Promotion;
use App\Models\RoomFacility;
use Illuminate\Database\Eloquent\Model;

class WebFrontendWebhookObserver
{
    /**
     * Tentukan nama modul Web Frontend berdasarkan model terkait.
     */
    public function getModule(Model $model): ?string
    {
        if (method_exists($model, 'getWebhookModuleName')) {
            return $model->getWebhookModuleName();
        }

        return match (true) {
            $model instanceof News => 'news',
            $model instanceof Article => 'articles',
            $model instanceof Promotion => 'promotions',
            $model instanceof FacilityService => 'facilities',
            $model instanceof RoomFacility => 'room_facilities',
            $model instanceof BannerPromotion => 'banner_promotions',
            default => null,
        };
    }

    /**
     * Handle the Model "saved" event (Create & Update).
     */
    public function saved(Model $model): void
    {
        $module = $this->getModule($model);
        if ($module) {
            $event = $model->wasRecentlyCreated ? 'content.created' : 'content.updated';
            $slug = $model->slug ?? null;
            SendCacheInvalidationWebhookJob::dispatch($module, $event, $slug)->afterCommit();
        }
    }

    /**
     * Handle the Model "deleted" event (Delete & Soft Delete).
     */
    public function deleted(Model $model): void
    {
        $module = $this->getModule($model);
        if ($module) {
            $slug = $model->slug ?? null;
            SendCacheInvalidationWebhookJob::dispatch($module, 'content.deleted', $slug)->afterCommit();
        }
    }

    /**
     * Audit pemulihan (I18N-9.6). Webhook tidak dikirim di sini: restore() memanggil save()
     * yang sudah memicu "saved" (content.updated).
     */
    public function restored(Model $model): void
    {
        $module = $this->getModule($model);
        if ($module) {
            $hasTranslatedSlug = method_exists($model, 'isTranslatableAttribute') && $model->isTranslatableAttribute('slug');
            AuditLog::record('content.restored', $model, [
                'module' => $module,
                'slug_id' => $hasTranslatedSlug ? $model->getTranslation('slug', 'id', false) : null,
                'slug_en' => $hasTranslatedSlug ? $model->getTranslation('slug', 'en', false) : null,
                'source' => 'panel',
            ]);
        }
    }
}
