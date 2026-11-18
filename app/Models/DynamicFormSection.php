<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DynamicFormSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'dynamic_form_version_id',
        'section_key',
        'title',
        'description',
        'sort_order',
        'next_section_rule',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'next_section_rule' => 'array',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(DynamicFormVersion::class, 'dynamic_form_version_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(DynamicFormField::class, 'dynamic_form_section_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
