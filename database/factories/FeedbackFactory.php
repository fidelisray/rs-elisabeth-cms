<?php

namespace Database\Factories;

use App\Models\Feedback;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Feedback>
 */
class FeedbackFactory extends Factory
{
    protected $model = Feedback::class;

    public function definition(): array
    {
        return [
            'ticket_number' => 'FB-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'reporter_name' => fake('id_ID')->name(),
            'relation_to_patient' => 'Orang Tua',
            'relation_other' => null,
            'patient_name' => fake('id_ID')->name(),
            'phone_number' => '081234567890',
            'categories' => ['Pelayanan Non Medis (Prosedur pendaftaran, pembayaran, klaim asuransi, dll)'],
            'category_other' => null,
            'complaint_detail' => 'Waktu tunggu di poliklinik terlalu lama.',
            'expected_solution' => 'Penambahan petugas pendaftaran.',
            'locale' => 'id',
            'source' => 'web_public',
            'submitted_at' => now(),
            'status' => 'baru',
        ];
    }
}
