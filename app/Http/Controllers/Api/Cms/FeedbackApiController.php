<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Cms\StoreFeedbackApiRequest;
use App\Models\Feedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class FeedbackApiController extends Controller
{
    /**
     * Menyimpan kritik dan saran pasien dari Web Frontend.
     *
     * Data pribadi tidak dicatat ke log. Respons hanya memuat nomor tiket.
     */
    public function store(StoreFeedbackApiRequest $request): JsonResponse
    {
        $data = $request->validated();

        $feedback = Feedback::create([
            'ticket_number' => $this->generateTicketNumber(),
            'reporter_name' => $data['reporter_name'],
            'relation_to_patient' => $data['relation_to_patient'],
            'relation_other' => $data['relation_other'] ?? null,
            'patient_name' => $data['patient_name'],
            'phone_number' => $data['phone_number'],
            'categories' => array_values($data['categories']),
            'category_other' => $data['category_other'] ?? null,
            'complaint_detail' => $data['complaint_detail'],
            'expected_solution' => $data['expected_solution'],
            'locale' => $data['locale'] ?? app()->getLocale(),
            'source' => $data['source'] ?? 'web_public',
            'submitted_at' => $data['submitted_at'] ?? now(),
            'status' => 'baru',
        ]);

        return response()->json([
            'success' => true,
            'status_code' => 201,
            'message' => 'Feedback berhasil dikirimkan.',
            'ticket_number' => $feedback->ticket_number,
        ], JsonResponse::HTTP_CREATED);
    }

    /**
     * Nomor tiket unik yang mudah dibaca, contoh: FB-20261024-7K3QZ9.
     */
    private function generateTicketNumber(): string
    {
        do {
            $ticket = 'FB-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (Feedback::withTrashed()->where('ticket_number', $ticket)->exists());

        return $ticket;
    }
}
