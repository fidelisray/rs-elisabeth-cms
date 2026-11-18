<?php

namespace Database\Factories;

use App\Models\DynamicForm;
use App\Models\DynamicFormVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DynamicFormVersion>
 */
class DynamicFormVersionFactory extends Factory
{
    protected $model = DynamicFormVersion::class;

    public function definition(): array
    {
        return [
            'dynamic_form_id' => DynamicForm::factory(),
            'version_number' => 1,
            'status' => 'draft',
            'schema_snapshot' => ['sections' => []],
        ];
    }
}
