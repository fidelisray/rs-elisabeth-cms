<?php

namespace Tests\Feature\Api\Cms;

use App\Models\Feedback;
use App\Models\QuestionnaireResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tests\Traits\GeneratesHmacHeaders;

/**
 * Endpoint POST feedback dan kuesioner dari Web Frontend.
 * Signature HMAC menyertakan body untuk POST.
 */
class FeedbackQuestionnaireApiTest extends TestCase
{
    use GeneratesHmacHeaders;
    use RefreshDatabase;

    private function feedbackPayload(array $override = []): array
    {
        return array_merge([
            'reporter_name' => 'Budi Santoso',
            'relation_to_patient' => 'Orang Tua',
            'patient_name' => 'Siti Aminah',
            'phone_number' => '081234567890',
            'categories' => ['Pelayanan Non Medis (Prosedur pendaftaran, pembayaran, klaim asuransi, dll)'],
            'complaint_detail' => 'Waktu tunggu poliklinik terlalu lama sekali.',
            'expected_solution' => 'Tambah petugas pendaftaran.',
            'locale' => 'id',
            'source' => 'web_public',
        ], $override);
    }

    private function questionnairePayload(array $override = []): array
    {
        return array_merge([
            'demographics' => [
                'age_group' => '15 - 64 tahun',
                'gender' => 'Perempuan',
                'payment_method' => 'BPJS Kesehatan',
            ],
            'ratings' => ['q1' => 'Sangat Puas', 'q2' => 'Puas', 'q3' => 'Sangat Mudah'],
            'suggestions' => 'Tambahkan kursi tunggu.',
            'locale' => 'id',
            'source' => 'qr_barcode',
        ], $override);
    }

    /**
     * Tanda tangan POST: HMAC atas timestamp.consId.body (body mentah).
     */
    private function signedPost(string $uri, array $payload, ?string $tamperedBody = null): TestResponse
    {
        Config::set('cms_api.clients.TESTING-APP', 'TESTING-SECRET-12345');
        Config::set('cms_api.tolerance_minutes', 5);

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $signature = base64_encode(hash_hmac('sha256', $timestamp.'TESTING-APP'.$body, 'TESTING-SECRET-12345', true));

        // Body yang dikirim boleh berbeda dari body yang ditandatangani (untuk uji tamper).
        $sent = $tamperedBody ?? $body;

        return $this->call('POST', $uri, [], [], [], [
            'HTTP_X_CONS_ID' => 'TESTING-APP',
            'HTTP_X_TIMESTAMP' => $timestamp,
            'HTTP_X_SIGNATURE' => $signature,
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_ACCEPT_LANGUAGE' => 'id',
            'CONTENT_TYPE' => 'application/json',
        ], $sent);
    }

    public function test_feedback_is_stored_and_returns_ticket_number(): void
    {
        $response = $this->signedPost('/api/v1/cms/feedbacks', $this->feedbackPayload());

        $response->assertStatus(201)->assertJsonPath('success', true);
        $ticket = $response->json('ticket_number');
        $this->assertStringStartsWith('FB-', $ticket);

        $this->assertDatabaseHas('feedbacks', [
            'ticket_number' => $ticket,
            'patient_name' => 'Siti Aminah',
            'status' => 'baru',
        ]);
    }

    public function test_feedback_rejects_unknown_category(): void
    {
        $response = $this->signedPost('/api/v1/cms/feedbacks', $this->feedbackPayload([
            'categories' => ['Kategori Palsu'],
        ]));

        $response->assertStatus(422)->assertJsonPath('success', false);
        $this->assertDatabaseCount('feedbacks', 0);
    }

    public function test_feedback_rejects_when_honeypot_is_filled(): void
    {
        $response = $this->signedPost('/api/v1/cms/feedbacks', $this->feedbackPayload([
            'hp_website' => 'bot',
        ]));

        $response->assertStatus(422);
        $this->assertDatabaseCount('feedbacks', 0);
    }

    public function test_feedback_normalizes_phone_number_and_requires_minimum_digits(): void
    {
        $ok = $this->signedPost('/api/v1/cms/feedbacks', $this->feedbackPayload([
            'phone_number' => '0812-3456-7890',
        ]));
        $ok->assertStatus(201);
        $this->assertDatabaseHas('feedbacks', ['phone_number' => '081234567890']);

        $bad = $this->signedPost('/api/v1/cms/feedbacks', $this->feedbackPayload([
            'phone_number' => '123',
        ]));
        $bad->assertStatus(422);
    }

