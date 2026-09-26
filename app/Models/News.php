<?php

namespace App\Models;

use App\Traits\ConvertsImagesToWebp;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class News extends Model
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
            $model->created_by = \Illuminate\Support\Facades\Auth::user()?->email ?? 'system';
        });

        static::updating(function ($model) {
            $model->updated_by = \Illuminate\Support\Facades\Auth::user()?->email ?? 'system';
        });

        static::deleting(function ($model) {
            $model->deleted_by = \Illuminate\Support\Facades\Auth::user()?->email ?? 'system';
            $model->saveQuietly();
        });

        static::saved(function ($model) {
            \Illuminate\Support\Facades\Cache::put('rs_web_cms_api_news_version', time());
        });

        static::deleted(function ($model) {
            \Illuminate\Support\Facades\Cache::put('rs_web_cms_api_news_version', time());
        });
    }
}

