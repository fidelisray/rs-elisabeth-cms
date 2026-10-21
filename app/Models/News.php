<?php

namespace App\Models;

use App\Observers\WebFrontendWebhookObserver;
use App\Support\ApiCacheInvalidator;
use App\Traits\ConvertsImagesToWebp;
use App\Traits\HasCmsTranslations;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

#[ObservedBy([WebFrontendWebhookObserver::class])]
class News extends Model
{
    use ConvertsImagesToWebp;
    use HasCmsTranslations;
    use HasFactory;
    use SoftDeletes;

    public array $translatable = [
        'title',
        'slug',
        'shorts',
        'content',
        'category',
    ];

    /**
     * Kolom-kolom yang menyimpan path gambar dan akan dikonversi ke WebP.
     */
    protected function getWebpFields(): array
    {
        return ['image_path'];
    }

    protected $fillable = [
        'title',
        'slug',
        'category',
        'author',
        'shorts',
        'content',
        'image_path',
        'is_published',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /**
     * Tipe data konversi (Casting).
     */
    protected $casts = [
        'is_published' => 'boolean',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
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
            ApiCacheInvalidator::forgetForModule('news');
        });

        static::deleted(function ($model) {
            ApiCacheInvalidator::forgetForModule('news');
        });
    }
}
