<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DynamicFormVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'dynamic_form_id',
        'version_number',
        'status',
        'schema_snapshot',
        'published_at',
        'published_by',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'schema_snapshot' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(DynamicForm::class, 'dynamic_form_id')->withTrashed();
    }

    public function sections(): HasMany
    {
        return $this->hasMany(DynamicFormSection::class, 'dynamic_form_version_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(DynamicFormField::class, 'dynamic_form_version_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(DynamicFormSubmission::class, 'dynamic_form_version_id');
    }
}
