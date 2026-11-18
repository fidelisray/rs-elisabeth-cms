<?php

namespace Tests\Feature;

use App\Actions\FollowUpDynamicFormSubmission;
use App\Actions\HardDeleteContent;
use App\Enums\DynamicFormMode;
use App\Enums\DynamicFormStatus;
use App\Enums\FeedbackTicketStatus;
use App\Filament\Resources\DynamicForms\DynamicFormResource;
use App\Filament\Resources\DynamicForms\Pages\CreateDynamicForm;
use App\Filament\Resources\DynamicForms\Pages\EditDynamicForm;
use App\Filament\Resources\DynamicForms\Pages\ListDynamicForms;
use App\Filament\Resources\DynamicForms\Pages\ManageDynamicFormAnalytics;
use App\Filament\Resources\DynamicFormSubmissions\DynamicFormSubmissionResource;
use App\Filament\Resources\DynamicFormSubmissions\Pages\ListDynamicFormSubmissions;
use App\Filament\Resources\DynamicFormSubmissions\Pages\ViewDynamicFormSubmission;
use App\Filament\Widgets\DynamicFeedbackOverviewWidget;
use App\Livewire\DynamicFormLivePreview;
use App\Models\AuditLog;
use App\Models\DynamicForm;
use App\Models\DynamicFormSubmission;
use App\Models\DynamicFormUpload;
use App\Models\Role;
use App\Models\User;
use App\Services\DynamicForm\DynamicFormExportService;
use App\Services\DynamicForm\DynamicFormLogicEvaluator;
use App\Services\DynamicForm\DynamicFormSubmissionService;
use App\Services\DynamicForm\DynamicFormVersionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DynamicFormPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $marketing;

    private User $staffIt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->artisan('app:super-admin:init', ['--email' => 'admin@rs-elisabeth.com'])->assertExitCode(0);

        $this->superAdmin = User::where('email', 'admin@rs-elisabeth.com')->firstOrFail();
        $this->marketing = User::where('email', 'marketing@rs-elisabeth.com')->firstOrFail();
        $this->staffIt = User::where('email', 'it@rs-elisabeth.com')->firstOrFail();

        foreach ([$this->superAdmin, $this->marketing, $this->staffIt] as $user) {
            $user->update(['must_change_password' => false]);
        }

        config(['filesystems.dynamic_form_private_disk' => 'private-test']);
        Storage::fake('private-test');
    }

    public function test_wizard_creation_versioned_draft_editing_and_publishing_workflow(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 200)]);
        Config::set('web_frontend.webhook_url', 'http://localhost/webhook');

        $this->actingAs($this->marketing);

        // 1. Buat form melalui 4-Step Wizard dan langsung publikasikan
        Livewire::test(CreateDynamicForm::class)
            ->fillForm([
                'title' => 'Survei Poliklinik',
                'slug' => 'survei-poliklinik',
                'mode' => DynamicFormMode::Questionnaire->value,
                'locale' => 'id',
                'sections' => [
                    [
                        'section_key' => 'sec_awal',
                        'title' => 'Bagian Awal',
                        'fields' => [
                            [
                                'field_key' => 'nilai',
                                'type' => 'rating',
                                'label' => 'Penilaian Layanan',
                                'is_required' => true,
                                'options' => ['max_stars' => 5, 'icon' => 'star'],
                            ],
                        ],
                    ],
                ],
                'logic_config' => [
                    'section_jumps' => [],
                    'choice_jumps' => [],
                    'visibility_rules' => [],
                ],
                'allow_multiple_submissions' => true,
                'publish_now' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $form = DynamicForm::where('slug', 'survei-poliklinik')->firstOrFail();
        $this->assertSame(DynamicFormStatus::Published, $form->status);
        $this->assertNotNull($form->published_version_id);
        $v1 = $form->publishedVersion;
        $this->assertSame(1, $v1->version_number);

        // Terima 1 submission pada v1
        $submission = app(DynamicFormSubmissionService::class)->submit($form, [
            'answers' => ['nilai' => 5],
        ]);
        $this->assertSame($v1->id, $submission->dynamic_form_version_id);

        // 2. Edit form yang sudah terpublikasi -> menyimpan ke draf v2 tanpa mengubah snapshot v1
        Livewire::test(EditDynamicForm::class, ['record' => $form->getRouteKey()])
            ->fillForm([
                'title' => 'Survei Poliklinik',
                'slug' => 'survei-poliklinik',
                'mode' => DynamicFormMode::Questionnaire->value,
                'locale' => 'id',
                'sections' => [
                    [
                        'section_key' => 'sec_awal',
                        'title' => 'Bagian Awal',
                        'fields' => [
                            [
                                'field_key' => 'nilai',
                                'type' => 'rating',
                                'label' => 'Penilaian Layanan (Diperbarui)',
                                'is_required' => true,
                                'options' => ['max_stars' => 5, 'icon' => 'star'],
                            ],
                            [
                                'field_key' => 'saran',
                                'type' => 'long_text',
                                'label' => 'Saran Tambahan',
                                'is_required' => false,
                            ],
                        ],
                    ],
                ],
                'publish_now' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $form->refresh();
        $this->assertSame($v1->id, $form->published_version_id);
        $draftV2 = $form->latestDraftVersion();
        $this->assertNotNull($draftV2);
        $this->assertSame(2, $draftV2->version_number);
        $this->assertCount(1, $v1->fresh()->schema_snapshot['sections'][0]['fields']);

        // 3. Publikasikan draf v2 -> v1 diarsipkan, v2 menjadi aktif
        app(DynamicFormVersionService::class)->publishDraftVersion($form);
        $form->refresh();
        $this->assertSame($draftV2->id, $form->published_version_id);
        $this->assertSame('archived', $v1->fresh()->status);
        $this->assertSame('published', $draftV2->fresh()->status);
    }

    public function test_interactive_live_preview_evaluates_visibility_and_section_jumps_without_persisting(): void
    {
        $this->actingAs($this->marketing);

        $form = DynamicForm::factory()->create(['title' => 'Form Pratinjau', 'slug' => 'form-pratinjau']);
        $versionService = app(DynamicFormVersionService::class);
        $versionService->syncDraftStructure($form, [
            [
                'section_key' => 'sec_1',
                'title' => 'Identifikasi',
                'fields' => [
                    [
                        'field_key' => 'layanan',
                        'type' => 'radio',
                        'label' => 'Pilih Layanan',
                        'is_required' => true,
                        'options' => [
                            'choices' => [
                                ['key' => 'igd', 'label' => 'IGD', 'go_to_section_key' => 'sec_3'],
                                ['key' => 'poli', 'label' => 'Poliklinik', 'go_to_section_key' => null],
                            ],
                        ],
                    ],
                    [
                        'field_key' => 'catatan_poli',
                        'type' => 'short_text',
                        'label' => 'Nama Poliklinik',
                        'is_required' => true,
                        'conditional_logic' => [
                            'visibility' => [
                                'enabled' => true,
                                'match' => 'all',
                                'rules' => [['source_field_key' => 'layanan', 'operator' => 'equals', 'value' => 'poli']],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'section_key' => 'sec_2',
                'title' => 'Detail Poliklinik',
                'next_section_rule' => ['action' => 'submit', 'target_section_key' => '__submit__'],
                'fields' => [
                    ['field_key' => 'antrean', 'type' => 'rating', 'label' => 'Skor Antrean', 'is_required' => true],
                ],
            ],
            [
                'section_key' => 'sec_3',
                'title' => 'Detail IGD',
                'fields' => [
                    ['field_key' => 'triase', 'type' => 'rating', 'label' => 'Kecepatan Triase', 'is_required' => true],
                ],
            ],
        ], false);

        Livewire::test(DynamicFormLivePreview::class, ['formId' => $form->id])
            ->call('nextSection')
            ->assertSet('isCompleted', false)
            ->assertSee('Pilih Layanan')
            ->set('answers.layanan.selected', 'igd')
            ->call('nextSection')
            ->assertSee('Detail IGD')
            ->assertDontSee('Detail Poliklinik')
            ->set('answers.triase', 5)
            ->call('nextSection')
            ->assertSet('isCompleted', true)
            ->assertSee('Simulasi Pengisian Berhasil');

        $this->assertDatabaseCount('dynamic_form_submissions', 0);
    }

    public function test_analytics_summary_filters_and_csv_xlsx_exports_work_with_real_data(): void
    {
        $this->actingAs($this->marketing);

        $form = DynamicForm::factory()->feedback()->create([
            'title' => 'Masukan Pasien',
            'slug' => 'masukan-pasien',
        ]);

        $versionService = app(DynamicFormVersionService::class);
        $versionService->syncDraftStructure($form, [
            [
                'section_key' => 'sec_1',
                'title' => 'Penilaian',
                'fields' => [
                    [
                        'field_key' => 'unit',
                        'type' => 'radio',
                        'label' => 'Unit',
                        'is_required' => true,
                        'options' => ['choices' => [['key' => 'igd', 'label' => 'IGD'], ['key' => 'ranap', 'label' => 'Rawat Inap']]],
                    ],
                    [
                        'field_key' => 'rating_rs',
                        'type' => 'linear_scale',
                        'label' => 'Skor',
                        'is_required' => true,
                        'options' => ['min_value' => 1, 'max_value' => 5],
                    ],
                    [
                        'field_key' => 'komentar',
                        'type' => 'long_text',
                        'label' => 'Komentar',
                        'is_required' => false,
                    ],
                ],
            ],
        ], false);
        $v1 = $versionService->publishDraftVersion($form);

        app(DynamicFormSubmissionService::class)->submit($form->fresh(), [
            'answers' => ['unit' => 'igd', 'rating_rs' => 5, 'komentar' => '=SUM(1,2) formula test'],
            'submitted_at' => now()->subDays(5)->toIso8601String(),
        ]);

        app(DynamicFormSubmissionService::class)->submit($form->fresh(), [
            'answers' => ['unit' => 'ranap', 'rating_rs' => 3, 'komentar' => 'Perlu lebih cepat.'],
            'submitted_at' => now()->toIso8601String(),
        ]);

        Livewire::test(ManageDynamicFormAnalytics::class, ['record' => $form->getRouteKey()])
            ->assertSee('Total Respons Terfilter')
            ->assertSee('2')
            ->assertSee('Perlu lebih cepat.')
            ->assertSee('aria-label="IGD"', false)
            ->set('dateFrom', now()->subDay()->format('Y-m-d'))
            ->assertSee('1');

        $exportService = app(DynamicFormExportService::class);
        $csvResponse = $exportService->streamCsv($form, ['version_id' => $v1->id]);
        ob_start();
        $csvResponse->sendContent();
        $csvOutput = (string) ob_get_clean();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csvOutput);
        $this->assertStringContainsString("'=SUM(1,2) formula test", $csvOutput);
        $this->assertStringContainsString('Rawat Inap', $csvOutput);

        $xlsxResponse = $exportService->streamXlsx($form, ['version_id' => $v1->id]);
        $xlsxPath = $xlsxResponse->getFile()->getPathname();
        $this->assertFileExists($xlsxPath);
        $this->assertStringStartsWith('PK', (string) file_get_contents($xlsxPath, false, null, 0, 2));
        @unlink($xlsxPath);
    }

    public function test_submission_immutability_authorized_download_and_feedback_followup_rbac(): void
    {
        $form = DynamicForm::factory()->feedback()->create([
            'title' => 'Pengaduan Mutu',
            'slug' => 'pengaduan-mutu',
            'ticket_prefix' => 'PMU',
        ]);

        $versionService = app(DynamicFormVersionService::class);
        $versionService->syncDraftStructure($form, [
            [
                'section_key' => 's1',
                'title' => 'Laporan',
                'fields' => [
                    ['field_key' => 'judul', 'type' => 'short_text', 'label' => 'Judul Keluhan', 'is_required' => true],
                    ['field_key' => 'bukti', 'type' => 'file_upload', 'label' => 'Lampiran', 'is_required' => false],
                ],
            ],
        ], false);
        $versionService->publishDraftVersion($form);

        Storage::disk('private-test')->put('dynamic-forms/bukti-rahasia.pdf', '%PDF-1.4 rahasia');
        $upload = DynamicFormUpload::create([
            'token' => 'tok-panel-1',
            'dynamic_form_id' => $form->id,
            'field_key' => 'bukti',
            'disk' => 'private-test',
            'storage_path' => 'dynamic-forms/bukti-rahasia.pdf',
            'original_name' => 'bukti-rahasia.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 16,
            'expires_at' => now()->addHour(),
        ]);

        $submission = app(DynamicFormSubmissionService::class)->submit($form->fresh(), [
            'answers' => [
                'judul' => 'AC ruang tunggu mati',
                'bukti' => [$upload->token],
            ],
        ]);

        // Guest tidak boleh mengakses halaman maupun mengunduh lampiran
        $downloadUrl = route('filament.admin.dynamic-forms.uploads.download', ['upload' => $upload->id]);
        $this->get($downloadUrl)->assertRedirect('/admin/login');

        // Role tanpa View:DynamicFormSubmission ditolak 403 saat akses daftar & unduh lampiran
        $restrictedRole = Role::findOrCreate('form_only_viewer', 'web');
        $restrictedRole->syncPermissions(['ViewAny:DynamicForm', 'View:DynamicForm']);
        $restrictedUser = User::factory()->create(['must_change_password' => false]);
        $restrictedUser->assignRole($restrictedRole);
        $this->switchUser($restrictedUser);
        $this->get(DynamicFormSubmissionResource::getUrl('index'))->assertForbidden();
        $this->get($downloadUrl)->assertForbidden();

        // Marketing boleh melihat submission & mengunduh lampiran, tapi tidak boleh followUp tanpa Update:DynamicFormSubmission
        $this->switchUser($this->marketing);
        $this->get(DynamicFormSubmissionResource::getUrl('view', ['record' => $submission]))
            ->assertOk()
            ->assertSee('AC ruang tunggu mati')
            ->assertSee('bukti-rahasia.pdf');

        $downloadRes = $this->get($downloadUrl)->assertOk();
        $this->assertSame('nosniff', $downloadRes->headers->get('X-Content-Type-Options'));

        Livewire::test(ViewDynamicFormSubmission::class, ['record' => $submission->getKey()])
            ->assertActionHidden('followUp');

        // Immutability submission
        $this->assertFalse(DynamicFormSubmissionResource::canCreate());
        $this->assertFalse(DynamicFormSubmissionResource::canEdit($submission));
        $this->assertFalse(DynamicFormSubmissionResource::canDelete($submission));

        // Super Admin dapat menindaklanjuti tiket feedback; catatan internal tidak bocor ke metadata AuditLog
        $this->switchUser($this->superAdmin);
        Livewire::test(ViewDynamicFormSubmission::class, ['record' => $submission->getKey()])
            ->callAction('followUp', data: [
                'status' => FeedbackTicketStatus::Diproses->value,
                'internal_note' => 'Teknisi sedang menuju ruang tunggu.',
            ])
            ->assertHasNoActionErrors();

        $submission->refresh();
        $this->assertSame(FeedbackTicketStatus::Diproses, $submission->status);
        $this->assertSame('Teknisi sedang menuju ruang tunggu.', $submission->internal_note);
        $this->assertSame('AC ruang tunggu mati', $submission->answers['judul']);

        $audit = AuditLog::where('action', 'dynamic_form_submission.followed_up')->sole();
        $this->assertSame('diproses', $audit->metadata['status']);
        $this->assertStringNotContainsString('Teknisi sedang menuju ruang tunggu.', json_encode($audit->metadata));

        // Pastikan questionnaire mode tidak bisa di-followUp bahkan oleh super_admin
        $qForm = DynamicForm::factory()->create(['mode' => DynamicFormMode::Questionnaire]);
        $vService = app(DynamicFormVersionService::class);
        $vService->syncDraftStructure($qForm, [[
            'section_key' => 's1',
            'title' => 'S1',
            'fields' => [['field_key' => 'q', 'type' => 'short_text', 'label' => 'Q']],
        ]], false);
        $vService->publishDraftVersion($qForm);
        $qSub = app(DynamicFormSubmissionService::class)->submit($qForm->fresh(), ['answers' => ['q' => 'ok']]);

        $this->assertFalse($this->superAdmin->can('followUp', $qSub));
        $this->expectException(AuthorizationException::class);
        app(FollowUpDynamicFormSubmission::class)->handle($qSub, ['status' => 'selesai']);
    }

    public function test_duplicate_close_reopen_hard_delete_and_dashboard_widget(): void
    {
        $this->actingAs($this->superAdmin);

        // Empty state widget
        Livewire::test(DynamicFeedbackOverviewWidget::class)
            ->assertSee('Formulir Dinamis')
            ->assertSee('Belum ada formulir dinamis yang dipublikasikan.');

        $form = DynamicForm::factory()->feedback()->create([
            'title' => 'Form Sumber ID',
            'slug' => 'form-sumber-id',
            'locale' => 'id',
        ]);

        $versionService = app(DynamicFormVersionService::class);
        $versionService->syncDraftStructure($form, [[
            'section_key' => 's1',
            'title' => 'Umum',
            'fields' => [['field_key' => 'pesan', 'type' => 'short_text', 'label' => 'Pesan', 'is_required' => true]],
        ]], false);
        $versionService->publishDraftVersion($form);

        app(DynamicFormSubmissionService::class)->submit($form->fresh(), ['answers' => ['pesan' => 'Halo']]);

        Livewire::test(DynamicFeedbackOverviewWidget::class)
            ->assertSee('Form Sumber ID')
            ->assertSee('DFB-');

        $copy = $versionService->duplicateForm($form, 'Form Salinan EN', null, 'en');
        $this->assertSame('en', $copy->locale);
        $this->assertSame(DynamicFormStatus::Draft, $copy->status);
        $this->assertCount(1, $versionService->loadEditableSectionsData($copy));

        $versionService->closeForm($form);
        $this->assertSame(DynamicFormStatus::Closed, $form->fresh()->status);
        $versionService->reopenForm($form->fresh());
        $this->assertSame(DynamicFormStatus::Published, $form->fresh()->status);

        // Soft delete + hard delete via HardDeleteContent tanpa error Spatie getTranslation()
        $copy->delete();
        $this->assertSoftDeleted('dynamic_forms', ['id' => $copy->id]);
        $this->assertTrue(app(HardDeleteContent::class)->handle($copy->fresh()));
        $this->assertDatabaseMissing('dynamic_forms', ['id' => $copy->id]);

        Livewire::test(ListDynamicForms::class)->assertOk();
        Livewire::test(ListDynamicFormSubmissions::class)->assertOk();
    }

    public function test_wizard_rejects_logic_cycle_and_admin_pages_and_filters_render_cleanly(): void
    {
        $this->switchUser($this->marketing);

        // Wizard menolak siklus logika di Step 3 (create tidak tersimpan)
        Livewire::test(CreateDynamicForm::class)
            ->fillForm([
                'title' => 'Form Siklus Ilegal',
                'slug' => 'form-siklus-ilegal',
                'mode' => DynamicFormMode::Questionnaire->value,
                'locale' => 'id',
                'sections' => [
                    [
                        'section_key' => 'sec_a',
                        'title' => 'Bagian A',
                        'fields' => [
                            ['field_key' => 'q1', 'type' => 'short_text', 'label' => 'Q1'],
                        ],
                    ],
                    [
                        'section_key' => 'sec_b',
                        'title' => 'Bagian B',
                        'fields' => [
                            ['field_key' => 'q2', 'type' => 'short_text', 'label' => 'Q2'],
                        ],
                    ],
                ],
                'logic_config' => [
                    'section_jumps' => [
                        ['section_key' => 'sec_b', 'action' => 'jump', 'target_section_key' => 'sec_a'],
                    ],
                ],
            ])
            ->call('create')
            ->assertHasErrors();

        $this->assertDatabaseMissing('dynamic_forms', ['slug' => 'form-siklus-ilegal']);

        // Render penuh seluruh halaman HTTP panel admin & filter tabel
        $form = DynamicForm::factory()->feedback()->create([
            'title' => 'Survei Lengkap RS',
            'slug' => 'survei-lengkap-rs',
        ]);
        $versionService = app(DynamicFormVersionService::class);
        $versionService->syncDraftStructure($form, [[
            'section_key' => 's1',
            'title' => 'S1',
            'fields' => [
                ['field_key' => 'pesan', 'type' => 'short_text', 'label' => 'Pesan', 'is_required' => true],
                [
                    'field_key' => 'grid',
                    'type' => 'matrix_grid',
                    'label' => 'Penilaian',
                    'options' => [
                        'selection_mode' => 'multiple',
                        'rows' => [['key' => 'r1', 'label' => 'Kebersihan']],
                        'columns' => [['key' => 'c1', 'label' => 'Baik', 'score' => 5]],
                    ],
                ],
            ],
        ]], false);
        $versionService->publishDraftVersion($form);

        $sub = app(DynamicFormSubmissionService::class)->submit($form->fresh(), [
            'answers' => [
                'pesan' => 'Bagus sekali',
                'grid' => ['r1' => ['c1']],
            ],
        ]);

        foreach ([
            DynamicFormResource::getUrl('index'),
            DynamicFormResource::getUrl('create'),
            DynamicFormResource::getUrl('view', ['record' => $form]),
            DynamicFormResource::getUrl('edit', ['record' => $form]),
            DynamicFormResource::getUrl('analytics', ['record' => $form]),
            DynamicFormSubmissionResource::getUrl('index'),
            DynamicFormSubmissionResource::getUrl('view', ['record' => $sub]),
        ] as $url) {
            $this->get($url)->assertOk();
        }

        Livewire::test(ListDynamicFormSubmissions::class)
            ->searchTable($sub->ticket_number)
            ->assertCanSeeTableRecords([$sub]);

        Livewire::test(ListDynamicForms::class)
            ->filterTable('mode', DynamicFormMode::Feedback->value)
            ->assertCanSeeTableRecords([$form]);
    }

    public function test_step3_conditional_logic_select_options_populate_from_step2_sections(): void
    {
        $this->actingAs($this->marketing);

        $form = DynamicForm::factory()->create(['title' => 'Form Logika UI', 'slug' => 'form-logika-ui']);
        app(DynamicFormVersionService::class)->syncDraftStructure($form, [
            [
                'section_key' => 'sec_satu',
                'title' => 'Bagian Satu',
                'fields' => [
                    [
                        'field_key' => 'unit_asal',
                        'type' => 'radio',
                        'label' => 'Unit Asal',
                        'options' => [
                            'choices' => [
                                ['key' => 'igd', 'label' => 'IGD'],
                                ['key' => 'poli', 'label' => 'Poli'],
                            ],
                        ],
                    ],
                    [
                        'field_key' => 'info_tambahan',
                        'type' => 'info_block',
                        'label' => 'Info Khusus',
                        'options' => ['content' => 'Informasi penting'],
                    ],
                ],
            ],
            [
                'section_key' => 'sec_dua',
                'title' => 'Bagian Dua',
                'fields' => [
                    ['field_key' => 'catatan', 'type' => 'short_text', 'label' => 'Catatan'],
                ],
            ],
        ], false);

        $component = Livewire::test(EditDynamicForm::class, ['record' => $form->getRouteKey()])
            ->fillForm([
                'title' => 'Form Logika UI',
                'slug' => 'form-logika-ui',
                'logic_config' => [
                    'visibility_rules' => [
                        [
                            'target_field_key' => 'catatan',
                            'match' => 'all',
                            'rules' => [
                                ['source_field_key' => 'unit_asal', 'operator' => 'equals', 'value' => 'igd'],
                            ],
                        ],
                    ],
                    'choice_jumps' => [
                        [
                            'source_field_key' => 'unit_asal',
                            'choice_key' => 'igd',
                            'go_to_section_key' => 'sec_dua',
                        ],
                    ],
                    'section_jumps' => [
                        [
                            'section_key' => 'sec_satu',
                            'action' => 'jump',
                            'target_section_key' => 'sec_dua',
                        ],
                    ],
                ],
            ]);

        $schema = $component->instance()->form;
        $vrItem = collect($schema->getComponentByStatePath('logic_config.visibility_rules', withHidden: true)->getChildSchemas(withHidden: true))->first();
        $vrRuleItem = collect($vrItem->getComponentByStatePath('rules', withHidden: true)->getChildSchemas(withHidden: true))->first();
        $cjItem = collect($schema->getComponentByStatePath('logic_config.choice_jumps', withHidden: true)->getChildSchemas(withHidden: true))->first();
        $sjItem = collect($schema->getComponentByStatePath('logic_config.section_jumps', withHidden: true)->getChildSchemas(withHidden: true))->first();

        $targetSelect = $vrItem->getComponentByStatePath('target_field_key', withHidden: true);
        $sourceSelect = $vrRuleItem->getComponentByStatePath('source_field_key', withHidden: true);
        $cjSourceSelect = $cjItem->getComponentByStatePath('source_field_key', withHidden: true);
        $cjChoiceSelect = $cjItem->getComponentByStatePath('choice_key', withHidden: true);
        $cjTargetSelect = $cjItem->getComponentByStatePath('go_to_section_key', withHidden: true);
        $sjSectionSelect = $sjItem->getComponentByStatePath('section_key', withHidden: true);
        $sjTargetSelect = $sjItem->getComponentByStatePath('target_section_key', withHidden: true);

        $this->assertArrayHasKey('info_tambahan', $targetSelect->getOptions());
        $this->assertArrayHasKey('catatan', $targetSelect->getOptions());
        $this->assertArrayHasKey('unit_asal', $sourceSelect->getOptions());
        $this->assertArrayNotHasKey('info_tambahan', $sourceSelect->getOptions());
        $this->assertArrayHasKey('unit_asal', $cjSourceSelect->getOptions());
        $this->assertArrayHasKey('igd', $cjChoiceSelect->getOptions());
        $this->assertArrayHasKey('sec_dua', $cjTargetSelect->getOptions());
        $this->assertArrayHasKey('__submit__', $cjTargetSelect->getOptions());
        $this->assertArrayHasKey('sec_satu', $sjSectionSelect->getOptions());
        $this->assertArrayHasKey('sec_dua', $sjTargetSelect->getOptions());
    }

    public function test_hard_delete_form_with_submissions_and_widget_rbac_isolation_and_preview_stale_cleanup(): void
    {
        // 1. Live Preview membersihkan jawaban tersembunyi antar-section
        $this->actingAs($this->marketing);
        $previewForm = DynamicForm::factory()->create(['title' => 'Preview Stale', 'slug' => 'preview-stale']);
        $versionService = app(DynamicFormVersionService::class);
        $versionService->syncDraftStructure($previewForm, [
            [
                'section_key' => 'sec_1',
                'title' => 'Awal',
                'fields' => [
                    [
                        'field_key' => 'layanan',
                        'type' => 'radio',
                        'label' => 'Layanan',
                        'options' => ['choices' => [['key' => 'poli', 'label' => 'Poli'], ['key' => 'igd', 'label' => 'IGD']]],
                    ],
                    [
                        'field_key' => 'detail_poli',
                        'type' => 'short_text',
                        'label' => 'Detail Poli',
                        'conditional_logic' => [
                            'visibility' => [
                                'enabled' => true,
                                'match' => 'all',
                                'rules' => [['source_field_key' => 'layanan', 'operator' => 'equals', 'value' => 'poli']],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'section_key' => 'sec_2',
                'title' => 'Lanjutan',
                'fields' => [
                    [
                        'field_key' => 'komentar_poli',
                        'type' => 'short_text',
                        'label' => 'Komentar Khusus Poli',
                        'conditional_logic' => [
                            'visibility' => [
                                'enabled' => true,
                                'match' => 'all',
                                'rules' => [['source_field_key' => 'detail_poli', 'operator' => 'is_not_empty', 'value' => null]],
                            ],
                        ],
                    ],
                    [
                        'field_key' => 'skala_puas',
                        'type' => 'linear_scale',
                        'label' => 'Kepuasan Umum',
                        'options' => ['min_value' => 1, 'max_value' => 5, 'min_label' => 'Kurang', 'max_label' => 'Puas'],
                    ],
                ],
            ],
        ], false);

        Livewire::test(DynamicFormLivePreview::class, ['formId' => $previewForm->id])
            ->set('answers.layanan.selected', 'poli')
            ->set('answers.detail_poli', 'Poli Jantung')
            ->set('answers.layanan.selected', 'igd')
            ->call('nextSection')
            ->assertSee('Kepuasan Umum')
            ->assertSee('Kurang')
            ->assertDontSee('Komentar Khusus Poli');

        // 2. Hard Delete form yang sudah memiliki submission & upload
        $this->switchUser($this->superAdmin);
        $formWithSubs = DynamicForm::factory()->feedback()->create([
            'title' => 'Form Dihapus Permanen',
            'slug' => 'form-dihapus-permanen',
        ]);
        $versionService->syncDraftStructure($formWithSubs, [[
            'section_key' => 's1',
            'title' => 'S1',
            'fields' => [
                ['field_key' => 'judul', 'type' => 'short_text', 'label' => 'Judul', 'is_required' => true],
                ['field_key' => 'lampiran', 'type' => 'file_upload', 'label' => 'Lampiran', 'is_required' => false],
            ],
        ]], false);
        $versionService->publishDraftVersion($formWithSubs);

        Storage::disk('private-test')->put('dynamic-forms/to-delete.pdf', '%PDF-1.4');
        $upload = DynamicFormUpload::create([
            'token' => 'del-tok-1',
            'dynamic_form_id' => $formWithSubs->id,
            'field_key' => 'lampiran',
            'disk' => 'private-test',
            'storage_path' => 'dynamic-forms/to-delete.pdf',
            'original_name' => 'to-delete.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 8,
            'expires_at' => now()->addHour(),
        ]);

        $sub = app(DynamicFormSubmissionService::class)->submit($formWithSubs->fresh(), [
            'answers' => ['judul' => 'Keluhan test', 'lampiran' => [$upload->token]],
        ]);

        $formWithSubs->delete();
        // Pastikan historical submission tetap menampilkan judul & mode form yang di-soft-delete
        $this->get(DynamicFormSubmissionResource::getUrl('view', ['record' => $sub]))
            ->assertOk()
            ->assertSee('Form Dihapus Permanen')
            ->assertSee($sub->ticket_number);

        // Hard Delete oleh super_admin harus bersih tanpa error FK constraint
        $this->assertTrue(app(HardDeleteContent::class)->handle($formWithSubs->fresh()));
        $this->assertDatabaseMissing('dynamic_forms', ['id' => $formWithSubs->id]);
        $this->assertDatabaseMissing('dynamic_form_submissions', ['id' => $sub->id]);
        $this->assertDatabaseMissing('dynamic_form_uploads', ['id' => $upload->id]);
        Storage::disk('private-test')->assertMissing('dynamic-forms/to-delete.pdf');

        // 3. Widget RBAC: role hanya punya ViewAny:DynamicForm tidak boleh melihat daftar tiket submission
        $activeForm = DynamicForm::factory()->feedback()->create(['title' => 'Form Aktif Widget', 'slug' => 'form-aktif-widget']);
        $versionService->syncDraftStructure($activeForm, [[
            'section_key' => 's1',
            'title' => 'S1',
            'fields' => [['field_key' => 'pesan', 'type' => 'short_text', 'label' => 'Pesan']],
        ]], false);
        $versionService->publishDraftVersion($activeForm);
        $widgetTicket = app(DynamicFormSubmissionService::class)->submit($activeForm->fresh(), ['answers' => ['pesan' => 'Test']]);

        $formViewerRole = Role::findOrCreate('widget_form_viewer', 'web');
        $formViewerRole->syncPermissions(['View:DynamicFeedbackOverviewWidget', 'ViewAny:DynamicForm', 'View:DynamicForm']);
        $formViewer = User::factory()->create(['must_change_password' => false]);
        $formViewer->assignRole($formViewerRole);
        $this->switchUser($formViewer);

        Livewire::test(DynamicFeedbackOverviewWidget::class)
            ->assertSee('Form Aktif Widget')
            ->assertDontSee($widgetTicket->ticket_number)
            ->assertDontSee('Tiket Baru Menunggu');
    }

    public function test_analytics_livewire_export_actions_empty_filter_and_legacy_matrix_rows(): void
    {
        $this->actingAs($this->marketing);

        $form = DynamicForm::factory()->create([
            'mode' => DynamicFormMode::Questionnaire,
            'title' => 'Survei Matriks Lintas Versi',
            'slug' => 'survei-matriks-lintas-versi',
        ]);

        $versionService = app(DynamicFormVersionService::class);
        $versionService->syncDraftStructure($form, [[
            'section_key' => 's1',
            'title' => 'Evaluasi Mutu',
            'fields' => [
                [
                    'field_key' => 'matriks_mutu',
                    'type' => 'matrix_grid',
                    'label' => 'Aspek Penilaian',
                    'options' => [
                        'selection_mode' => 'single',
                        'rows' => [
                            ['key' => 'r_lama', 'label' => 'Kebersihan Ruang Lama'],
                            ['key' => 'r_tetap', 'label' => 'Kecepatan Layanan'],
                        ],
                        'columns' => [
                            ['key' => 'baik', 'label' => 'Baik', 'score' => 5],
                        ],
                    ],
                ],
            ],
        ]], false);
        $versionService->publishDraftVersion($form);

        app(DynamicFormSubmissionService::class)->submit($form->fresh(), [
            'answers' => ['matriks_mutu' => ['r_lama' => 'baik', 'r_tetap' => 'baik']],
        ]);

        $versionService->syncDraftStructure($form->fresh(), [[
            'section_key' => 's1',
            'title' => 'Evaluasi Mutu',
            'fields' => [
                [
                    'field_key' => 'matriks_mutu',
                    'type' => 'matrix_grid',
                    'label' => 'Aspek Penilaian',
                    'options' => [
                        'selection_mode' => 'single',
                        'rows' => [
                            ['key' => 'r_tetap', 'label' => 'Kecepatan Layanan'],
                            ['key' => 'r_baru', 'label' => 'Keramahan Staf'],
                        ],
                        'columns' => [
                            ['key' => 'baik', 'label' => 'Baik', 'score' => 5],
                        ],
                    ],
                ],
            ],
        ]], true);
        $versionService->publishDraftVersion($form->fresh());

        app(DynamicFormSubmissionService::class)->submit($form->fresh(), [
            'answers' => ['matriks_mutu' => ['r_tetap' => 'baik', 'r_baru' => 'baik']],
        ]);

        Livewire::test(ManageDynamicFormAnalytics::class, ['record' => $form->getRouteKey()])
            ->assertSee('Total Respons Terfilter')
            ->assertSee('r_lama (Baris versi lama)')
            ->assertSee('Keramahan Staf')
            ->callAction('exportCsv')
            ->assertFileDownloaded()
            ->callAction('exportXlsx')
            ->assertFileDownloaded()
            ->set('dateFrom', '2099-01-01')
            ->assertSee('Tidak ada respons yang cocok dengan filter aktif.')
            ->call('resetFilters')
            ->assertDontSee('Tidak ada respons yang cocok dengan filter aktif.')
            ->assertSee('Keramahan Staf');
    }

    public function test_wizard_duplicate_key_validation_edit_double_publish_guard_invalid_logic_notification_and_submission_viewer_widget_rbac(): void
    {
        $this->actingAs($this->marketing);

        // 1. Duplikasi field_key ditolak & form tidak tersimpan
        Livewire::test(CreateDynamicForm::class)
            ->fillForm([
                'title' => 'Form Duplikat Key',
                'slug' => 'form-duplikat-key',
                'mode' => DynamicFormMode::Questionnaire->value,
                'locale' => 'id',
                'sections' => [[
                    'section_key' => 'sec_1',
                    'title' => 'Bagian 1',
                    'fields' => [
                        ['field_key' => 'dup_key', 'type' => 'short_text', 'label' => 'Pertanyaan 1'],
                        ['field_key' => 'dup_key', 'type' => 'short_text', 'label' => 'Pertanyaan 2'],
                    ],
                ]],
            ])
            ->call('create')
            ->assertHasErrors();

        $this->assertDatabaseMissing('dynamic_forms', ['slug' => 'form-duplikat-key']);

        // 2. Guard double-publish di EditDynamicForm saat publish_now = true + aksi header publish
        $draftForm = DynamicForm::factory()->feedback()->create([
            'title' => 'Form Draft Guard Publish',
            'slug' => 'form-draft-guard-publish',
            'status' => DynamicFormStatus::Draft,
        ]);
        $versionService = app(DynamicFormVersionService::class);
        $versionService->syncDraftStructure($draftForm, [[
            'section_key' => 'sec_a',
            'title' => 'Bagian A',
            'fields' => [['field_key' => 'q1', 'type' => 'short_text', 'label' => 'Pertanyaan A']],
        ]], false);

        Livewire::test(EditDynamicForm::class, ['record' => $draftForm->getRouteKey()])
            ->fillForm([
                'title' => 'Form Draft Guard Publish',
                'slug' => 'form-draft-guard-publish',
                'mode' => DynamicFormMode::Feedback->value,
                'locale' => 'id',
                'sections' => [[
                    'section_key' => 'sec_a',
                    'title' => 'Bagian A',
                    'fields' => [['field_key' => 'q1', 'type' => 'short_text', 'label' => 'Pertanyaan A']],
                ]],
                'publish_now' => true,
            ])
            ->callAction('publish')
            ->assertHasNoErrors();

        $draftForm->refresh();
        $this->assertSame(DynamicFormStatus::Published, $draftForm->status);
        $this->assertSame(
            1,
            AuditLog::where('action', 'dynamic_form.published')
                ->where('subject_id', $draftForm->id)
                ->count()
        );

        // 3. EditDynamicForm mengirim Notification error ketika logika percabangan tidak valid
        $versionService->syncDraftStructure($draftForm, [
            [
                'section_key' => 'sec_a',
                'title' => 'Bagian A',
                'fields' => [['field_key' => 'q1', 'type' => 'short_text', 'label' => 'Q1']],
            ],
            [
                'section_key' => 'sec_b',
                'title' => 'Bagian B',
                'fields' => [['field_key' => 'q2', 'type' => 'short_text', 'label' => 'Q2']],
            ],
        ], true);

        Livewire::test(EditDynamicForm::class, ['record' => $draftForm->getRouteKey()])
            ->fillForm([
                'title' => 'Form Draft Guard Publish',
                'slug' => 'form-draft-guard-publish',
                'mode' => DynamicFormMode::Feedback->value,
                'locale' => 'id',
                'sections' => [
                    [
                        'section_key' => 'sec_a',
                        'title' => 'Bagian A',
                        'fields' => [['field_key' => 'q1', 'type' => 'short_text', 'label' => 'Q1']],
                    ],
                    [
                        'section_key' => 'sec_b',
                        'title' => 'Bagian B',
                        'fields' => [['field_key' => 'q2', 'type' => 'short_text', 'label' => 'Q2']],
                    ],
                ],
                'logic_config' => [
                    'section_jumps' => [
                        ['section_key' => 'sec_b', 'action' => 'jump', 'target_section_key' => 'sec_a'],
                    ],
                ],
            ])
            ->call('save')
            ->assertNotified('Aturan Percabangan Logika Tidak Valid')
            ->assertHasErrors();

        // 4. Widget RBAC: role hanya punya ViewAny:DynamicFormSubmission tidak boleh melihat daftar form terpublikasi
        $ticket = app(DynamicFormSubmissionService::class)->submit($draftForm->fresh(), [
            'answers' => ['q1' => 'Keluhan widget submission viewer'],
        ]);

        $submissionViewerRole = Role::findOrCreate('widget_submission_viewer', 'web');
        $submissionViewerRole->syncPermissions([
            'View:DynamicFeedbackOverviewWidget',
            'ViewAny:DynamicFormSubmission',
            'View:DynamicFormSubmission',
        ]);
        $submissionViewer = User::factory()->create(['must_change_password' => false]);
        $submissionViewer->assignRole($submissionViewerRole);
        $this->switchUser($submissionViewer);

        Livewire::test(DynamicFeedbackOverviewWidget::class)
            ->assertSee($ticket->ticket_number)
            ->assertSee('Tiket Baru Menunggu')
            ->assertDontSee('Formulir Aktif:')
            ->assertDontSee('Kelola Dynamic Forms')
            ->assertDontSee('Formulir Dinamis Terpublikasi');
    }

    public function test_orphan_upload_returns_404_and_soft_deleted_submission_upload_enforces_attachment_policy(): void
    {
        $form = DynamicForm::factory()->feedback()->create([
            'title' => 'Form Uji Upload Privat',
            'slug' => 'form-uji-upload-privat',
        ]);

        $versionService = app(DynamicFormVersionService::class);
        $versionService->syncDraftStructure($form, [[
            'section_key' => 's1',
            'title' => 'Bagian 1',
            'fields' => [
                ['field_key' => 'judul', 'type' => 'short_text', 'label' => 'Judul', 'is_required' => true],
                ['field_key' => 'berkas', 'type' => 'file_upload', 'label' => 'Berkas', 'is_required' => false],
            ],
        ]], false);
        $versionService->publishDraftVersion($form);

        Storage::disk('private-test')->put('dynamic-forms/orphan.pdf', '%PDF-1.4 orphan');
        $orphanUpload = DynamicFormUpload::create([
            'token' => 'tok-orphan-1',
            'dynamic_form_id' => $form->id,
            'submission_id' => null,
            'field_key' => 'berkas',
            'disk' => 'private-test',
            'storage_path' => 'dynamic-forms/orphan.pdf',
            'original_name' => 'orphan.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 15,
            'expires_at' => now()->addHour(),
        ]);

        Storage::disk('private-test')->put('dynamic-forms/trashed-sub.pdf', '%PDF-1.4 trashed');
        $attachedUpload = DynamicFormUpload::create([
            'token' => 'tok-trashed-sub-1',
            'dynamic_form_id' => $form->id,
            'field_key' => 'berkas',
            'disk' => 'private-test',
            'storage_path' => 'dynamic-forms/trashed-sub.pdf',
            'original_name' => 'trashed-sub.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 16,
            'expires_at' => now()->addHour(),
        ]);

        $submission = app(DynamicFormSubmissionService::class)->submit($form->fresh(), [
            'answers' => [
                'judul' => 'Lampiran submission terhapus lunak',
                'berkas' => [$attachedUpload->token],
            ],
        ]);
        $submission->delete();

        $orphanUrl = route('filament.admin.dynamic-forms.uploads.download', ['upload' => $orphanUpload->id]);
        $attachedUrl = route('filament.admin.dynamic-forms.uploads.download', ['upload' => $attachedUpload->id]);

        // User dengan izin penuh pun harus mendapat 404 untuk berkas sementara (yatim)
        $this->switchUser($this->marketing);
        $this->get($orphanUrl)->assertNotFound();

        // Submission yang di-soft-delete tetap bisa diunduh oleh user dengan izin downloadAttachment
        $this->get($attachedUrl)->assertOk();

        // User tanpa izin View:DynamicFormSubmission tetap ditolak 403 untuk berkas milik submission ter-soft-delete
        $restrictedRole = Role::findOrCreate('form_only_viewer_upload_check', 'web');
        $restrictedRole->syncPermissions(['ViewAny:DynamicForm', 'View:DynamicForm']);
        $restrictedUser = User::factory()->create(['must_change_password' => false]);
        $restrictedUser->assignRole($restrictedRole);
        $this->switchUser($restrictedUser);
        $this->get($attachedUrl)->assertForbidden();
    }

    public function test_wizard_deduplicates_choice_row_column_keys_on_blur_and_rejects_duplicates_on_submit(): void
    {
        $this->actingAs($this->marketing);

        // 1. Auto-deduplikasi sibling keys pada choices.*.label saat afterStateUpdated
        $component = Livewire::test(CreateDynamicForm::class);
        $sections = $component->get('data.sections');
        $secKey = array_key_first($sections);
        $fldKey = array_key_first($sections[$secKey]['fields']);

        $component->set("data.sections.{$secKey}.fields.{$fldKey}.type", 'radio');
        $choices = $component->get("data.sections.{$secKey}.fields.{$fldKey}.options.choices");
        $choiceItemKeys = array_keys($choices);

        $component
            ->set("data.sections.{$secKey}.fields.{$fldKey}.options.choices.{$choiceItemKeys[0]}.label", 'Sama')
            ->set("data.sections.{$secKey}.fields.{$fldKey}.options.choices.{$choiceItemKeys[1]}.label", 'Sama');

        $updatedChoices = $component->get("data.sections.{$secKey}.fields.{$fldKey}.options.choices");
        $this->assertSame('sama', $updatedChoices[$choiceItemKeys[0]]['key']);
        $this->assertSame('sama_2', $updatedChoices[$choiceItemKeys[1]]['key']);

        // 2. Auto-deduplikasi sibling keys pada rows.*.label dan columns.*.label untuk matrix_grid
        $component->set("data.sections.{$secKey}.fields.{$fldKey}.type", 'matrix_grid');
        $rows = $component->get("data.sections.{$secKey}.fields.{$fldKey}.options.rows");
        $rowItemKeys = array_keys($rows);
        $columns = $component->get("data.sections.{$secKey}.fields.{$fldKey}.options.columns");
        $columnItemKeys = array_keys($columns);

        $component
            ->set("data.sections.{$secKey}.fields.{$fldKey}.options.rows.{$rowItemKeys[0]}.label", 'Baris Sama')
            ->set("data.sections.{$secKey}.fields.{$fldKey}.options.rows.{$rowItemKeys[1]}.label", 'Baris Sama')
            ->set("data.sections.{$secKey}.fields.{$fldKey}.options.columns.{$columnItemKeys[0]}.label", 'Kolom Sama')
            ->set("data.sections.{$secKey}.fields.{$fldKey}.options.columns.{$columnItemKeys[1]}.label", 'Kolom Sama');

        $updatedRows = $component->get("data.sections.{$secKey}.fields.{$fldKey}.options.rows");
        $updatedColumns = $component->get("data.sections.{$secKey}.fields.{$fldKey}.options.columns");
        $this->assertSame('baris_sama', $updatedRows[$rowItemKeys[0]]['key']);
        $this->assertSame('baris_sama_2', $updatedRows[$rowItemKeys[1]]['key']);
        $this->assertSame('kolom_sama', $updatedColumns[$columnItemKeys[0]]['key']);
        $this->assertSame('kolom_sama_2', $updatedColumns[$columnItemKeys[1]]['key']);

        // 3. Penolakan eksplisit jika pilihan/baris/kolom memakai key duplikat secara manual saat submit
        Livewire::test(CreateDynamicForm::class)
            ->fillForm([
                'title' => 'Form Pilihan Duplikat',
                'slug' => 'form-pilihan-duplikat',
                'mode' => DynamicFormMode::Questionnaire->value,
                'locale' => 'id',
                'sections' => [[
                    'section_key' => 'sec_1',
                    'title' => 'Bagian 1',
                    'fields' => [[
                        'field_key' => 'tanya_pilihan',
                        'type' => 'radio',
                        'label' => 'Pilih Salah Satu',
                        'options' => [
                            'choices' => [
                                ['key' => 'opsi_sama', 'label' => 'Opsi A'],
                                ['key' => 'opsi_sama', 'label' => 'Opsi B'],
                            ],
                        ],
                    ]],
                ]],
            ])
            ->call('create')
            ->assertHasErrors();

        $this->assertDatabaseMissing('dynamic_forms', ['slug' => 'form-pilihan-duplikat']);

        // 4. Deduplikasi pilihan berlabel sama menjaga akurasi choice_jumps di Step 3 hingga evaluasi lompatan
        $jumpFormComponent = Livewire::test(CreateDynamicForm::class);
        $jSections = $jumpFormComponent->get('data.sections');
        $jSecKey = array_key_first($jSections);
        $jFldKey = array_key_first($jSections[$jSecKey]['fields']);

        $jumpFormComponent
            ->set("data.sections.{$jSecKey}.title", 'Bagian Awal')
            ->set("data.sections.{$jSecKey}.fields.{$jFldKey}.type", 'radio')
            ->set("data.sections.{$jSecKey}.fields.{$jFldKey}.label", 'Pilih Unit');

        $jChoices = $jumpFormComponent->get("data.sections.{$jSecKey}.fields.{$jFldKey}.options.choices");
        $jChoiceKeys = array_keys($jChoices);
        $jumpFormComponent
            ->set("data.sections.{$jSecKey}.fields.{$jFldKey}.options.choices.{$jChoiceKeys[0]}.label", 'Sama')
            ->set("data.sections.{$jSecKey}.fields.{$jFldKey}.options.choices.{$jChoiceKeys[1]}.label", 'Sama');

        // Verifikasi opsi choice_jumps di Step 3 menampilkan kedua opsi yang terdeduplikasi secara terpisah
        $jumpFormComponent->set('data.logic_config.choice_jumps', [[
            'source_field_key' => 'pilih_unit',
            'choice_key' => null,
            'go_to_section_key' => null,
        ]]);
        $cjRefreshed = collect($jumpFormComponent->instance()->form->getComponentByStatePath('logic_config.choice_jumps', withHidden: true)->getChildSchemas(withHidden: true))->first();
        $this->assertNotNull($cjRefreshed);
        $cjOpts = $cjRefreshed->getComponentByStatePath('choice_key', withHidden: true)->getOptions();
        $this->assertSame(['sama' => 'Sama (sama)', 'sama_2' => 'Sama (sama_2)'], $cjOpts);

        Livewire::test(CreateDynamicForm::class)
            ->fillForm([
                'title' => 'Form Choice Jump Deduplikasi',
                'slug' => 'form-choice-jump-dedup',
                'mode' => DynamicFormMode::Questionnaire->value,
                'locale' => 'id',
                'sections' => [
                    [
                        'section_key' => 'sec_awal',
                        'title' => 'Bagian Awal',
                        'fields' => [[
                            'field_key' => 'pilih_unit',
                            'type' => 'radio',
                            'label' => 'Pilih Unit',
                            'options' => [
                                'choices' => [
                                    ['key' => 'sama', 'label' => 'Sama'],
                                    ['key' => 'sama_2', 'label' => 'Sama'],
                                ],
                            ],
                        ]],
                    ],
                    [
                        'section_key' => 'sec_tengah',
                        'title' => 'Bagian Tengah',
                        'fields' => [['field_key' => 'tengah', 'type' => 'short_text', 'label' => 'Tengah']],
                    ],
                    [
                        'section_key' => 'sec_akhir',
                        'title' => 'Bagian Akhir',
                        'fields' => [['field_key' => 'akhir', 'type' => 'short_text', 'label' => 'Akhir']],
                    ],
                ],
                'logic_config' => [
                    'choice_jumps' => [
                        ['source_field_key' => 'pilih_unit', 'choice_key' => 'sama_2', 'go_to_section_key' => 'sec_akhir'],
                    ],
                ],
                'publish_now' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $jumpForm = DynamicForm::where('slug', 'form-choice-jump-dedup')->firstOrFail();
        $jumpEval = app(DynamicFormLogicEvaluator::class)->evaluate(
            $jumpForm->publishedVersion->schema_snapshot,
            ['pilih_unit' => 'sama_2', 'tengah' => 'terlewati', 'akhir' => 'ok']
        );
        $this->assertSame(['sec_awal', 'sec_akhir'], $jumpEval['active_section_keys']);

        // 5. EditDynamicForm (hasSkippableSteps = true) menolak duplikasi choices/rows/columns saat save
        Livewire::test(EditDynamicForm::class, ['record' => $jumpForm->getRouteKey()])
            ->fillForm([
                'title' => 'Form Choice Jump Deduplikasi',
                'slug' => 'form-choice-jump-dedup',
                'mode' => DynamicFormMode::Questionnaire->value,
                'locale' => 'id',
                'sections' => [[
                    'section_key' => 'sec_awal',
                    'title' => 'Bagian Awal',
                    'fields' => [[
                        'field_key' => 'matriks_edit',
                        'type' => 'matrix_grid',
                        'label' => 'Matriks Edit',
                        'options' => [
                            'rows' => [
                                ['key' => 'r_dup', 'label' => 'Baris 1'],
                                ['key' => 'r_dup', 'label' => 'Baris 2'],
                            ],
                            'columns' => [
                                ['key' => 'c_dup', 'label' => 'Kolom 1'],
                                ['key' => 'c_dup', 'label' => 'Kolom 2'],
                            ],
                        ],
                    ]],
                ]],
                'logic_config' => [
                    'section_jumps' => [],
                    'choice_jumps' => [],
                    'visibility_rules' => [],
                ],
            ])
            ->call('save')
            ->assertNotified('Terdapat Kunci Duplikat pada Formulir')
            ->assertHasErrors();

        $this->assertNull($jumpForm->fresh()->latestDraftVersion());

        // 6. DynamicFormVersionService::uniqueKey tidak memotong kunci 66..80 karakter maupun underscore valid sehingga choice_jumps tetap utuh
        $longKey1 = str_repeat('a', 68).'_1';
        $longKey2 = str_repeat('a', 68).'_2';
        $merged = app(DynamicFormVersionService::class)->mergeLogicConfigIntoSections(
            [
                [
                    'section_key' => 'sec_1',
                    'title' => 'Bagian 1',
                    'fields' => [[
                        'field_key' => 'q_panjang',
                        'type' => 'dropdown',
                        'label' => 'Pertanyaan Panjang',
                        'options' => [
                            'choices' => [
                                ['key' => $longKey1, 'label' => 'Opsi 1'],
                                ['key' => $longKey2, 'label' => 'Opsi 2'],
                                ['key' => '_opsi_khusus', 'label' => 'Opsi Khusus'],
                            ],
                        ],
                    ]],
                ],
                [
                    'section_key' => 'sec_2',
                    'title' => 'Bagian 2',
                    'fields' => [['field_key' => 'q2', 'type' => 'short_text', 'label' => 'Q2']],
                ],
            ],
            [
                'choice_jumps' => [
                    ['source_field_key' => 'q_panjang', 'choice_key' => $longKey2, 'go_to_section_key' => 'sec_2'],
                    ['source_field_key' => 'q_panjang', 'choice_key' => '_opsi_khusus', 'go_to_section_key' => '__submit__'],
                ],
            ]
        );

        $choicesOut = collect($merged[0]['fields'][0]['options']['choices'])->keyBy('key');
        $this->assertTrue($choicesOut->has($longKey1));
        $this->assertTrue($choicesOut->has($longKey2));
        $this->assertTrue($choicesOut->has('_opsi_khusus'));
        $this->assertSame('sec_2', $choicesOut[$longKey2]['go_to_section_key']);
        $this->assertSame('__submit__', $choicesOut['_opsi_khusus']['go_to_section_key']);
    }

    private function switchUser(User $user): void
    {
        session()->flush();
        app()->forgetScopedInstances();
        $this->actingAs($user);
    }
}
