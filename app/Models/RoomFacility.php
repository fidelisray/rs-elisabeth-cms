<?php

namespace App\Models;

use App\Observers\WebFrontendWebhookObserver;
use App\Support\ApiCacheInvalidator;
use App\Support\RoomNestedContent;
use App\Traits\ConvertsImagesToWebp;
use App\Traits\HasCmsTranslations;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

#[ObservedBy([WebFrontendWebhookObserver::class])]
class RoomFacility extends Model
{
    use ConvertsImagesToWebp;
    use HasCmsTranslations;
    use HasFactory;
    use SoftDeletes;

    public array $translatable = [
        'name',
        'slug',
        'tagline',
        'description',
        'whatsapp_text',
    ];

    /**
     * Kolom-kolom yang menyimpan path gambar dan akan dikonversi ke WebP.
     */
    protected function getWebpFields(): array
    {
        return ['image_path'];
    }

    protected $fillable = [
        'name',
        'slug',
        'tagline',
        'description',
        'room_size',
        'bed_count',
        'max_companion',
        'image_path',
        'amenities',
        'highlight_tags',
        'whatsapp_text',
        'sort_order',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /**
     * Cast JSON columns to array and boolean column to bool.
     */
    protected $casts = [
        'amenities' => 'array',
        'highlight_tags' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Auto-generate slug from name on creating, if slug is empty.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (self $model): void {
            $data = RoomNestedContent::prepare([
                'amenities' => $model->amenities,
                'highlight_tags' => $model->highlight_tags,
                'whatsapp_text' => $model->getTranslations('whatsapp_text') ?: null,
            ]);
            RoomNestedContent::validator($data)->validate();
            $model->amenities = $data['amenities'];
            $model->highlight_tags = $data['highlight_tags'];
        });

        static::creating(function (self $model) {
            $model->ensureTranslatedSlugsFrom('name');
            if (empty($model->slug) && filled($model->name)) {
                $model->slug = Str::slug($model->name);
            }
            $model->created_by = Auth::user()?->email ?? 'system';
        });

        static::updating(function (self $model) {
            $model->ensureTranslatedSlugsFrom('name');
            if (empty($model->slug) && filled($model->name)) {
                $model->slug = Str::slug($model->name);
            }
            $model->updated_by = Auth::user()?->email ?? 'system';
        });

        static::deleting(function (self $model) {
            $model->deleted_by = Auth::user()?->email ?? 'system';
            $model->saveQuietly();
        });

        static::saved(function ($model) {
            ApiCacheInvalidator::forgetForModule('room_facilities');
        });

        static::deleted(function ($model) {
            ApiCacheInvalidator::forgetForModule('room_facilities');
        });
    }
}