    public function test_feedback_rejects_tampered_body_with_valid_headers(): void
    {
        $original = json_encode($this->feedbackPayload(), JSON_THROW_ON_ERROR);
        $tampered = json_encode($this->feedbackPayload(['patient_name' => 'Orang Lain']), JSON_THROW_ON_ERROR);

        // Header ditandatangani untuk $original, body yang dikirim $tampered.
        $response = $this->signedPost('/api/v1/cms/feedbacks', $this->feedbackPayload(), $tampered);

        $response->assertStatus(401);
        $this->assertDatabaseCount('feedbacks', 0);
        $this->assertNotSame($original, $tampered);
    }

    public function test_feedback_is_rejected_without_valid_hmac(): void
    {
        $response = $this->postJson('/api/v1/cms/feedbacks', $this->feedbackPayload());

        $response->assertStatus(401);
        $this->assertDatabaseCount('feedbacks', 0);
    }

    public function test_feedback_personal_data_is_not_written_to_log(): void
    {
        Log::spy();

        $this->signedPost('/api/v1/cms/feedbacks', $this->feedbackPayload());

        Log::shouldNotHaveReceived('info', fn ($message) => str_contains((string) $message, 'Siti Aminah'));
        Log::shouldNotHaveReceived('error', fn ($message) => str_contains((string) $message, 'Siti Aminah'));
    }

    public function test_questionnaire_igd_is_stored(): void
    {
        $response = $this->signedPost('/api/v1/cms/questionnaires/igd', $this->questionnairePayload());

        $response->assertStatus(201)->assertJsonPath('success', true);
        $this->assertDatabaseHas('questionnaire_responses', [
            'questionnaire_type' => 'igd',
            'gender' => 'Perempuan',
        ]);
    }

    public function test_questionnaire_rawat_jalan_and_rawat_inap_use_url_format(): void
    {
        $this->signedPost('/api/v1/cms/questionnaires/rawat-jalan', $this->questionnairePayload())->assertStatus(201);
        $this->signedPost('/api/v1/cms/questionnaires/rawat-inap', $this->questionnairePayload())->assertStatus(201);

        $this->assertDatabaseHas('questionnaire_responses', ['questionnaire_type' => 'rawat-jalan']);
        $this->assertDatabaseHas('questionnaire_responses', ['questionnaire_type' => 'rawat-inap']);
    }

    public function test_questionnaire_rejects_unknown_type_in_url(): void
    {
        $response = $this->signedPost('/api/v1/cms/questionnaires/rawat_jalan', $this->questionnairePayload());

        $response->assertStatus(404);
        $this->assertDatabaseCount('questionnaire_responses', 0);
    }

    public function test_questionnaire_rejects_body_type_mismatch(): void
    {
        $response = $this->signedPost('/api/v1/cms/questionnaires/igd', $this->questionnairePayload([
            'questionnaire_type' => 'rawat-jalan',
        ]));

        $response->assertStatus(422);
        $this->assertDatabaseCount('questionnaire_responses', 0);
    }

    public function test_questionnaire_rejects_unknown_rating_scale(): void
    {
        $response = $this->signedPost('/api/v1/cms/questionnaires/igd', $this->questionnairePayload([
            'ratings' => ['q1' => 'Bagus Sekali'],
        ]));

        $response->assertStatus(422);
        $this->assertDatabaseCount('questionnaire_responses', 0);
    }

    public function test_questionnaire_is_rejected_without_valid_hmac(): void
    {
        $response = $this->postJson('/api/v1/cms/questionnaires/igd', $this->questionnairePayload());

        $response->assertStatus(401);
        $this->assertDatabaseCount('questionnaire_responses', 0);
    }

    public function test_get_requests_still_accept_legacy_signature_without_body(): void
    {
        // Klien GET lama: signature hanya timestamp.consId (body kosong). Tetap valid.
        $this->withHmac()->getJson('/api/v1/cms/doctors')->assertStatus(200);
    }

    public function test_models_use_soft_delete_and_hard_delete_guard(): void
    {
        $feedback = Feedback::factory()->create();
        $feedback->delete();
        $this->assertSoftDeleted('feedbacks', ['id' => $feedback->id]);

        $questionnaire = QuestionnaireResponse::factory()->create();
        $questionnaire->delete();
        $this->assertSoftDeleted('questionnaire_responses', ['id' => $questionnaire->id]);
    }
}
