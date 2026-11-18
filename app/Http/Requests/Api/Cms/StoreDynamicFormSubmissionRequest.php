<?php

namespace App\Http\Requests\Api\Cms;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class StoreDynamicFormSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hp_website' => ['nullable', 'max:0'],
            'version_id' => ['nullable', 'integer', 'min:1'],
            'answers' => ['required', 'array'],
            'locale' => ['nullable', 'string', 'in:id,en'],
            'source' => ['nullable', 'string', 'max:50'],
            'respondent_key' => ['nullable', 'string', 'max:191'],
            'submitted_at' => ['nullable', 'date'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'status_code' => 422,
            'message' => 'Data pengisian tidak valid.',
            'errors' => $validator->errors(),
        ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY));
    }
}
