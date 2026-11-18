<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DynamicFormUpload extends Model
{
    use HasFactory;

    protected $fillable = [
        'token',
        'dynamic_form_id',
        'submission_id',
        'field_key',
        'disk',
        'storage_path',
        'original_name',
        'mime_type',
        'size_bytes',
        'expires_at',
        'attached_at',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'expires_at' => 'datetime',
            'attached_at' => 'datetime',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(DynamicForm::class, 'dynamic_form_id');
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(DynamicFormSubmission::class, 'submission_id');
    }
}
