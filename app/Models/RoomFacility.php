<?php

namespace App\Models;

use App\Traits\ConvertsImagesToWebp;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RoomFacility extends Model
{
    use HasFactory;
    use \Illuminate\Database\Eloquent\SoftDeletes;
    use ConvertsImagesToWebp;

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
        'category',
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
        'amenities'           => 'array',
        'highlight_tags'      => 'array',
        'is_active'           => 'boolean',
        'sort_order'          => 'integer',
    ];

    /**
     * Auto-generate slug from name on creating, if slug is empty.
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

        static::saved(function ($model) {
            \Illuminate\Support\Facades\Cache::forget('rs_web_cms_api_room_facilities');
        });

        static::deleted(function ($model) {
            \Illuminate\Support\Facades\Cache::forget('rs_web_cms_api_room_facilities');
        });
    }
}

