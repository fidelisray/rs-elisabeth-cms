<?php

namespace App\Models;

use App\Enums\DynamicFormFieldType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DynamicFormField extends Model
{
    use HasFactory;

    protected $fillable = [
        'dynamic_form_version_id',
        'dynamic_form_section_id',
        'field_key',
        'type',
        'label',
        'help_text',
        'placeholder',
        'is_required',
        'sort_order',
        'options',
        'validation_rules',
        'conditional_logic',
    ];

    protected function casts(): array
    {
        return [
            'type' => DynamicFormFieldType::class,
            'is_required' => 'boolean',
            'sort_order' => 'integer',
            'options' => 'array',
            'validation_rules' => 'array',
            'conditional_logic' => 'array',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(DynamicFormVersion::class, 'dynamic_form_version_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(DynamicFormSection::class, 'dynamic_form_section_id');
    }
}
