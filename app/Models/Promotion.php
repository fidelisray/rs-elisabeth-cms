<?php

namespace App\Models;

use App\Observers\WebFrontendWebhookObserver;
use App\Traits\ConvertsImagesToWebp;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

#[ObservedBy([WebFrontendWebhookObserver::class])]
class Promotion extends Model
{
    use ConvertsImagesToWebp;
    use HasFactory;
    use SoftDeletes;

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
        'shorts',
        'description',
        'image_path',
        'start_date',
        'end_date',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /**
     * Tipe data konversi (Casting).
     */
    protected $casts = [
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Tanggal "hari ini" menurut zona waktu aplikasi (APP_TIMEZONE, Asia/Jakarta),
     * dalam format Y-m-d. Dipakai untuk filter dan untuk cache key agar cache
     * otomatis berganti saat pergantian hari.
     */
    public static function today(): string
    {
        return Carbon::now(config('app.timezone'))->toDateString();
    }

    /**
     * Promo yang sedang tayang pada tanggal tertentu (K-12):
     * aktif DAN (start_date kosong ATAU sudah mulai) DAN (end_date kosong ATAU belum berakhir).
     *
     * - start_date kosong = langsung berlaku.
     * - end_date kosong = tanpa batas akhir (tayang sampai dinonaktifkan manual).
     * - Batas bersifat inklusif: promo yang berakhir hari ini masih tampil sampai tengah malam.
     *
     * @param  Builder<Promotion>  $query
     * @return Builder<Promotion>
     */
    public function scopeActiveOn(Builder $query, ?string $date = null): Builder
    {
        $date ??= self::today();

        return $query
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('start_date')->orWhereDate('start_date', '<=', $date))
            ->where(fn (Builder $q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $date));
    }

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
            Cache::put('rs_web_cms_api_promotions_version', time());
        });

        static::deleted(function ($model) {
            Cache::put('rs_web_cms_api_promotions_version', time());
        });
    }
}
