<?php

namespace App\Observers;

use App\Jobs\SendCacheInvalidationWebhookJob;
use App\Models\Article;
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
     * Handle the Model "restored" event (Restore Soft Deleted).
     */
    public function restored(Model $model): void
    {
        $module = $this->getModule($model);
        if ($module) {
            $slug = $model->slug ?? null;
            SendCacheInvalidationWebhookJob::dispatch($module, 'content.updated', $slug)->afterCommit();
        }
    }

    /**
     * Handle the Model "forceDeleted" event.
     */
    public function forceDeleted(Model $model): void
    {
        $this->deleted($model);
    }
}
