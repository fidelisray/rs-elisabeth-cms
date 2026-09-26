<?php

namespace App\Models;

use App\Traits\ConvertsImagesToWebp;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
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
        'judul',
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
    protected function isActive(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
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
            $model->created_by = \Illuminate\Support\Facades\Auth::user()?->email ?? 'system';
        });

        static::updating(function ($model) {
            $model->updated_by = \Illuminate\Support\Facades\Auth::user()?->email ?? 'system';
        });

        static::deleting(function ($model) {
            $model->deleted_by = \Illuminate\Support\Facades\Auth::user()?->email ?? 'system';
            $model->saveQuietly();
        });

        // Hapus cache API ketika data diubah atau ditambah
        static::saved(function ($model) {
            \Illuminate\Support\Facades\Cache::put('rs_web_cms_api_articles_version', time());
        });

        // Hapus cache API ketika data dihapus
        static::deleted(function ($model) {
            \Illuminate\Support\Facades\Cache::put('rs_web_cms_api_articles_version', time());
        });
    }
}

