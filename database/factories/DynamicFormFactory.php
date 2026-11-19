<?php

namespace Database\Factories;

use App\Enums\DynamicFormMode;
use App\Enums\DynamicFormStatus;
use App\Models\DynamicForm;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DynamicForm>
 */
class DynamicFormFactory extends Factory
{
    protected $model = DynamicForm::class;

    public function definition(): array
    {
        $title = 'Formulir '.$this->faker->unique()->words(3, true);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(4)),
            'description' => 'Deskripsi formulir dinamis rumah sakit.',
            'mode' => DynamicFormMode::Questionnaire,
            'locale' => 'id',
            'status' => DynamicFormStatus::Draft,
            'allow_multiple_submissions' => true,
            'is_public_listed' => false,
            'ticket_prefix' => 'DFB',
            'created_by' => 'system',
        ];
    }

    public function feedback(): static
    {
        return $this->state(fn () => [
            'mode' => DynamicFormMode::Feedback,
            'ticket_prefix' => 'DFB',
        ]);
    }
}
