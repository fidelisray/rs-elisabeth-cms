<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DynamicFormSubmissionAnswer extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'submission_id',
        'dynamic_form_id',
        'dynamic_form_version_id',
        'field_key',
        'field_type',
        'row_key',
        'value_text',
        'value_number',
        'value_json',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'value_number' => 'float',
            'value_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(DynamicFormSubmission::class, 'submission_id');
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(DynamicForm::class, 'dynamic_form_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(DynamicFormVersion::class, 'dynamic_form_version_id');
    }
}
