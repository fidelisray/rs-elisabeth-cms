<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Cms\StoreQuestionnaireApiRequest;
use App\Models\QuestionnaireResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class QuestionnaireApiController extends Controller
{
    /**
     * Menyimpan satu pengisian kuesioner kepuasan pasien.
     *
     * Jenis kuesioner (igd, rawat-jalan, rawat-inap) berasal dari URL.
     */
    public function store(StoreQuestionnaireApiRequest $request, string $type): JsonResponse
    {
        if (! array_key_exists($type, QuestionnaireResponse::TYPES)) {
            return response()->json([
                'success' => false,
                'status_code' => Response::HTTP_NOT_FOUND,
                'message' => 'Jenis kuesioner tidak dikenal.',
                'errors' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        $data = $request->validated();

        QuestionnaireResponse::create([
            'questionnaire_type' => $type,
            'age_group' => $data['demographics']['age_group'],
            'gender' => $data['demographics']['gender'],
            'payment_method' => $data['demographics']['payment_method'],
            'ratings' => $data['ratings'],
            'suggestions' => $data['suggestions'] ?? null,
            'locale' => $data['locale'] ?? app()->getLocale(),
            'source' => $data['source'] ?? 'qr_barcode',
            'submitted_at' => $data['submitted_at'] ?? now(),
        ]);

        return response()->json([
            'success' => true,
            'status_code' => 201,
            'message' => 'Kuesioner berhasil dikirimkan.',
        ], JsonResponse::HTTP_CREATED);
    }
}
