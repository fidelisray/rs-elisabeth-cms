<?php

namespace App\Models;

use App\Models\Concerns\GuardsHardDelete;
use App\Observers\WebFrontendWebhookObserver;
use App\Support\ApiCacheInvalidator;
use App\Traits\HasCmsTranslations;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

#[ObservedBy([WebFrontendWebhookObserver::class])]
class Doctor extends Model
{
    use GuardsHardDelete {
        GuardsHardDelete::forceDelete insteadof SoftDeletes;
        GuardsHardDelete::forceDeleteQuietly insteadof SoftDeletes;
    }
    use HasCmsTranslations;
    use HasFactory;
    use SoftDeletes;

    /**
     * Field konten marketing yang mendukung translasi dwi-bahasa (ID & EN).
     */
    public array $translatable = [
        'bio',
        'location',
        'experience',
        'education',
    ];

    protected $fillable = [
        'paramedic_code',
        'name',
        'specialty_code',
        'specialty_name',
        'service_unit_name',
        'bio',
        'location',
        'experience',
        'education',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function getWebhookModuleName(): string
    {
        return 'doctors';
    }

    /**
     * Memeriksa apakah profil dokter sudah diisi lengkap oleh tim marketing.
     */
    public function getIsProfileCompleteAttribute(): bool
    {
        return ! empty($this->getTranslation('bio', 'id', false))
            && ! empty($this->getTranslation('location', 'id', false))
            && ! empty($this->getTranslation('experience', 'id', false))
            && ! empty($this->getTranslation('education', 'id', false));
    }

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

        static::saved(function () {
            ApiCacheInvalidator::forgetForModule('doctors');
        });
    }
}
