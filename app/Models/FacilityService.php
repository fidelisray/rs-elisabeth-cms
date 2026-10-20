<?php

namespace App\Models;

use App\Observers\WebFrontendWebhookObserver;
use App\Traits\ConvertsImagesToWebp;
use App\Traits\HasCmsTranslations;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

#[ObservedBy([WebFrontendWebhookObserver::class])]
class FacilityService extends Model
{
    use ConvertsImagesToWebp;
    use HasCmsTranslations;
    use HasFactory;
    use SoftDeletes;

    public array $translatable = [
        'name',
        'slug',
        'short_description',
        'description',
    ];

    protected $fillable = [
        'name',
        'slug',
        'description',
        'short_description',
        'image_path',
        'category',
        'highlights',
        'wa_link_text',
        'wa_number',
        'wa_prefilled_message',
        'has_appointment_cta',
        'sort_order',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'highlights' => 'array',
        'has_appointment_cta' => 'boolean',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Boot: auto-generate slug, dan invalidasi cache setiap ada perubahan data.
     */
    protected static function boot(): void
    {
        parent::boot();

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

        // Flush cache lokal agar perubahan dari CMS langsung terekspos ke frontend.
        static::saved(function () {
            Cache::forget('rs_web_cms_api_facility_services');
        });

        static::deleted(function () {
            Cache::forget('rs_web_cms_api_facility_services');
        });
    }

    public function getWebpFields(): array
    {
        return ['image_path'];
    }
}
