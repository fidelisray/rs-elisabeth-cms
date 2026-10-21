<?php

namespace App\Models;

use App\Observers\WebFrontendWebhookObserver;
use App\Support\ApiCacheInvalidator;
use App\Traits\ConvertsImagesToWebp;
use App\Traits\HasCmsTranslations;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

#[ObservedBy([WebFrontendWebhookObserver::class])]
class Article extends Model
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
        'tags',
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
        'image_path',
        'shorts',
        'content',
        'tags',
        'author',
        'is_active',
        'views',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /**
     * Interact with the article's is_active status.
     * Maps 'yes'/'no' string to boolean for Filament Toggle.
     */
    protected function isActive(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === 'yes',
            set: fn (?bool $value) => $value ? 'yes' : 'no',
        );
    }

    /**
     * Boot function to automatically fill audit fields and clear cache.
     */
    protected static function boot()
    {
        parent::boot();

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

        // Hapus cache API ketika data diubah atau ditambah
        static::saved(function ($model) {
            ApiCacheInvalidator::forgetForModule('articles');
        });

        // Hapus cache API ketika data dihapus
        static::deleted(function ($model) {
            ApiCacheInvalidator::forgetForModule('articles');
        });
    }
}
