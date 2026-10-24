<?php

namespace Database\Factories;

use App\Models\QuestionnaireResponse;
use App\Support\QuestionnaireDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuestionnaireResponse>
 */
class QuestionnaireResponseFactory extends Factory
{
    protected $model = QuestionnaireResponse::class;

    public function definition(): array
    {
        return [
            'questionnaire_type' => 'rawat-jalan',
            'age_group' => '15 - 64 tahun',
            'gender' => 'Perempuan',
            'payment_method' => 'BPJS Kesehatan',
            'ratings' => fn (array $attributes) => collect(QuestionnaireDefinition::QUESTIONS[$attributes['questionnaire_type']])
                ->map(fn ($scale) => QuestionnaireDefinition::SCALES[$scale][3])->all(),
            'suggestions' => 'Saran peningkatan layanan.',
            'locale' => 'id',
            'source' => 'qr_barcode',
            'submitted_at' => now(),
        ];
    }
}
