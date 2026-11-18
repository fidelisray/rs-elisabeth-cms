<?php

namespace App\Models;

use App\Enums\FeedbackTicketStatus;
use App\Models\Concerns\GuardsHardDelete;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class DynamicFormSubmission extends Model
{
    use GuardsHardDelete {
        GuardsHardDelete::forceDelete insteadof SoftDeletes;
        GuardsHardDelete::forceDeleteQuietly insteadof SoftDeletes;
    }
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'dynamic_form_id',
        'dynamic_form_version_id',
        'ticket_number',
        'status',
        'internal_note',
        'answers',
        'locale',
        'source',
        'respondent_identifier_hash',
        'submitted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => FeedbackTicketStatus::class,
            'answers' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(DynamicForm::class, 'dynamic_form_id')->withTrashed();
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(DynamicFormVersion::class, 'dynamic_form_version_id');
    }

    public function indexedAnswers(): HasMany
    {
        return $this->hasMany(DynamicFormSubmissionAnswer::class, 'submission_id');
    }

    public function uploads(): HasMany
    {
        return $this->hasMany(DynamicFormUpload::class, 'submission_id');
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model): void {
            $model->created_by ??= Auth::user()?->email ?? 'system';
        });

        static::updating(function (self $model): void {
            $model->updated_by = Auth::user()?->email ?? 'system';
        });

        static::deleting(function (self $model): void {
            if (! $model->isForceDeleting()) {
                $model->deleted_by = Auth::user()?->email ?? 'system';
                $model->saveQuietly();
            }
        });
    }
}
