<?php

namespace App\Models;

use App\Models\Concerns\GuardsHardDelete;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

/**
 * Satu pengisian kuesioner kepuasan pasien.
 */
class QuestionnaireResponse extends Model
{
    use GuardsHardDelete {
        GuardsHardDelete::forceDelete insteadof SoftDeletes;
        GuardsHardDelete::forceDeleteQuietly insteadof SoftDeletes;
    }
    use HasFactory;
    use SoftDeletes;

    /**
     * Jenis kuesioner. Format URL, sama di seluruh kontrak.
     */
    public const TYPES = [
        'igd' => 'IGD',
        'rawat-jalan' => 'Rawat Jalan',
        'rawat-inap' => 'Rawat Inap',
    ];

    protected $table = 'questionnaire_responses';

    protected $fillable = [
        'questionnaire_type',
        'age_group',
        'gender',
        'payment_method',
        'ratings',
        'suggestions',
        'locale',
        'source',
        'submitted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'ratings' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $model) {
            $model->created_by = Auth::user()?->email ?? 'system';
        });

        static::updating(function (self $model) {
            $model->updated_by = Auth::user()?->email ?? 'system';
        });
    }
}
