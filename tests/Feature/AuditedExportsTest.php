<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DynamicFormMode;
use App\Exceptions\ExportAbortedException;
use App\Filament\Pages\AuditLogsPage;
use App\Filament\Resources\DynamicForms\Pages\ManageDynamicFormAnalytics;
use App\Filament\Resources\DynamicFormSubmissions\Pages\ListDynamicFormSubmissions;
use App\Filament\Resources\Feedbacks\Pages\ListFeedbacks;
use App\Filament\Resources\Questionnaires\Pages\ListQuestionnaires;
use App\Models\AuditLog;
use App\Models\DynamicForm;
use App\Models\Feedback;
use App\Models\QuestionnaireResponse;
use App\Models\User;
use App\Services\DynamicForm\DynamicFormSubmissionService;
use App\Services\DynamicForm\DynamicFormVersionService;
use App\Services\Export\FeedbackExportService;
use App\Services\Export\QuestionnaireExportService;
use App\Services\Export\SpreadsheetExportWriter;
use App\Support\QuestionnaireDefinition;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Concerns\SeedsTestRolesAndUsers;
use Tests\TestCase;

class AuditedExportsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsTestRolesAndUsers;

    private User $superAdmin;

    private User $marketing;

    private User $staffIt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDefaultTestUsersAndRoles();
        $this->artisan('app:super-admin:init', ['--email' => 'admin@rs-elisabeth.com'])->assertExitCode(0);

        $this->superAdmin = User::where('email', 'admin@rs-elisabeth.com')->firstOrFail();
        $this->marketing = User::where('email', 'marketing@rs-elisabeth.com')->firstOrFail();
        $this->staffIt = User::where('email', 'it@rs-elisabeth.com')->firstOrFail();

        foreach ([$this->superAdmin, $this->marketing, $this->staffIt] as $user) {
            $user->update(['must_change_password' => false]);
            RateLimiter::clear('panel-export:'.$user->id);
        }

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_permission_seeder_registers_export_permissions_idempotently_and_syncs_only_to_super_admin(): void
    {
        foreach (['Export:Feedback', 'Export:QuestionnaireResponse', 'Export:DynamicFormSubmission'] as $permission) {
            $this->assertTrue(Permission::where('name', $permission)->exists(), "Missing permission {$permission}");
            $this->assertTrue($this->superAdmin->can($permission));
            $this->assertFalse($this->marketing->can($permission));
            $this->assertFalse($this->staffIt->can($permission));
        }

        // Jalankan ulang seeder secara idempoten — tidak error dan tidak mencabut izin.
        $this->seed(PermissionSeeder::class);

        $this->assertTrue($this->superAdmin->fresh()->can('Export:Feedback'));
        $this->assertTrue($this->superAdmin->fresh()->can('Export:QuestionnaireResponse'));
        $this->assertTrue($this->superAdmin->fresh()->can('Export:DynamicFormSubmission'));
    }

    public function test_feedback_export_enforces_rbac_formula_sanitization_and_super_admin_internal_note_exclusivity(): void
    {
        $feedback = Feedback::create([
            'ticket_number' => 'FB-20261101-EXP001',
            'reporter_name' => '=CMD|\' /C calc\'!A0',
            'relation_to_patient' => 'Keluarga Pasien',
            'relation_other' => 'Kakak Kandung',
            'patient_name' => 'Pasien Rahasia Satu',
            'phone_number' => '081234567890',
            'categories' => ['Pelayanan Dokter', 'Fasilitas & Kebersihan'],
            'category_other' => '+Parkir',
            'complaint_detail' => '@SUM(A1:A5) Ruang tunggu perlu tambahan kursi.',
            'expected_solution' => '-Tambah kursi antrean',
            'locale' => 'id',
            'source' => 'web',
            'submitted_at' => '2026-11-01 09:30:00',
            'status' => 'diproses',
            'internal_note' => 'CATATAN-INTERNAL-SUPER-ADMIN-ONLY',
        ]);

        // 1. Marketing tanpa Export:Feedback tidak melihat aksi & ditolak oleh service.
        $this->actingAs($this->marketing);

        Livewire::test(ListFeedbacks::class)
            ->assertActionHidden('exportData');

        try {
            app(FeedbackExportService::class)->export(Feedback::query(), $this->marketing, 'csv');
            $this->fail('Expected AuthorizationException for user without Export:Feedback');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        // 2. Berikan Export:Feedback ke Marketing -> boleh ekspor, tetapi TIDAK BOLEH mendapat Catatan Internal.
        $this->marketing->givePermissionTo('Export:Feedback');
        $this->marketing->refresh();

        Livewire::test(ListFeedbacks::class)
            ->assertActionVisible('exportData');

        // Paksa locale EN untuk memastikan header & status file tetap baku Bahasa Indonesia.
        app()->setLocale('en');

        $csvResponse = app(FeedbackExportService::class)->export(
            Feedback::query()->whereKey($feedback->id),
            $this->marketing,
            'csv',
            false,
            ['status' => 'diproses', 'has_search_query' => false],
        );
        $csvOutput = $this->captureStreamedResponse($csvResponse);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csvOutput);
        $this->assertStringContainsString('Nomor Tiket', $csvOutput);
        $this->assertStringContainsString('Nama Pelapor', $csvOutput);
        $this->assertStringContainsString('Diproses', $csvOutput);
        $this->assertStringContainsString('081234567890', $csvOutput);
        $this->assertStringContainsString("'=CMD|", $csvOutput);
        $this->assertStringContainsString("'@SUM(A1:A5)", $csvOutput);
        $this->assertStringContainsString("'-Tambah kursi antrean", $csvOutput);
        $this->assertStringNotContainsString('Catatan Internal', $csvOutput);
        $this->assertStringNotContainsString('CATATAN-INTERNAL-SUPER-ADMIN-ONLY', $csvOutput);

        $marketingLog = AuditLog::where('action', 'report.exported')->latest('id')->firstOrFail();
        $this->assertSame('feedbacks', $marketingLog->metadata['module']);
        $this->assertSame('csv', $marketingLog->metadata['format']);
        $this->assertSame(1, $marketingLog->metadata['row_count']);
        $this->assertFalse($marketingLog->metadata['anonymized']);
        $this->assertFalse($marketingLog->metadata['included_internal_note']);
        $encodedMeta = (string) json_encode($marketingLog->metadata);
        $this->assertStringNotContainsString('Pasien Rahasia Satu', $encodedMeta);
        $this->assertStringNotContainsString('081234567890', $encodedMeta);
        $this->assertStringNotContainsString('CATATAN-INTERNAL-SUPER-ADMIN-ONLY', $encodedMeta);

        // 3. Super Admin ekspor XLSX (tetap di bawah locale EN) -> mendapat Catatan Internal & header baku ID.
        $this->actingAs($this->superAdmin);
        $xlsxResponse = app(FeedbackExportService::class)->export(
            Feedback::query()->whereKey($feedback->id),
            $this->superAdmin,
            'xlsx',
            false,
            ['has_search_query' => false],
        );

        $sheets = $this->readXlsxSheets($xlsxResponse);
        $this->assertSame(['Kritik & Saran'], array_keys($sheets));
        $sheetRows = $sheets['Kritik & Saran'];
        $this->assertCount(2, $sheetRows);
        $this->assertSame([
            'Nomor Tiket',
            'Waktu Kirim',
            'Status',
            'Nama Pelapor',
            'Hubungan dengan Pasien',
            'Nama Pasien',
            'Nomor Telepon',
            'Kategori',
            'Isi Keluhan',
            'Solusi yang Diharapkan',
            'Catatan Internal',
        ], $sheetRows[0]);
        $this->assertSame('FB-20261101-EXP001', $sheetRows[1][0]);
        $this->assertSame('Diproses', $sheetRows[1][2]);
        $this->assertSame('081234567890', $sheetRows[1][6]);
        $this->assertIsString($sheetRows[1][6]);
        $this->assertSame('CATATAN-INTERNAL-SUPER-ADMIN-ONLY', $sheetRows[1][10]);

        $superAdminLog = AuditLog::where('action', 'report.exported')->latest('id')->firstOrFail();
        $this->assertTrue($superAdminLog->metadata['included_internal_note']);

        app()->setLocale('id');
    }

    public function test_feedback_export_anonymized_mode_and_livewire_filters_never_log_table_search_pii(): void
    {
        Feedback::create([
            'ticket_number' => 'FB-20261001-ANON01',
            'reporter_name' => 'Pelapor Siti Rahasia',
            'relation_to_patient' => 'Pasien Sendiri',
            'patient_name' => 'Pasien Siti Rahasia',
            'phone_number' => '081111222333',
            'categories' => ['Administrasi & Pendaftaran'],
            'complaint_detail' => 'Antrean loket pagi panjang.',
            'expected_solution' => 'Tambah loket pendaftaran pagi.',
            'locale' => 'id',
            'source' => 'web',
            'submitted_at' => '2026-10-01 08:15:00',
            'status' => 'baru',
            'internal_note' => 'Evaluasi shift loket.',
        ]);

        Feedback::create([
            'ticket_number' => 'FB-20261020-ANON02',
            'reporter_name' => 'Pelapor Budi Rahasia',
            'relation_to_patient' => 'Pasien Sendiri',
            'patient_name' => 'Pasien Budi Rahasia',
            'phone_number' => '089999888777',
            'categories' => ['Pelayanan Perawat'],
            'complaint_detail' => 'Sudah baik.',
            'expected_solution' => 'Pertahankan kualitas pelayanan.',
            'locale' => 'id',
            'source' => 'web',
            'submitted_at' => '2026-10-20 14:00:00',
            'status' => 'selesai',
        ]);

        $this->actingAs($this->superAdmin);

        // Uji WYSIWYG tableFilters (status + submitted_at) + tableSearch berisi nama pasien.
        Livewire::test(ListFeedbacks::class)
            ->filterTable('status', 'baru')
            ->filterTable('submitted_at', [
                'from' => '2026-10-01',
                'until' => '2026-10-05',
            ])
            ->set('tableSearch', 'Siti Rahasia')
            ->callAction('exportData', data: [
                'format' => 'csv',
                'anonymized' => true,
            ])
            ->assertFileDownloaded();

        $auditLog = AuditLog::where('action', 'report.exported')->latest('id')->firstOrFail();
        $this->assertSame('feedbacks', $auditLog->metadata['module']);
        $this->assertSame(1, $auditLog->metadata['row_count']);
        $this->assertTrue($auditLog->metadata['anonymized']);
        $this->assertSame('baru', $auditLog->metadata['filters']['status']);
        $this->assertSame('2026-10-01', $auditLog->metadata['filters']['date_from']);
        $this->assertSame('2026-10-05', $auditLog->metadata['filters']['date_to']);
        $this->assertTrue($auditLog->metadata['filters']['has_search_query']);

        // INVARIAN KERAS: kata kunci pencarian nama pasien TIDAK BOLEH masuk ke metadata audit log.
        $serialized = (string) json_encode($auditLog->metadata);
        $this->assertStringNotContainsString('Siti Rahasia', $serialized);
        $this->assertStringNotContainsString('Budi Rahasia', $serialized);

        // Pastikan isi CSV Mode Anonim tidak memuat kolom & nilai PII, namun tetap membawa tiket & keluhan.
        $anonCsv = $this->captureStreamedResponse(
            app(FeedbackExportService::class)->export(
                Feedback::query()->where('ticket_number', 'FB-20261001-ANON01'),
                $this->superAdmin,
                'csv',
                true,
            )
        );

        $this->assertStringContainsString('FB-20261001-ANON01', $anonCsv);
        $this->assertStringContainsString('Antrean loket pagi panjang.', $anonCsv);
        $this->assertStringContainsString('Catatan Internal', $anonCsv);
        $this->assertStringNotContainsString('Nama Pelapor', $anonCsv);
        $this->assertStringNotContainsString('Nama Pasien', $anonCsv);
        $this->assertStringNotContainsString('Nomor Telepon', $anonCsv);
        $this->assertStringNotContainsString('Pelapor Siti Rahasia', $anonCsv);
        $this->assertStringNotContainsString('Pasien Siti Rahasia', $anonCsv);
        $this->assertStringNotContainsString('081111222333', $anonCsv);
    }

    public function test_questionnaire_export_supports_adaptive_single_unit_and_all_units_three_worksheets(): void
    {
        $igdRatings = [];
        foreach (QuestionnaireDefinition::QUESTIONS['igd'] as $code => $scaleKey) {
            $igdRatings[$code] = QuestionnaireDefinition::SCALES[$scaleKey][3]; // Sangat Puas / Sangat Setuju / dst
        }

        $rajalRatings = [];
        $i = 0;
        foreach (QuestionnaireDefinition::QUESTIONS['rawat-jalan'] as $code => $scaleKey) {
            $rajalRatings[$code] = $i % 2 === 0
                ? QuestionnaireDefinition::SCALES[$scaleKey][2] // Positif
                : QuestionnaireDefinition::SCALES[$scaleKey][1]; // Negatif
            $i++;
        }

        $ranapRatings = [];
        foreach (QuestionnaireDefinition::QUESTIONS['rawat-inap'] as $code => $scaleKey) {
            $ranapRatings[$code] = QuestionnaireDefinition::SCALES[$scaleKey][2];
        }

        QuestionnaireResponse::create([
            'questionnaire_type' => 'igd',
            'age_group' => '25-44 tahun',
            'gender' => 'Laki-laki',
            'payment_method' => 'BPJS Kesehatan',
            'ratings' => $igdRatings,
            'suggestions' => 'Triase sangat sigap.',
            'locale' => 'id',
            'source' => 'web',
            'submitted_at' => '2026-10-10 10:00:00',
        ]);

        QuestionnaireResponse::create([
            'questionnaire_type' => 'rawat-jalan',
            'age_group' => '45-64 tahun',
            'gender' => 'Perempuan',
            'payment_method' => 'Umum / Mandiri',
            'ratings' => $rajalRatings,
            'suggestions' => 'Parkir perlu diperluas.',
            'locale' => 'id',
            'source' => 'web',
            'submitted_at' => '2026-10-12 11:00:00',
        ]);

        QuestionnaireResponse::create([
            'questionnaire_type' => 'rawat-inap',
            'age_group' => '25-44 tahun',
            'gender' => 'Perempuan',
            'payment_method' => 'Asuransi Swasta',
            'ratings' => $ranapRatings,
            'suggestions' => 'Kamar sangat bersih.',
            'locale' => 'id',
            'source' => 'web',
            'submitted_at' => '2026-10-25 15:00:00',
        ]);

        // 1. Marketing tanpa Export:QuestionnaireResponse tidak melihat tombol & ditolak service.
        $this->actingAs($this->marketing);

        Livewire::test(ListQuestionnaires::class)
            ->assertActionHidden('exportData');

        try {
            app(QuestionnaireExportService::class)->export(QuestionnaireResponse::query(), $this->marketing, 'xlsx');
            $this->fail('Expected AuthorizationException for user without Export:QuestionnaireResponse');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        // 2. Super Admin — Semua Layanan (XLSX) -> 3 worksheet terpisah (IGD, Rawat Jalan, Rawat Inap).
        $this->actingAs($this->superAdmin);

        $allUnitsXlsx = app(QuestionnaireExportService::class)->export(
            QuestionnaireResponse::query(),
            $this->superAdmin,
            'xlsx',
            null,
            ['has_search_query' => false],
        );

        $sheets = $this->readXlsxSheets($allUnitsXlsx);
        $this->assertSame(['IGD', 'Rawat Jalan', 'Rawat Inap'], array_keys($sheets));

        // IGD: 7 kolom dasar + 15 pertanyaan + 1 Saran = 23 kolom; Skor Kepuasan (%) = integer 100.
        $this->assertCount(2, $sheets['IGD']);
        $this->assertCount(23, $sheets['IGD'][0]);
        $this->assertSame('Skor Kepuasan (%)', $sheets['IGD'][0][6]);
        $this->assertSame(100, $sheets['IGD'][1][6]);
        $this->assertIsInt($sheets['IGD'][1][6]);

        // Rawat Jalan: 7 + 11 + 1 = 19 kolom; skor numerik.
        $this->assertCount(2, $sheets['Rawat Jalan']);
        $this->assertCount(19, $sheets['Rawat Jalan'][0]);
        $this->assertIsInt($sheets['Rawat Jalan'][1][6]);

        // Rawat Inap: 7 + 19 + 1 = 27 kolom; skor numerik 100.
        $this->assertCount(2, $sheets['Rawat Inap']);
        $this->assertCount(27, $sheets['Rawat Inap'][0]);
        $this->assertSame(100, $sheets['Rawat Inap'][1][6]);

        // 3. Semua Layanan (CSV) -> header ter-prefix unit [IGD], [Rawat Jalan], [Rawat Inap].
        $allUnitsCsv = $this->captureStreamedResponse(
            app(QuestionnaireExportService::class)->export(
                QuestionnaireResponse::query(),
                $this->superAdmin,
                'csv',
                null,
                ['has_search_query' => false],
            )
        );
        $this->assertStringStartsWith("\xEF\xBB\xBF", $allUnitsCsv);
        $this->assertStringContainsString('[IGD] Kecepatan penanganan saat tiba', $allUnitsCsv);
        $this->assertStringContainsString('[Rawat Jalan] Pelayanan petugas parkir', $allUnitsCsv);
        $this->assertStringContainsString('[Rawat Inap] Kualitas makanan', $allUnitsCsv);

        // 4. Filter 1 Unit (`igd`) + rentang tanggal via Livewire -> 1 sheet presisi & audit log tercatat.
        Livewire::test(ListQuestionnaires::class)
            ->filterTable('questionnaire_type', 'igd')
            ->filterTable('submitted_at', [
                'from' => '2026-10-01',
                'until' => '2026-10-15',
            ])
            ->callAction('exportData', data: ['format' => 'xlsx'])
            ->assertFileDownloaded();

        $singleUnitSheets = $this->readXlsxSheets(
            app(QuestionnaireExportService::class)->export(
                QuestionnaireResponse::query()->where('questionnaire_type', 'igd'),
                $this->superAdmin,
                'xlsx',
                'igd',
                ['questionnaire_type' => 'igd'],
            )
        );
        $this->assertSame(['IGD'], array_keys($singleUnitSheets));
        $this->assertCount(23, $singleUnitSheets['IGD'][0]);

        $auditLog = AuditLog::where('action', 'report.exported')
            ->where('metadata->module', 'questionnaires')
            ->latest('id')
            ->firstOrFail();
        $this->assertSame('igd', $auditLog->metadata['filters']['questionnaire_type']);
        $this->assertSame(1, $auditLog->metadata['row_count']);
    }

    public function test_dynamic_form_submissions_list_export_modal_enforces_rbac_and_audits_without_answers(): void
    {
        $form = DynamicForm::factory()->create([
            'mode' => DynamicFormMode::Questionnaire,
            'title' => 'Survei Mutu Poliklinik',
            'slug' => 'survei-mutu-poliklinik',
        ]);

        $versionService = app(DynamicFormVersionService::class);
        $versionService->syncDraftStructure($form, [[
            'section_key' => 's1',
            'title' => 'Penilaian',
            'fields' => [
                ['field_key' => 'pesan_rahasia', 'type' => 'short_text', 'label' => 'Pesan Pasien'],
            ],
        ]], false);
        $versionService->publishDraftVersion($form);

        app(DynamicFormSubmissionService::class)->submit($form->fresh(), [
            'answers' => ['pesan_rahasia' => 'JAWABAN-RAHASIA-RESPONDEN-999'],
        ]);

        // Tanpa Export:DynamicFormSubmission -> tombol tersembunyi di ListDynamicFormSubmissions & Analytics.
        $this->actingAs($this->marketing);

        Livewire::test(ListDynamicFormSubmissions::class)
            ->assertActionHidden('exportData');

        Livewire::test(ManageDynamicFormAnalytics::class, ['record' => $form->getRouteKey()])
            ->assertActionHidden('exportCsv')
            ->assertActionHidden('exportXlsx');

        // Berikan izin eksplisit -> tombol tampil & ekspor berhasil disertai audit log tanpa jawaban responden.
        $this->marketing->givePermissionTo('Export:DynamicFormSubmission');
        $this->marketing->refresh();

        Livewire::test(ListDynamicFormSubmissions::class)
            ->filterTable('dynamic_form_id', (string) $form->id)
            ->assertActionVisible('exportData')
            ->callAction('exportData', data: [
                'dynamic_form_id' => $form->id,
                'format' => 'xlsx',
            ])
            ->assertFileDownloaded();

        $auditLog = AuditLog::where('action', 'report.exported')
            ->where('subject_type', DynamicForm::class)
            ->where('subject_id', $form->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('dynamic_forms', $auditLog->metadata['module']);
        $this->assertSame('xlsx', $auditLog->metadata['format']);
        $this->assertSame(1, $auditLog->metadata['row_count']);
        $this->assertStringNotContainsString(
            'JAWABAN-RAHASIA-RESPONDEN-999',
            (string) json_encode($auditLog->metadata),
        );
    }

    public function test_export_guards_block_empty_dataset_hard_cap_and_rate_limiter_cleanly(): void
    {
        $this->actingAs($this->superAdmin);

        // 1. Guard 0 baris: tidak menghasilkan unduhan, tidak menulis audit log, dan menampilkan notifikasi warning.
        Livewire::test(ListFeedbacks::class)
            ->filterTable('submitted_at', [
                'from' => '2099-01-01',
                'until' => '2099-01-31',
            ])
            ->callAction('exportData', data: ['format' => 'xlsx', 'anonymized' => false])
            ->assertNotified('Tidak ada data untuk diekspor');

        $this->assertDatabaseMissing('audit_logs', ['action' => 'report.exported']);

        // 2. Guard Hard Cap 10.000 baris.
        $writer = app(SpreadsheetExportWriter::class);

        try {
            $writer->enforceGuards($this->superAdmin->id, SpreadsheetExportWriter::MAX_SYNC_ROWS + 1);
            $this->fail('Expected ExportAbortedException for limit exceeded.');
        } catch (ExportAbortedException $e) {
            $this->assertSame(ExportAbortedException::REASON_LIMIT_EXCEEDED, $e->reason);
        }

        // 3. Guard Rate Limiter 10x/menit/user.
        RateLimiter::clear('panel-export:'.$this->superAdmin->id);
        for ($i = 0; $i < SpreadsheetExportWriter::RATE_LIMIT_MAX_ATTEMPTS; $i++) {
            $writer->enforceGuards($this->superAdmin->id, 1);
        }

        try {
            $writer->enforceGuards($this->superAdmin->id, 1);
            $this->fail('Expected ExportAbortedException for rate limit.');
        } catch (ExportAbortedException $e) {
            $this->assertSame(ExportAbortedException::REASON_RATE_LIMITED, $e->reason);
        }

        // Pastikan di Livewire saat rate limit tercapai, muncul notifikasi ramah tanpa 500 error.
        Feedback::create([
            'ticket_number' => 'FB-20261110-RATE01',
            'reporter_name' => 'Pelapor Uji',
            'relation_to_patient' => 'Pasien Sendiri',
            'patient_name' => 'Pasien Uji',
            'phone_number' => '081200000001',
            'categories' => ['Pelayanan Dokter'],
            'complaint_detail' => 'Uji rate limit.',
            'expected_solution' => 'Respon cepat.',
            'locale' => 'id',
            'source' => 'web',
            'submitted_at' => now(),
            'status' => 'baru',
        ]);

        Livewire::test(ListFeedbacks::class)
            ->callAction('exportData', data: ['format' => 'csv', 'anonymized' => false])
            ->assertNotified('Batas permintaan ekspor tercapai');

        // 4. Pastikan AuditLogsPage mengenali event report.exported tanpa error (badge info).
        AuditLog::record('report.exported', null, [
            'module' => 'feedbacks',
            'format' => 'xlsx',
            'row_count' => 1,
        ]);

        Livewire::test(AuditLogsPage::class)
            ->assertSee('report.exported');
    }

    private function captureStreamedResponse(StreamedResponse $response): string
    {
        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }

    /**
     * @return array<string, list<list<mixed>>>
     */
    private function readXlsxSheets(BinaryFileResponse $response): array
    {
        $path = $response->getFile()->getPathname();
        $this->assertFileExists($path);

        $reader = new XlsxReader;
        $reader->open($path);

        $sheets = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            $rows = [];
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
            $sheets[$sheet->getName()] = $rows;
        }

        $reader->close();
        @unlink($path);

        return $sheets;
    }
}
