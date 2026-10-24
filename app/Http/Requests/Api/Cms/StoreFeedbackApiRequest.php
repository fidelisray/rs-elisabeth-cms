<?php

namespace App\Http\Requests\Api\Cms;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class StoreFeedbackApiRequest extends FormRequest
{
    /**
     * Nilai harus sama persis dengan pilihan di formulir Web Frontend
     * (app/Http/Requests/StoreFeedbackRequest.php dan FeedbackController::index).
     */
    public const RELATIONS = [
        'Diri Sendiri',
        'Orang Tua',
        'Suami/Istri',
        'Saudara Kandung',
        'Anak Kandung',
        'Lainnya',
    ];

    public const CATEGORIES = [
        'Pelayanan Medis (Tindakan medis, penegakan diagnosis, penundaan pelayanan, dll)',
        'Pelayanan Non Medis (Prosedur pendaftaran, pembayaran, klaim asuransi, dll)',
        'Fasilitas & Sarana Prasarana',
        'Tarif dan biaya pemeriksaan',
        'Etika dan perilaku petugas',
        'Lainnya',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Honeypot: field hp_website harus kosong. Bot biasanya mengisinya.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone_number' => preg_replace('/[^0-9+]/', '', (string) $this->input('phone_number', '')),
        ]);
    }

    public function rules(): array
    {
        return [
            'hp_website' => ['nullable', 'max:0'],
            'reporter_name' => ['required', 'string', 'max:150'],
            'relation_to_patient' => ['required', 'string', Rule::in(self::RELATIONS)],
            'relation_other' => ['nullable', 'required_if:relation_to_patient,Lainnya', 'string', 'max:150'],
            'patient_name' => ['required', 'string', 'max:150'],
            'phone_number' => ['required', 'string', 'min:10', 'max:15', 'regex:/^\+?[0-9]{10,15}$/'],
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => ['required', 'string', Rule::in(self::CATEGORIES)],
            'category_other' => ['nullable', 'string', 'max:150'],
            'complaint_detail' => ['required', 'string', 'min:10', 'max:3000'],
            'expected_solution' => ['required', 'string', 'min:5', 'max:1500'],
            'locale' => ['nullable', 'string', 'in:id,en'],
            'source' => ['nullable', 'string', 'in:web_public'],
            'submitted_at' => ['nullable', 'date'],
        ];
    }

    /**
     * Respons validasi JSON standar (422), konsisten dengan API CMS lain.
     */
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
