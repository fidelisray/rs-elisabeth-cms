<?php

namespace App\Models;

use App\Traits\ConvertsImagesToWebp;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class BannerPromotion extends Model
{
    use HasFactory;
    use SoftDeletes, ConvertsImagesToWebp;

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
        'is_active',
        'sort_order',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Boot function to automatically fill audit trail fields and clear cache.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Isi created_by dan sort_order otomatis saat data pertama kali dibuat
        static::creating(function (self $model) {
            $model->created_by = Auth::user()?->email ?? 'system';
            $model->sort_order = (int) static::max('sort_order') + 1;
        });

        // Isi updated_by setiap kali data diperbarui
        static::updating(function (self $model) {
            $model->updated_by = Auth::user()?->email ?? 'system';
        });

        // Isi deleted_by saat data di-soft-delete
        static::deleting(function (self $model) {
            $model->deleted_by = Auth::user()?->email ?? 'system';
            $model->saveQuietly();
        });

        // Hapus cache API setiap ada perubahan data (create / update)
        static::saved(function () {
            Cache::forget('rs_web_cms_api_banner_promotions');
        });

        // Hapus cache API setiap ada penghapusan data
        static::deleted(function () {
            Cache::forget('rs_web_cms_api_banner_promotions');
        });
    }
}

