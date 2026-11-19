<?php

namespace Tests\Feature\Api\Cms;

use App\Enums\DynamicFormMode;
use App\Enums\FeedbackTicketStatus;
use App\Models\DynamicForm;
use App\Models\DynamicFormSubmission;
use App\Models\DynamicFormSubmissionAnswer;
use App\Models\DynamicFormUpload;
use App\Services\DynamicForm\DynamicFormVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tests\Traits\GeneratesHmacHeaders;

class DynamicFormApiTest extends TestCase
{
    use GeneratesHmacHeaders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.dynamic_form_private_disk' => 'private-test']);
        Storage::fake('private-test');
    }

    private function createPublishedForm(array $formOverrides = [], ?array $sections = null): DynamicForm
    {
        $form = DynamicForm::factory()->create(array_merge([
            'title' => 'Survei Layanan IGD',
            'slug' => 'survei-layanan-igd',
            'mode' => DynamicFormMode::Questionnaire,
            'locale' => 'id',
            'ticket_prefix' => 'IGD',
        ], $formOverrides));

        $versionService = app(DynamicFormVersionService::class);
        $sectionsData = $sections ?? [
            [
                'section_key' => 'sec_utama',
                'title' => 'Penilaian Utama',
                'next_section_rule' => ['action' => 'next', 'target_section_key' => null],
                'fields' => [
                    [
                        'field_key' => 'unit',
                        'type' => 'radio',
                        'label' => 'Unit yang Dikunjungi',
                        'is_required' => true,
                        'options' => [
                            'choices' => [
                                ['key' => 'igd', 'label' => 'IGD'],
                                ['key' => 'poli', 'label' => 'Poliklinik'],
                            ],
                        ],
                    ],
                    [
                        'field_key' => 'skor',
                        'type' => 'linear_scale',
                        'label' => 'Skor Kepuasan',
                        'is_required' => true,
                        'options' => ['min_value' => 1, 'max_value' => 5],
                    ],
                    [
                        'field_key' => 'matriks',
                        'type' => 'matrix_grid',
                        'label' => 'Aspek Pelayanan',
                        'is_required' => false,
                        'options' => [
                            'selection_mode' => 'single',
                            'rows' => [
                                ['key' => 'cepat', 'label' => 'Kecepatan'],
                                ['key' => 'ramah', 'label' => 'Keramahan'],
                            ],
                            'columns' => [
                                ['key' => 'kurang', 'label' => 'Kurang', 'score' => 1],
                                ['key' => 'puas', 'label' => 'Puas', 'score' => 5],
                            ],
                        ],
                    ],
                    [
                        'field_key' => 'lampiran',
                        'type' => 'file_upload',
                        'label' => 'Foto Bukti',
                        'is_required' => false,
                        'options' => [
                            'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png'],
                            'max_file_size_mb' => 2,
                            'max_files' => 2,
                        ],
                    ],
                ],
            ],
        ];

        $versionService->syncDraftStructure($form, $sectionsData, false);
        $versionService->publishDraftVersion($form, 'admin@rs-elisabeth.com');

        return $form->fresh(['publishedVersion']);
    }

    private function signedJsonPost(string $uri, array $payload): TestResponse
    {
        Config::set('cms_api.clients.TESTING-APP', 'TESTING-SECRET-12345');
        Config::set('cms_api.tolerance_minutes', 5);

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $signature = base64_encode(hash_hmac('sha256', $timestamp.'TESTING-APP'.$body, 'TESTING-SECRET-12345', true));

        return $this->call('POST', $uri, [], [], [], [
            'HTTP_X_CONS_ID' => 'TESTING-APP',
            'HTTP_X_TIMESTAMP' => $timestamp,
            'HTTP_X_SIGNATURE' => $signature,
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_ACCEPT_LANGUAGE' => 'id',
            'CONTENT_TYPE' => 'application/json',
        ], $body);
    }

    private function signedFileUpload(string $uri, string $fieldKey, UploadedFile $file, ?string $overrideSha256 = null): TestResponse
    {
        Config::set('cms_api.clients.TESTING-APP', 'TESTING-SECRET-12345');
        Config::set('cms_api.tolerance_minutes', 5);

        $sha256 = $overrideSha256 ?? (string) hash_file('sha256', $file->getRealPath());
        $timestamp = (string) time();
        $signature = base64_encode(hash_hmac('sha256', $timestamp.'TESTING-APP'.$sha256, 'TESTING-SECRET-12345', true));

        return $this->withHeaders([
            'X-Cons-ID' => 'TESTING-APP',
            'X-Timestamp' => $timestamp,
            'X-Signature' => $signature,
            'X-Content-SHA256' => $sha256,
            'Accept' => 'application/json',
        ])->post($uri, [
            'field_key' => $fieldKey,
            'file' => $file,
        ]);
    }

    public function test_list_and_detail_endpoints_enforce_hmac_and_reflect_status_schedule_and_quota(): void
    {
        $this->getJson('/api/v1/cms/forms')->assertStatus(401);

        $active = $this->createPublishedForm([
            'slug' => 'form-aktif',
            'mode' => DynamicFormMode::Feedback,
            'locale' => 'id',
            'max_responses' => 2,
            'is_public_listed' => true,
        ]);

        DynamicForm::factory()->create([
            'slug' => 'form-draft',
            'status' => 'draft',
        ]);

        $unlisted = $this->createPublishedForm([
            'slug' => 'form-qr-khusus',
            'mode' => DynamicFormMode::Feedback,
            'locale' => 'id',
            'is_public_listed' => false,
        ]);

        $expired = $this->createPublishedForm([
            'slug' => 'form-kadaluarsa',
            'ends_at' => now()->subDay(),
            'closed_message' => 'Periode sudah berakhir.',
            'is_public_listed' => true,
        ]);

        $enForm = $this->createPublishedForm([
            'slug' => 'english-survey',
            'locale' => 'en',
            'mode' => DynamicFormMode::Questionnaire,
            'is_public_listed' => true,
        ]);

        $list = $this->withHmac()->getJson('/api/v1/cms/forms?mode=feedback&lang=id')->assertOk();
        $slugs = collect($list->json('data'))->pluck('slug')->all();
        $this->assertSame(['form-aktif'], $slugs);
        $this->assertTrue((bool) $list->json('data.0.is_public_listed'));

        // Unlisted form tidak bocor di ?listed_only=1, muncul di ?include_unlisted=1 tanpa bentrok cache,
        // dan tetap bisa diakses langsung via GET /forms/{slug}.
        $listedOnly = $this->withHmac()->getJson('/api/v1/cms/forms?mode=feedback&lang=id&listed_only=1')->assertOk();
        $this->assertSame(['form-aktif'], collect($listedOnly->json('data'))->pluck('slug')->all());

        $withUnlisted = $this->withHmac()->getJson('/api/v1/cms/forms?mode=feedback&lang=id&include_unlisted=1')->assertOk();
        $withUnlistedSlugs = collect($withUnlisted->json('data'))->pluck('slug')->all();
        $this->assertContains($active->slug, $withUnlistedSlugs);
        $this->assertContains($unlisted->slug, $withUnlistedSlugs);

        $defaultAfterUnlisted = $this->withHmac()->getJson('/api/v1/cms/forms?mode=feedback&lang=id')->assertOk();
        $this->assertSame(['form-aktif'], collect($defaultAfterUnlisted->json('data'))->pluck('slug')->all());

        $this->withHmac()->getJson('/api/v1/cms/forms/'.$unlisted->slug)
            ->assertOk()
            ->assertJsonPath('data.is_public_listed', false)
            ->assertJsonPath('data.is_accepting_responses', true);

        $this->withHmac()->getJson('/api/v1/cms/forms/form-draft')->assertNotFound();

        $detailActive = $this->withHmac()->getJson('/api/v1/cms/forms/'.$active->slug)->assertOk();
        $detailActive->assertJsonPath('data.is_accepting_responses', true);
        $detailActive->assertJsonPath('data.is_public_listed', true);
        $detailActive->assertJsonPath('data.remaining_quota', 2);
        $detailActive->assertJsonPath('data.published_version.version_number', 1);

        $detailExpired = $this->withHmac()->getJson('/api/v1/cms/forms/'.$expired->slug)->assertOk();
        $detailExpired->assertJsonPath('data.is_accepting_responses', false);
        $detailExpired->assertJsonPath('data.closed_reason', 'ended');
        $detailExpired->assertJsonPath('data.closed_message', 'Periode sudah berakhir.');

        $enList = $this->withHmac()->getJson('/api/v1/cms/forms?lang=en')->assertOk();
        $this->assertSame([$enForm->slug], collect($enList->json('data'))->pluck('slug')->all());

        // Pastikan cache tanpa ?lang (semua bahasa) tidak bertabrakan dengan ?lang=id.
        $allList = $this->withHmac()->getJson('/api/v1/cms/forms')->assertOk();
        $allSlugs = collect($allList->json('data'))->pluck('slug')->all();
        $this->assertContains($active->slug, $allSlugs);
        $this->assertContains($enForm->slug, $allSlugs);

        $idOnlyList = $this->withHmac()->getJson('/api/v1/cms/forms?lang=id')->assertOk();
        $this->assertSame([$active->slug], collect($idOnlyList->json('data'))->pluck('slug')->all());
    }

    public function test_two_step_upload_verifies_sha256_hmac_rejects_spoofed_mime_and_binds_to_submission(): void
    {
        $form = $this->createPublishedForm([
            'slug' => 'keluhan-pasien',
            'mode' => DynamicFormMode::Feedback,
            'ticket_prefix' => 'KLP',
        ]);

        // 1. Tampered X-Content-SHA256 ditolak 401
        $validPdf = UploadedFile::fake()->createWithContent('bukti.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n");
        $this->signedFileUpload(
            '/api/v1/cms/forms/'.$form->slug.'/uploads',
            'lampiran',
            $validPdf,
            str_repeat('0', 64)
        )->assertStatus(401);

        // 2. MIME palsu (.jpg berisi script PHP) ditolak 422
        $spoofed = UploadedFile::fake()->createWithContent('shell.jpg', '<?php echo "hacked"; ?>');
        $this->signedFileUpload(
            '/api/v1/cms/forms/'.$form->slug.'/uploads',
            'lampiran',
            $spoofed
        )->assertStatus(422);

        // 3. Upload valid berhasil dan disimpan di disk privat
        $uploadRes = $this->signedFileUpload(
            '/api/v1/cms/forms/'.$form->slug.'/uploads',
            'lampiran',
            $validPdf
        )->assertStatus(201);

        $token = $uploadRes->json('data.upload_token');
        $this->assertNotEmpty($token);
        $uploadRecord = DynamicFormUpload::where('token', $token)->firstOrFail();
        $this->assertNull($uploadRecord->submission_id);
        Storage::disk('private-test')->assertExists($uploadRecord->storage_path);

        // 4. Submit JSON HMAC menyertakan upload_token
        $submitRes = $this->signedJsonPost('/api/v1/cms/forms/'.$form->slug.'/submissions', [
            'version_id' => $form->published_version_id,
            'answers' => [
                'unit' => ['selected' => 'igd'],
                'skor' => 4,
                'matriks' => ['cepat' => 'puas', 'ramah' => 'puas'],
                'lampiran' => [$token],
            ],
        ])->assertStatus(201);

        $ticket = $submitRes->json('data.ticket_number');
        $this->assertStringStartsWith('KLP-', $ticket);

        $submission = DynamicFormSubmission::where('ticket_number', $ticket)->firstOrFail();
        $this->assertSame(FeedbackTicketStatus::Baru, $submission->status);
        $this->assertSame($submission->id, $uploadRecord->fresh()->submission_id);
        $this->assertNotNull($uploadRecord->fresh()->attached_at);

        // Indeks jawaban tertulis bersih termasuk baris matrix_grid
        $this->assertDatabaseHas('dynamic_form_submission_answers', [
            'submission_id' => $submission->id,
            'field_key' => 'matriks',
            'row_key' => 'cepat',
            'value_text' => 'puas',
        ]);
        $this->assertSame(2, DynamicFormSubmissionAnswer::where('submission_id', $submission->id)->where('field_key', 'matriks')->count());

        // 5. Token yang sudah terpakai tidak bisa dipakai ulang
        $this->signedJsonPost('/api/v1/cms/forms/'.$form->slug.'/submissions', [
            'answers' => [
                'unit' => ['selected' => 'igd'],
                'skor' => 5,
                'lampiran' => [$token],
            ],
        ])->assertStatus(422);
    }

    public function test_enforces_honeypot_quota_multiple_submission_dedup_and_keeps_legacy_endpoints_intact(): void
    {
        $form = $this->createPublishedForm([
            'slug' => 'kuota-terbatas',
            'max_responses' => 1,
            'allow_multiple_submissions' => false,
        ]);

        // Honeypot ditolak
        $this->signedJsonPost('/api/v1/cms/forms/'.$form->slug.'/submissions', [
            'hp_website' => 'spam-bot',
            'answers' => ['unit' => ['selected' => 'igd'], 'skor' => 5],
        ])->assertStatus(422);

        // Pengisian pertama berhasil
        $this->signedJsonPost('/api/v1/cms/forms/'.$form->slug.'/submissions', [
            'respondent_key' => 'pasien-001',
            'answers' => ['unit' => ['selected' => 'igd'], 'skor' => 5],
        ])->assertStatus(201);

        // Pengisian kedua ditolak karena kuota penuh
        $this->signedJsonPost('/api/v1/cms/forms/'.$form->slug.'/submissions', [
            'respondent_key' => 'pasien-002',
            'answers' => ['unit' => ['selected' => 'poli'], 'skor' => 4],
        ])->assertStatus(422);

        // Deduplikasi tanpa batas kuota
        $dedupForm = $this->createPublishedForm([
            'slug' => 'sekali-isi',
            'max_responses' => 10,
            'allow_multiple_submissions' => false,
        ]);

        $this->signedJsonPost('/api/v1/cms/forms/'.$dedupForm->slug.'/submissions', [
            'respondent_key' => 'Pasien@Example.com',
            'answers' => ['unit' => ['selected' => 'igd'], 'skor' => 5],
        ])->assertStatus(201);

        $this->signedJsonPost('/api/v1/cms/forms/'.$dedupForm->slug.'/submissions', [
            'respondent_key' => 'pasien@example.com',
            'answers' => ['unit' => ['selected' => 'igd'], 'skor' => 3],
        ])->assertStatus(422);

        // Endpoint lama tetap berfungsi penuh berdampingan
        $this->signedJsonPost('/api/v1/cms/feedbacks', [
            'reporter_name' => 'Ani',
            'relation_to_patient' => 'Diri Sendiri',
            'patient_name' => 'Ani',
            'phone_number' => '081234567890',
            'categories' => ['Lainnya'],
            'complaint_detail' => 'Waktu tunggu perlu ditingkatkan.',
            'expected_solution' => 'Tambah loket antrean.',
        ])->assertStatus(201);
    }

    public function test_cleanup_command_removes_only_expired_orphan_uploads(): void
    {
        $form = $this->createPublishedForm(['slug' => 'cleanup-test']);

        Storage::disk('private-test')->put('dynamic-forms/orphan-expired.pdf', 'expired');
        Storage::disk('private-test')->put('dynamic-forms/orphan-fresh.pdf', 'fresh');

        $expired = DynamicFormUpload::create([
            'token' => 'expired-token',
            'dynamic_form_id' => $form->id,
            'field_key' => 'lampiran',
            'disk' => 'private-test',
            'storage_path' => 'dynamic-forms/orphan-expired.pdf',
            'original_name' => 'expired.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 7,
            'expires_at' => now()->subHours(2),
        ]);

        $fresh = DynamicFormUpload::create([
            'token' => 'fresh-token',
            'dynamic_form_id' => $form->id,
            'field_key' => 'lampiran',
            'disk' => 'private-test',
            'storage_path' => 'dynamic-forms/orphan-fresh.pdf',
            'original_name' => 'fresh.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 5,
            'expires_at' => now()->addHours(12),
        ]);

        $this->artisan('app:dynamic-forms:cleanup-uploads')->assertExitCode(0);

        $this->assertDatabaseMissing('dynamic_form_uploads', ['id' => $expired->id]);
        $this->assertDatabaseHas('dynamic_form_uploads', ['id' => $fresh->id]);
        Storage::disk('private-test')->assertMissing('dynamic-forms/orphan-expired.pdf');
        Storage::disk('private-test')->assertExists('dynamic-forms/orphan-fresh.pdf');
    }

    public function test_rejects_stale_version_id_future_schedule_closed_form_and_oversized_or_bad_extension_upload(): void
    {
        $form = $this->createPublishedForm([
            'slug' => 'jadwal-dan-versi',
            'max_responses' => 1,
        ]);

        // Tambah versi v2 yang terpublikasi sehingga v1 menjadi stale
        $v1Id = $form->published_version_id;
        $versionService = app(DynamicFormVersionService::class);
        $versionService->syncDraftStructure($form, [[
            'section_key' => 'sec_utama',
            'title' => 'Penilaian v2',
            'fields' => [
                ['field_key' => 'unit', 'type' => 'radio', 'label' => 'Unit', 'is_required' => true, 'options' => ['choices' => [['key' => 'igd', 'label' => 'IGD']]]],
                ['field_key' => 'skor', 'type' => 'linear_scale', 'label' => 'Skor', 'is_required' => true, 'options' => ['min_value' => 1, 'max_value' => 5]],
                ['field_key' => 'lampiran', 'type' => 'file_upload', 'label' => 'Bukti', 'is_required' => false, 'options' => ['allowed_extensions' => ['pdf'], 'max_file_size_mb' => 1, 'max_files' => 1]],
            ],
        ]], false);
        $v2 = $versionService->publishDraftVersion($form->fresh());

        // Kirim dengan version_id v1 (stale) ditolak 422
        $this->signedJsonPost('/api/v1/cms/forms/'.$form->slug.'/submissions', [
            'version_id' => $v1Id,
            'answers' => ['unit' => ['selected' => 'igd'], 'skor' => 5],
        ])->assertStatus(422);

        // Upload ekstensi tidak diizinkan (.png padahal hanya pdf) ditolak 422
        $png = UploadedFile::fake()->image('foto.png');
        $this->signedFileUpload('/api/v1/cms/forms/'.$form->slug.'/uploads', 'lampiran', $png)->assertStatus(422);

        // Submit valid pada v2 memenuhi kuota (1) -> GET schema menampilkan quota_full
        $this->signedJsonPost('/api/v1/cms/forms/'.$form->slug.'/submissions', [
            'version_id' => $v2->id,
            'answers' => ['unit' => ['selected' => 'igd'], 'skor' => 5],
        ])->assertStatus(201);

        $this->withHmac()->getJson('/api/v1/cms/forms/'.$form->slug)
            ->assertOk()
            ->assertJsonPath('data.is_accepting_responses', false)
            ->assertJsonPath('data.closed_reason', 'quota_full')
            ->assertJsonPath('data.remaining_quota', 0);

        // Form belum mulai (starts_at di masa depan)
        $futureForm = $this->createPublishedForm([
            'slug' => 'belum-buka',
            'starts_at' => now()->addDay(),
        ]);
        $this->withHmac()->getJson('/api/v1/cms/forms/'.$futureForm->slug)
            ->assertOk()
            ->assertJsonPath('data.is_accepting_responses', false)
            ->assertJsonPath('data.closed_reason', 'not_started');
        $this->signedJsonPost('/api/v1/cms/forms/'.$futureForm->slug.'/submissions', [
            'answers' => ['unit' => ['selected' => 'igd'], 'skor' => 5],
        ])->assertStatus(422);
    }

    public function test_numeric_indexing_skips_phone_and_text_fields_and_closed_form_upload_returns_422(): void
    {
        $form = $this->createPublishedForm([
            'slug' => 'kontak-dan-angka',
            'closed_message' => 'Formulir sedang ditutup sementara.',
        ], [[
            'section_key' => 'sec_1',
            'title' => 'Data',
            'fields' => [
                [
                    'field_key' => 'telepon',
                    'type' => 'short_text',
                    'label' => 'Nomor Telepon',
                    'is_required' => true,
                    'options' => ['subtype' => 'phone'],
                ],
                [
                    'field_key' => 'nik_teks',
                    'type' => 'short_text',
                    'label' => 'NIK',
                    'is_required' => true,
                    'options' => ['subtype' => 'text'],
                ],
                [
                    'field_key' => 'usia',
                    'type' => 'short_text',
                    'label' => 'Usia',
                    'is_required' => true,
                    'options' => ['subtype' => 'number'],
                ],
                [
                    'field_key' => 'berkas',
                    'type' => 'file_upload',
                    'label' => 'Berkas',
                    'is_required' => false,
                    'options' => ['allowed_extensions' => ['pdf'], 'max_file_size_mb' => 2, 'max_files' => 1],
                ],
            ],
        ]]);

        $res = $this->signedJsonPost('/api/v1/cms/forms/'.$form->slug.'/submissions', [
            'answers' => [
                'telepon' => '081234567890',
                'nik_teks' => '3374010101900001',
                'usia' => 34,
            ],
        ])->assertStatus(201);

        $subId = $res->json('data.submission_id');
        $this->assertDatabaseHas('dynamic_form_submission_answers', [
            'submission_id' => $subId,
            'field_key' => 'telepon',
            'value_text' => '081234567890',
            'value_number' => null,
        ]);
        $this->assertDatabaseHas('dynamic_form_submission_answers', [
            'submission_id' => $subId,
            'field_key' => 'nik_teks',
            'value_text' => '3374010101900001',
            'value_number' => null,
        ]);
        $this->assertDatabaseHas('dynamic_form_submission_answers', [
            'submission_id' => $subId,
            'field_key' => 'usia',
            'value_number' => 34.0,
        ]);

        // Tutup form -> upload harus mengembalikan 422 dengan closed_message (selaras dengan /submissions).
        $versionService = app(DynamicFormVersionService::class);
        $versionService->closeForm($form);

        $pdf = UploadedFile::fake()->createWithContent('dok.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n");
        $this->signedFileUpload('/api/v1/cms/forms/'.$form->slug.'/uploads', 'berkas', $pdf)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Formulir sedang ditutup sementara.');
    }

    public function test_allows_empty_answers_object_when_all_visible_fields_are_optional(): void
    {
        $form = $this->createPublishedForm([
            'slug' => 'opsional-saja',
        ], [[
            'section_key' => 'sec_1',
            'title' => 'Opsional',
            'fields' => [
                [
                    'field_key' => 'catatan',
                    'type' => 'short_text',
                    'label' => 'Catatan Opsional',
                    'is_required' => false,
                    'options' => ['subtype' => 'text'],
                ],
            ],
        ]]);

        $this->signedJsonPost('/api/v1/cms/forms/'.$form->slug.'/submissions', [
            'version_id' => $form->published_version_id,
            'answers' => (object) [],
        ])->assertStatus(201)->assertJsonPath('success', true);

        // Menghilangkan key answers sepenuhnya tetap ditolak 422 dengan pesan terjemahan.
        $this->signedJsonPost('/api/v1/cms/forms/'.$form->slug.'/submissions', [
            'version_id' => $form->published_version_id,
        ])->assertStatus(422)->assertJsonPath('errors.answers.0', 'Jawaban formulir wajib disertakan.');

        // Form/submission locale 'en' mengembalikan pesan sistem duplikat berbahasa Inggris.
        $enDedup = $this->createPublishedForm([
            'slug' => 'en-once',
            'locale' => 'en',
            'allow_multiple_submissions' => false,
        ]);
        $this->signedJsonPost('/api/v1/cms/forms/'.$enDedup->slug.'/submissions', [
            'version_id' => $enDedup->published_version_id,
            'locale' => 'en',
            'respondent_key' => 'en-user-1',
            'answers' => ['unit' => ['selected' => 'igd'], 'skor' => 5],
        ])->assertStatus(201);

        $this->signedJsonPost('/api/v1/cms/forms/'.$enDedup->slug.'/submissions', [
            'version_id' => $enDedup->published_version_id,
            'locale' => 'en',
            'respondent_key' => 'en-user-1',
            'answers' => ['unit' => ['selected' => 'igd'], 'skor' => 4],
        ])->assertStatus(422)->assertJsonPath('errors.respondent_key.0', 'You have already submitted this form.');
    }
}
