<?php

namespace App\Http\Requests\Api\Cms;

use App\Models\QuestionnaireResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class StoreQuestionnaireApiRequest extends FormRequest
{
    public const AGE_GROUPS = ['< 15 tahun', '15 - 64 tahun', '> 64 tahun'];

    public const GENDERS = ['Laki-laki', 'Perempuan'];

    public const PAYMENTS = ['Tunai', 'BPJS Kesehatan', 'Asuransi', 'Jaminan Perusahaan'];

    /**
     * Skala jawaban yang diizinkan. Setiap pertanyaan memakai salah satu skala ini.
     */
    public const SCALES = [
        'Sangat Tidak Puas', 'Tidak Puas', 'Puas', 'Sangat Puas',
        'Sangat Tidak Setuju', 'Tidak Setuju', 'Setuju', 'Sangat Setuju',
        'Sangat Tidak Mudah', 'Tidak Mudah', 'Mudah', 'Sangat Mudah',
        'Sangat Tidak Memadai', 'Tidak Memadai', 'Memadai', 'Sangat Memadai',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Jenis kuesioner diambil dari URL (route parameter), bukan dari body,
     * agar tidak bisa dipalsukan oleh payload.
     */
    public function questionnaireType(): string
    {
        return (string) $this->route('type');
    }

    public function rules(): array
    {
        return [
            'hp_website' => ['nullable', 'max:0'],
            'questionnaire_type' => ['nullable', 'string', Rule::in(array_keys(QuestionnaireResponse::TYPES))],
            'demographics' => ['required', 'array'],
            'demographics.age_group' => ['required', 'string', Rule::in(self::AGE_GROUPS)],
            'demographics.gender' => ['required', 'string', Rule::in(self::GENDERS)],
            'demographics.payment_method' => ['required', 'string', Rule::in(self::PAYMENTS)],
            'ratings' => ['required', 'array', 'min:1', 'max:100'],
            'ratings.*' => ['required', 'string', Rule::in(self::SCALES)],
            'suggestions' => ['nullable', 'string', 'max:2000'],
            'locale' => ['nullable', 'string', 'in:id,en'],
            'source' => ['nullable', 'string', 'in:qr_barcode'],
            'submitted_at' => ['nullable', 'date'],
        ];
    }

    /**
     * Pastikan jenis di body (jika ada) sama dengan jenis di URL.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $bodyType = $this->input('questionnaire_type');

            if ($bodyType !== null && $bodyType !== $this->questionnaireType()) {
                $validator->errors()->add('questionnaire_type', 'Jenis kuesioner di body tidak sama dengan URL.');
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'status_code' => 422,
            'message' => 'Data tidak valid.',
            'errors' => $validator->errors(),
        ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY));
    }
}
