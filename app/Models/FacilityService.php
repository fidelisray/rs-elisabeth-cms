<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class FacilityService extends Model
{
    use HasFactory;
    use \Illuminate\Database\Eloquent\SoftDeletes;
    use \App\Traits\ConvertsImagesToWebp;

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
        'highlights'          => 'array',
        'has_appointment_cta' => 'boolean',
        'sort_order'          => 'integer',
        'is_active'           => 'boolean',
    ];

    /**
     * Boot: auto-generate slug, dan invalidasi cache setiap ada perubahan data.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->name);
            }
            $model->created_by = \Illuminate\Support\Facades\Auth::user()?->email ?? 'system';
        });

        static::updating(function (self $model) {
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->name);
            }
            $model->updated_by = \Illuminate\Support\Facades\Auth::user()?->email ?? 'system';
        });

        static::deleting(function (self $model) {
            $model->deleted_by = \Illuminate\Support\Facades\Auth::user()?->email ?? 'system';
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

