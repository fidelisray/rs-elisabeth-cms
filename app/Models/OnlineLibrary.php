<?php

namespace App\Models;

use App\Models\Concerns\GuardsHardDelete;
use App\Observers\WebFrontendWebhookObserver;
use App\Support\ApiCacheInvalidator;
use App\Traits\HasCmsTranslations;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

#[ObservedBy([WebFrontendWebhookObserver::class])]
class OnlineLibrary extends Model
{
    use GuardsHardDelete {
        GuardsHardDelete::forceDelete insteadof SoftDeletes;
        GuardsHardDelete::forceDeleteQuietly insteadof SoftDeletes;
    }
    use HasCmsTranslations;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'online_libraries';

    public array $translatable = [
        'title',
        'slug',
        'description',
        'category',
    ];

    protected $fillable = [
        'title',
        'slug',
        'description',
        'category',
        'file_path',
        'file_name',
        'file_size',
        'is_published',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'file_size' => 'integer',
    ];

    public function getWebhookModuleName(): string
    {
        return 'online_libraries';
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::saving(function ($model) {
            if ($model->file_path && empty($model->file_name)) {
                $model->file_name = basename($model->file_path);
            }
            if ($model->isDirty('file_path') && $model->file_path) {
                try {
                    $disk = Storage::disk(config('filesystems.media_disk'));
                    if ($disk->exists($model->file_path)) {
                        $model->file_size = $disk->size($model->file_path);
                    }
                } catch (\Throwable) {
                    // Ignore disk lookup failure
                }
            }
        });

        static::creating(function ($model) {
            $model->created_by = Auth::user()?->email ?? 'system';
        });

        static::updating(function ($model) {
            $model->updated_by = Auth::user()?->email ?? 'system';
        });

        static::deleting(function ($model) {
            $model->deleted_by = Auth::user()?->email ?? 'system';
            $model->saveQuietly();
        });

        static::saved(function ($model) {
            ApiCacheInvalidator::forgetForModule('online_libraries');
        });

        static::deleted(function ($model) {
            ApiCacheInvalidator::forgetForModule('online_libraries');
        });
    }
}
