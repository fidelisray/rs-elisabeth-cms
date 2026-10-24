<?php

namespace Database\Factories;

use App\Models\QuestionnaireResponse;
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
            'ratings' => ['q1' => 'Sangat Puas', 'q2' => 'Puas'],
            'suggestions' => null,
            'locale' => 'id',
            'source' => 'qr_barcode',
            'submitted_at' => now(),
        ];
    }
}
