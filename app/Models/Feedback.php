<?php

namespace App\Models;

use App\Models\Concerns\GuardsHardDelete;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

/**
 * Kritik dan saran pasien. Data pribadi: hanya dibaca role yang berwenang.
 */
class Feedback extends Model
{
    use GuardsHardDelete {
        GuardsHardDelete::forceDelete insteadof SoftDeletes;
        GuardsHardDelete::forceDeleteQuietly insteadof SoftDeletes;
    }
    use HasFactory;
    use SoftDeletes;

    public const STATUSES = ['baru', 'diproses', 'selesai'];

    protected $table = 'feedbacks';

    protected $fillable = [
        'ticket_number',
        'reporter_name',
        'relation_to_patient',
        'relation_other',
        'patient_name',
        'phone_number',
        'categories',
        'category_other',
        'complaint_detail',
        'expected_solution',
        'locale',
        'source',
        'submitted_at',
        'status',
        'internal_note',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'categories' => 'array',
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
