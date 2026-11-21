<?php

namespace Tests\Feature;

use App\Actions\FollowUpDynamicFormSubmission;
use App\Actions\HardDeleteContent;
use App\Enums\DynamicFormMode;
use App\Enums\DynamicFormStatus;
use App\Filament\Resources\DynamicForms\DynamicFormResource;
use App\Filament\Resources\DynamicForms\Pages\CreateDynamicForm;
use App\Filament\Resources\DynamicForms\Pages\EditDynamicForm;
use App\Filament\Resources\DynamicForms\Pages\ListDynamicForms;
use App\Filament\Resources\DynamicForms\Pages\ManageDynamicFormAnalytics;
use App\Filament\Resources\DynamicForms\Pages\ViewDynamicForm;
use App\Filament\Resources\DynamicFormSubmissions\DynamicFormSubmissionResource;
use App\Filament\Resources\DynamicFormSubmissions\Pages\ListDynamicFormSubmissions;
use App\Filament\Widgets\DynamicFeedbackOverviewWidget;
use App\Livewire\DynamicFormLivePreview;
use App\Models\AuditLog;
use App\Models\DynamicForm;
use App\Models\DynamicFormField;
use App\Models\DynamicFormSubmission;
use App\Models\DynamicFormSubmissionAnswer;
use App\Models\DynamicFormUpload;
use App\Models\DynamicFormVersion;
use App\Models\Role;
use App\Models\User;
use App\Services\DynamicForm\DynamicFormExportService;
use App\Services\DynamicForm\DynamicFormLogicEvaluator;
use App\Services\DynamicForm\DynamicFormSubmissionService;
use App\Services\DynamicForm\DynamicFormVersionService;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\SeedsTestRolesAndUsers;
use Tests\TestCase;

class DynamicFormPanelTest extends TestCase
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
        $this->assertFalse($form->is_public_listed);
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
                'is_public_listed' => true,
                'publish_now' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $form->refresh();
        $this->assertTrue($form->is_public_listed);
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

        // Marketing boleh melihat submission & mengunduh lampiran, dan tidak ada mekanisme ticketing/followUp
        $this->switchUser($this->marketing);
        $this->get(DynamicFormSubmissionResource::getUrl('view', ['record' => $submission]))
            ->assertOk()
            ->assertSee('AC ruang tunggu mati')
            ->assertSee('bukti-rahasia.pdf')
            ->assertDontSee('Nomor Tiket')
            ->assertDontSee('Status & Catatan Tindak Lanjut');

        $downloadRes = $this->get($downloadUrl)->assertOk();
        $this->assertSame('nosniff', $downloadRes->headers->get('X-Content-Type-Options'));

        $this->assertNull($submission->ticket_number);
        $this->assertNull($submission->status);

        // Immutability submission
        $this->assertFalse(DynamicFormSubmissionResource::canCreate());
        $this->assertFalse(DynamicFormSubmissionResource::canEdit($submission));
        $this->assertFalse(DynamicFormSubmissionResource::canDelete($submission));

        // Pastikan baik mode feedback maupun questionnaire tidak mengizinkan followUp bahkan untuk super_admin
        $this->switchUser($this->superAdmin);
        $this->assertFalse($this->superAdmin->can('followUp', $submission));

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
        app(FollowUpDynamicFormSubmission::class)->handle($submission, ['status' => 'selesai']);
    }

    public function test_duplicate_close_reopen_hard_delete_and_dashboard_widget(): void
    {
        $this->actingAs($this->superAdmin);

        // Empty state widget
        Livewire::test(DynamicFeedbackOverviewWidget::class)
            ->assertSee('Custom Questionnaire Form')
            ->assertSee('Belum ada formulir kustom yang dipublikasikan.');

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
            ->assertSee('Respons Terbaru Masuk');

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
            ->searchTable('Survei Lengkap RS')
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
            ->assertSee('Response #'.$sub->id);

        // Hard Delete oleh super_admin harus bersih tanpa error FK constraint
        $this->assertTrue(app(HardDeleteContent::class)->handle($formWithSubs->fresh()));
        $this->assertDatabaseMissing('dynamic_forms', ['id' => $formWithSubs->id]);
        $this->assertDatabaseMissing('dynamic_form_submissions', ['id' => $sub->id]);
        $this->assertDatabaseMissing('dynamic_form_uploads', ['id' => $upload->id]);
        Storage::disk('private-test')->assertMissing('dynamic-forms/to-delete.pdf');

        // 3. Widget RBAC: role hanya punya ViewAny:DynamicForm tidak boleh melihat daftar respons terbaru
        $activeForm = DynamicForm::factory()->feedback()->create(['title' => 'Form Aktif Widget', 'slug' => 'form-aktif-widget']);
        $versionService->syncDraftStructure($activeForm, [[
            'section_key' => 's1',
            'title' => 'S1',
            'fields' => [['field_key' => 'pesan', 'type' => 'short_text', 'label' => 'Pesan']],
        ]], false);
        $versionService->publishDraftVersion($activeForm);
        $widgetSub = app(DynamicFormSubmissionService::class)->submit($activeForm->fresh(), ['answers' => ['pesan' => 'Test']]);

        $formViewerRole = Role::findOrCreate('widget_form_viewer', 'web');
        $formViewerRole->syncPermissions(['View:DynamicFeedbackOverviewWidget', 'ViewAny:DynamicForm', 'View:DynamicForm']);
        $formViewer = User::factory()->create(['must_change_password' => false]);
        $formViewer->assignRole($formViewerRole);
        $this->switchUser($formViewer);

        Livewire::test(DynamicFeedbackOverviewWidget::class)
            ->assertSee('Form Aktif Widget')
            ->assertDontSee(DynamicFormSubmissionResource::getUrl('view', ['record' => $widgetSub]))
            ->assertDontSee('Total Respons Masuk')
            ->assertDontSee('Respons Terbaru Masuk');
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
            ->assertSee(DynamicFormSubmissionResource::getUrl('view', ['record' => $ticket]))
            ->assertSee('Total Respons Masuk')
            ->assertSee('Respons Terbaru Masuk')
            ->assertDontSee('Formulir Aktif:')
            ->assertDontSee('Kelola Custom Questionnaire Form')
            ->assertDontSee('Formulir Kustom Terpublikasi');
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

    public function test_share_link_qr_modal_table_columns_filters_action_group_and_rbac(): void
    {
        Http::fake();
        Config::set('web_frontend.public_url', 'http://rs-elisabeth-web.test');
        Config::set('web_frontend.webhook_url', 'http://rs-elisabeth-web.test/api/internal/webhook/cache-invalidate');

        $versionService = app(DynamicFormVersionService::class);
        $sampleSections = [[
            'section_key' => 'sec_1',
            'title' => 'Bagian 1',
            'fields' => [[
                'field_key' => 'pertanyaan_1',
                'type' => 'short_text',
                'label' => 'Pertanyaan 1',
                'is_required' => true,
            ]],
        ]];

        $draftForm = DynamicForm::factory()->create([
            'title' => 'Formulir Draf Belum Terbit',
            'slug' => 'formulir-draf-belum-terbit',
            'status' => DynamicFormStatus::Draft,
            'is_public_listed' => false,
        ]);
        $versionService->syncDraftStructure($draftForm, $sampleSections, false);

        $publishedListed = DynamicForm::factory()->create([
            'title' => 'Survei Katalog Publik',
            'slug' => 'survei-katalog-publik',
            'is_public_listed' => true,
        ]);
        $versionService->syncDraftStructure($publishedListed, $sampleSections, false);
        $versionService->publishDraftVersion($publishedListed);
        $publishedListed->refresh();

        $closedUnlisted = DynamicForm::factory()->create([
            'title' => 'Survei QR Saja Ditutup',
            'slug' => 'survei-qr-saja-ditutup',
            'is_public_listed' => false,
        ]);
        $versionService->syncDraftStructure($closedUnlisted, $sampleSections, false);
        $versionService->publishDraftVersion($closedUnlisted);
        $versionService->closeForm($closedUnlisted->fresh());
        $closedUnlisted->refresh();

        $this->actingAs($this->marketing);

        // 1. Tabel menampilkan path web (/formulir/...), badge Katalog Web, filter, aksi cepat, dan ActionGroup sekunder.
        Livewire::test(ListDynamicForms::class)
            ->assertSee('/formulir/survei-katalog-publik')
            ->assertSee('/formulir/survei-qr-saja-ditutup')
            ->assertDontSee('/api/v1/cms/forms/')
            ->assertSee('Katalog Publik')
            ->assertSee('Link / QR Saja')
            ->assertCanRenderTableColumn('title')
            ->assertCanRenderTableColumn('status')
            ->assertCanRenderTableColumn('is_public_listed')
            ->assertCanRenderTableColumn('submissions_count')
            ->assertCanNotRenderTableColumn('mode')
            ->assertActionVisible(TestAction::make('analytics')->table($publishedListed))
            ->assertActionVisible(TestAction::make('shareLink')->table($publishedListed))
            ->assertActionVisible(TestAction::make('shareLink')->table($closedUnlisted))
            ->assertActionHidden(TestAction::make('shareLink')->table($draftForm))
            ->assertActionVisible(TestAction::make('preview')->table($publishedListed))
            ->assertActionVisible(TestAction::make('close')->table($publishedListed))
            ->assertActionVisible(TestAction::make('reopen')->table($closedUnlisted))
            ->assertActionVisible(TestAction::make('edit')->table($publishedListed))
            ->filterTable('is_public_listed', true)
            ->assertCanSeeTableRecords([$publishedListed])
            ->assertCanNotSeeTableRecords([$closedUnlisted, $draftForm])
            ->filterTable('is_public_listed', false)
            ->assertCanSeeTableRecords([$closedUnlisted, $draftForm])
            ->assertCanNotSeeTableRecords([$publishedListed]);

        // 2. Modal Link & QR Code dari tabel menampilkan dua link, status katalog, preview QR, dan unduhan PNG/SVG.
        Livewire::test(ListDynamicForms::class)
            ->mountAction(TestAction::make('shareLink')->table($publishedListed))
            ->assertMountedActionModalSee('http://rs-elisabeth-web.test/formulir/survei-katalog-publik')
            ->assertMountedActionModalSee('http://rs-elisabeth-web.test/formulir/survei-katalog-publik?source=qr_barcode')
            ->assertMountedActionModalSee('Katalog Publik')
            ->assertMountedActionModalSee('web_public')
            ->assertMountedActionModalSee('qr_barcode')
            ->assertMountedActionModalSeeHtml('data:image/svg+xml;base64,')
            ->assertMountedActionModalSeeHtml("copyLink('public', \$refs.publicUrlInput, \$el)")
            ->assertMountedActionModalSeeHtml("copyLink('qr', \$refs.qrUrlInput, \$el)")
            ->callAction('downloadPng')
            ->assertFileDownloaded('qr-survei-katalog-publik.png')
            ->assertActionMounted(TestAction::make('shareLink')->table($publishedListed))
            ->callAction('downloadSvg')
            ->assertFileDownloaded('qr-survei-katalog-publik.svg')
            ->unmountAction()
            ->assertActionNotMounted();

        // 3. Halaman View & Edit menampilkan Path Web, status Katalog Web, dan header action Link & QR Code (tersembunyi di Draft).
        $this->get(DynamicFormResource::getUrl('view', ['record' => $publishedListed]))
            ->assertOk()
            ->assertSee('/formulir/survei-katalog-publik')
            ->assertSee('Katalog Publik')
            ->assertDontSee('Slug API');

        Livewire::test(ViewDynamicForm::class, ['record' => $publishedListed->getRouteKey()])
            ->assertActionVisible('shareLink')
            ->mountAction('shareLink')
            ->assertMountedActionModalSee('http://rs-elisabeth-web.test/formulir/survei-katalog-publik?source=qr_barcode')
            ->callAction('downloadPng')
            ->assertFileDownloaded('qr-survei-katalog-publik.png')
            ->callAction('downloadSvg')
            ->assertFileDownloaded('qr-survei-katalog-publik.svg')
            ->unmountAction()
            ->assertActionNotMounted();

        Livewire::test(ViewDynamicForm::class, ['record' => $draftForm->getRouteKey()])
            ->assertActionHidden('shareLink');

        Livewire::test(EditDynamicForm::class, ['record' => $closedUnlisted->getRouteKey()])
            ->assertActionVisible('shareLink')
            ->mountAction('shareLink')
            ->assertMountedActionModalSee('Link / QR Saja')
            ->assertMountedActionModalSee('Status formulir saat ini Ditutup')
            ->callAction('downloadPng')
            ->assertFileDownloaded('qr-survei-qr-saja-ditutup.png');

        Livewire::test(EditDynamicForm::class, ['record' => $draftForm->getRouteKey()])
            ->assertActionHidden('shareLink');

        // 4. Fallback ke WEB_FRONTEND_WEBHOOK_URL saat WEB_FRONTEND_PUBLIC_URL kosong & error state saat keduanya kosong.
        Config::set('web_frontend.public_url', null);
        Config::set('web_frontend.webhook_url', 'https://portal.rs-elisabeth.test:8443/api/internal/webhook/cache-invalidate');

        Livewire::test(ListDynamicForms::class)
            ->mountAction(TestAction::make('shareLink')->table($publishedListed))
            ->assertMountedActionModalSee('https://portal.rs-elisabeth.test:8443/formulir/survei-katalog-publik')
            ->assertMountedActionModalSee('https://portal.rs-elisabeth.test:8443/formulir/survei-katalog-publik?source=qr_barcode');

        Config::set('web_frontend.public_url', null);
        Config::set('web_frontend.webhook_url', null);

        Livewire::test(ListDynamicForms::class)
            ->mountAction(TestAction::make('shareLink')->table($publishedListed))
            ->assertMountedActionModalSee('URL Publik Web Belum Dikonfigurasi.')
            ->assertActionHidden('downloadPng')
            ->assertActionHidden('downloadSvg');

        Config::set('web_frontend.public_url', 'http://rs-elisabeth-web.test');

        // 5. RBAC: user dengan View:DynamicForm (tanpa Update) tetap bisa membuka Link & QR Code; tanpa View:DynamicForm ditolak.
        $viewerRole = Role::findOrCreate('form_share_viewer', 'web');
        $viewerRole->syncPermissions(['ViewAny:DynamicForm', 'View:DynamicForm']);
        $viewerUser = User::factory()->create(['must_change_password' => false]);
        $viewerUser->assignRole($viewerRole);
        $this->switchUser($viewerUser);

        Livewire::test(ListDynamicForms::class)
            ->assertActionVisible(TestAction::make('shareLink')->table($publishedListed))
            ->callAction([
                TestAction::make('shareLink')->table($publishedListed),
                TestAction::make('downloadPng'),
            ])
            ->assertFileDownloaded('qr-survei-katalog-publik.png');

        $listOnlyRole = Role::findOrCreate('form_list_only_no_view', 'web');
        $listOnlyRole->syncPermissions(['ViewAny:DynamicForm']);
        $listOnlyUser = User::factory()->create(['must_change_password' => false]);
        $listOnlyUser->assignRole($listOnlyRole);
        $this->switchUser($listOnlyUser);

        Livewire::test(ListDynamicForms::class)
            ->assertActionHidden(TestAction::make('analytics')->table($publishedListed))
            ->assertActionHidden(TestAction::make('shareLink')->table($publishedListed))
            ->mountAction(TestAction::make('shareLink')->table($publishedListed))
            ->assertActionNotMounted();

        $this->switchUser($this->marketing);
        Livewire::test(ListDynamicForms::class)
            ->mountAction(TestAction::make('shareLink')->table($draftForm))
            ->assertActionNotMounted();

        // 6. Form yang di-soft-delete menyembunyikan Link & QR Code & Analitik, namun tetap bisa diakses Restore/Force Delete di ActionGroup oleh super_admin.
        $this->switchUser($this->superAdmin);
        $publishedListed->delete();

        Livewire::test(ListDynamicForms::class)
            ->filterTable('trashed', 'only')
            ->assertActionHidden(TestAction::make('analytics')->table($publishedListed))
            ->assertActionHidden(TestAction::make('shareLink')->table($publishedListed))
            ->assertActionVisible(TestAction::make('restore')->table($publishedListed))
            ->assertActionVisible(TestAction::make('forceDelete')->table($publishedListed));
    }

    public function test_editing_published_form_with_zero_submissions_updates_version_in_place_and_consolidates_drafts(): void
    {
        $this->actingAs($this->marketing);
        $versionService = app(DynamicFormVersionService::class);

        $form = DynamicForm::factory()->create([
            'title' => 'Survei Kepatuhan Username',
            'slug' => 'survei-kepatuhan-username',
            'is_public_listed' => true,
        ]);

        $versionService->syncDraftStructure($form, [[
            'section_key' => 'data_lokasi',
            'title' => 'Data Lokasi',
            'fields' => [[
                'field_key' => 'nama_ruangan',
                'type' => 'short_text',
                'label' => 'Nama Ruangan',
                'is_required' => true,
            ]],
        ]], false);
        $v1 = $versionService->publishDraftVersion($form);
        $form->refresh();

        $this->assertSame(1, DynamicFormVersion::where('dynamic_form_id', $form->id)->count());
        $this->assertSame($v1->id, $form->published_version_id);

        // 1. Membuka EditDynamicForm pada form Published otomatis menyalakan publish_now = true
        // dan menyimpan perubahan langsung memperbarui v1 in-place (tanpa membuat version_id baru).
        $editComponent = Livewire::test(EditDynamicForm::class, ['record' => $form->getRouteKey()]);
        $this->assertTrue((bool) $editComponent->get('data.publish_now'));

        $editComponent
            ->set('data.sections', [[
                'section_key' => 'data_lokasi',
                'title' => 'Data Lokasi',
                'fields' => [
                    [
                        'field_key' => 'nama_ruangan',
                        'type' => 'short_text',
                        'label' => 'Nama Ruangan',
                        'is_required' => true,
                    ],
                    [
                        'field_key' => 'tanggal_kunjungan',
                        'type' => 'date',
                        'label' => 'Tanggal Kunjungan',
                        'is_required' => true,
                    ],
                ],
            ]])
            ->call('save')
            ->assertHasNoFormErrors();

        $form->refresh();
        $v1->refresh();
        $this->assertSame(1, DynamicFormVersion::where('dynamic_form_id', $form->id)->count());
        $this->assertSame($v1->id, $form->published_version_id);
        $this->assertCount(2, $v1->schema_snapshot['sections'][0]['fields']);
        $this->assertSame(
            [$v1->id],
            DynamicFormField::whereIn('dynamic_form_version_id', $form->versions()->pluck('id'))
                ->pluck('dynamic_form_version_id')
                ->unique()
                ->values()
                ->all()
        );

        // 2. Jika admin secara sengaja mematikan publish_now = false, draf sementara v2 dibuat
        // dan tabel menampilkan indikator "Draf v2 belum terbit", lalu saat dipublikasikan
        // selama v1 masih 0 respons, draf v2 dikonsolidasikan kembali ke v1.
        Livewire::test(EditDynamicForm::class, ['record' => $form->getRouteKey()])
            ->set('data.sections', [[
                'section_key' => 'data_lokasi',
                'title' => 'Data Lokasi',
                'fields' => [
                    [
                        'field_key' => 'nama_ruangan',
                        'type' => 'short_text',
                        'label' => 'Nama Ruangan',
                        'is_required' => true,
                    ],
                    [
                        'field_key' => 'tanggal_kunjungan',
                        'type' => 'date',
                        'label' => 'Tanggal Kunjungan',
                        'is_required' => true,
                    ],
                    [
                        'field_key' => 'nama_komputer',
                        'type' => 'short_text',
                        'label' => 'Nama Komputer',
                        'is_required' => false,
                    ],
                ],
            ]])
            ->set('data.publish_now', false)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(2, DynamicFormVersion::where('dynamic_form_id', $form->id)->count());

        Livewire::test(ListDynamicForms::class)
            ->assertSee('Draf v2 belum terbit')
            ->callAction(TestAction::make('publish')->table($form->fresh()))
            ->assertHasNoActionErrors();

        $form->refresh();
        $v1->refresh();

        $this->assertSame($v1->id, $form->published_version_id);
        $this->assertSame(1, DynamicFormVersion::where('dynamic_form_id', $form->id)->count());
        $this->assertCount(3, $v1->schema_snapshot['sections'][0]['fields']);
        $this->assertSame(
            [$v1->id],
            DynamicFormField::where('dynamic_form_version_id', $v1->id)
                ->pluck('dynamic_form_version_id')
                ->unique()
                ->values()
                ->all()
        );
    }

    public function test_step3_type_aware_value_inputs_source_filtering_operator_filtering_and_multi_select_roundtrip(): void
    {
        $this->actingAs($this->marketing);

        $form = DynamicForm::factory()->create([
            'title' => 'Form Step3 Dinamis',
            'slug' => 'form-step3-dinamis',
        ]);

        app(DynamicFormVersionService::class)->syncDraftStructure($form, [
            [
                'section_key' => 'sec_1',
                'title' => 'Bagian 1',
                'fields' => [
                    [
                        'field_key' => 'kepatuhan',
                        'type' => 'radio',
                        'label' => 'Kepatuhan Browser',
                        'options' => [
                            'choices' => [
                                ['key' => 'tidak_menyimpan_patuh', 'label' => 'Tidak Menyimpan (Patuh)'],
                                ['key' => 'menyimpan_tidak_patuh', 'label' => 'Menyimpan (Tidak Patuh)'],
                            ],
                            'allow_other' => true,
                            'other_label' => 'Alasan Lain',
                        ],
                    ],
                    [
                        'field_key' => 'info_tengah',
                        'type' => 'info_block',
                        'label' => 'Info Tengah',
                        'options' => ['content' => 'Petunjuk'],
                    ],
                    [
                        'field_key' => 'skala_puas',
                        'type' => 'linear_scale',
                        'label' => 'Skala Kepuasan',
                        'options' => [
                            'min_value' => 0,
                            'max_value' => 4,
                            'min_label' => 'Buruk',
                            'max_label' => 'Sangat Baik',
                        ],
                    ],
                    [
                        'field_key' => 'bintang',
                        'type' => 'rating',
                        'label' => 'Rating Bintang',
                        'options' => ['max_stars' => 3, 'icon' => 'star'],
                    ],
                    [
                        'field_key' => 'fasilitas',
                        'type' => 'checkbox',
                        'label' => 'Fasilitas',
                        'options' => [
                            'choices' => [
                                ['key' => 'parkir', 'label' => 'Parkir'],
                                ['key' => 'wifi', 'label' => 'WiFi'],
                            ],
                            'allow_other' => false,
                        ],
                    ],
                    [
                        'field_key' => 'tanggal_periksa',
                        'type' => 'date',
                        'label' => 'Tanggal Periksa',
                    ],
                    [
                        'field_key' => 'lampiran_awal',
                        'type' => 'file_upload',
                        'label' => 'Lampiran Awal',
                    ],
                ],
            ],
            [
                'section_key' => 'sec_2',
                'title' => 'Bagian 2',
                'fields' => [
                    ['field_key' => 'upload_bukti', 'type' => 'file_upload', 'label' => 'Upload Bukti'],
                    ['field_key' => 'catatan_akhir', 'type' => 'short_text', 'label' => 'Catatan Akhir'],
                ],
            ],
        ], false);

        // 1. Cek dropdown dinamis untuk berbagai tipe sumber & penyaringan pertanyaan sebelum target
        $component = Livewire::test(EditDynamicForm::class, ['record' => $form->getRouteKey()])
            ->fillForm([
                'title' => 'Form Step3 Dinamis',
                'slug' => 'form-step3-dinamis',
                'logic_config' => [
                    'visibility_rules' => [
                        [
                            'target_field_key' => 'skala_puas',
                            'match' => 'all',
                            'rules' => [
                                ['source_field_key' => 'kepatuhan', 'operator' => 'equals', 'value' => 'menyimpan_tidak_patuh'],
                            ],
                        ],
                        [
                            'target_field_key' => 'upload_bukti',
                            'match' => 'any',
                            'rules' => [
                                ['source_field_key' => 'fasilitas', 'operator' => 'in', 'value' => ['parkir', 'wifi']],
                                ['source_field_key' => 'skala_puas', 'operator' => 'lte', 'value' => '2'],
                                ['source_field_key' => 'bintang', 'operator' => 'equals', 'value' => '3'],
                                ['source_field_key' => 'tanggal_periksa', 'operator' => 'equals', 'value' => '2026-05-01'],
                                ['source_field_key' => 'lampiran_awal', 'operator' => 'is_not_empty', 'value' => null],
                            ],
                        ],
                    ],
                ],
            ]);

        $schema = $component->instance()->form;
        $vrItems = collect($schema->getComponentByStatePath('logic_config.visibility_rules', withHidden: true)->getChildSchemas(withHidden: true))->values();

        // Aturan 1: target = skala_puas -> hanya kepatuhan yang muncul sebagai source (bukan info_tengah, skala_puas, atau field setelahnya)
        $vr1Rule = collect($vrItems[0]->getComponentByStatePath('rules', withHidden: true)->getChildSchemas(withHidden: true))->first();
        $vr1Source = $vr1Rule->getComponentByStatePath('source_field_key', withHidden: true);
        $vr1Operator = $vr1Rule->getComponentByStatePath('operator', withHidden: true);
        $vr1Value = $vr1Rule->getComponentByStatePath('value', withHidden: true);

        $this->assertSame(['kepatuhan'], array_keys($vr1Source->getOptions()));
        $this->assertArrayNotHasKey('gt', $vr1Operator->getOptions());
        $this->assertArrayNotHasKey('contains', $vr1Operator->getOptions());
        $this->assertInstanceOf(Select::class, $vr1Value);
        $this->assertFalse($vr1Value->isMultiple());
        $this->assertSame([
            'tidak_menyimpan_patuh' => 'Tidak Menyimpan (Patuh) (tidak_menyimpan_patuh)',
            'menyimpan_tidak_patuh' => 'Menyimpan (Tidak Patuh) (menyimpan_tidak_patuh)',
            '__other__' => 'Alasan Lain (__other__)',
        ], $vr1Value->getOptions());

        // Aturan 2: multi-select checkbox, linear_scale, rating, date, dan file_upload
        $vr2Rules = collect($vrItems[1]->getComponentByStatePath('rules', withHidden: true)->getChildSchemas(withHidden: true))->values();

        $checkboxValue = $vr2Rules[0]->getComponentByStatePath('value', withHidden: true);
        $this->assertInstanceOf(Select::class, $checkboxValue);
        $this->assertTrue($checkboxValue->isMultiple());
        $this->assertArrayNotHasKey('__other__', $checkboxValue->getOptions());
        $this->assertArrayHasKey('parkir', $checkboxValue->getOptions());
        $this->assertArrayHasKey('wifi', $checkboxValue->getOptions());

        $scaleValue = $vr2Rules[1]->getComponentByStatePath('value', withHidden: true);
        $this->assertInstanceOf(Select::class, $scaleValue);
        $this->assertSame(['0', '1', '2', '3', '4'], array_map('strval', array_keys($scaleValue->getOptions())));

        $ratingValue = $vr2Rules[2]->getComponentByStatePath('value', withHidden: true);
        $this->assertInstanceOf(Select::class, $ratingValue);
        $this->assertSame(['1', '2', '3'], array_map('strval', array_keys($ratingValue->getOptions())));

        $dateOperator = $vr2Rules[3]->getComponentByStatePath('operator', withHidden: true);
        $dateValue = $vr2Rules[3]->getComponentByStatePath('value', withHidden: true);
        $this->assertSame(['equals', 'not_equals', 'is_empty', 'is_not_empty'], array_keys($dateOperator->getOptions()));
        $this->assertInstanceOf(TextInput::class, $dateValue);
        $this->assertStringContainsString('YYYY-MM-DD', (string) $dateValue->getPlaceholder());

        $fileOperator = $vr2Rules[4]->getComponentByStatePath('operator', withHidden: true);
        $this->assertSame(['is_empty', 'is_not_empty'], array_keys($fileOperator->getOptions()));
        $this->assertNull($vr2Rules[4]->getComponentByStatePath('value', withHidden: true));

        // 2. Simpan lalu buka ulang EditDynamicForm untuk memastikan format multi-select & nilai terhidrasi utuh
        $component->call('save')->assertHasNoFormErrors();

        $reopened = Livewire::test(EditDynamicForm::class, ['record' => $form->getRouteKey()]);
        $hydratedRules = collect($reopened->get('data.logic_config.visibility_rules'))->keyBy('target_field_key');
        $this->assertSame('menyimpan_tidak_patuh', collect($hydratedRules['skala_puas']['rules'])->first()['value']);
        $this->assertSame(['parkir', 'wifi'], collect($hydratedRules['upload_bukti']['rules'])->first()['value']);

        // 3. Mengganti Pertanyaan Sumber dari linear_scale (operator lte) ke file_upload mereset operator & value secara aman
        $uploadVrKey = collect((array) $reopened->get('data.logic_config.visibility_rules'))
            ->search(fn (array $item): bool => ($item['target_field_key'] ?? null) === 'upload_bukti');
        $scaleRuleKey = collect((array) $reopened->get("data.logic_config.visibility_rules.{$uploadVrKey}.rules"))
            ->search(fn (array $item): bool => ($item['source_field_key'] ?? null) === 'skala_puas');

        $reopened
            ->set("data.logic_config.visibility_rules.{$uploadVrKey}.rules.{$scaleRuleKey}.source_field_key", 'lampiran_awal')
            ->assertSet("data.logic_config.visibility_rules.{$uploadVrKey}.rules.{$scaleRuleKey}.operator", 'is_empty')
            ->assertSet("data.logic_config.visibility_rules.{$uploadVrKey}.rules.{$scaleRuleKey}.value", null);
    }

    public function test_wizard_and_publish_reject_invalid_choice_keys_empty_values_duplicates_and_trim_values(): void
    {
        $this->actingAs($this->marketing);

        $baseSections = [
            [
                'section_key' => 'sec_1',
                'title' => 'Bagian 1',
                'fields' => [
                    [
                        'field_key' => 'kepatuhan',
                        'type' => 'radio',
                        'label' => 'Kepatuhan',
                        'options' => [
                            'choices' => [
                                ['key' => 'tidak_menyimpan_patuh', 'label' => 'Tidak Menyimpan (Patuh)'],
                                ['key' => 'menyimpan_tidak_patuh', 'label' => 'Menyimpan (Tidak Patuh)'],
                            ],
                        ],
                    ],
                    [
                        'field_key' => 'upload_bukti',
                        'type' => 'file_upload',
                        'label' => 'Upload Bukti',
                    ],
                    [
                        'field_key' => 'catatan',
                        'type' => 'short_text',
                        'label' => 'Catatan',
                    ],
                ],
            ],
            [
                'section_key' => 'sec_2',
                'title' => 'Bagian 2',
                'fields' => [
                    ['field_key' => 'penutup', 'type' => 'short_text', 'label' => 'Penutup'],
                ],
            ],
        ];

        // 1. Tolak nilai pembanding yang tidak cocok dengan choice.key (kasus nyata "Tidak Patuh")
        Livewire::test(CreateDynamicForm::class)
            ->fillForm([
                'title' => 'Form Key Salah',
                'slug' => 'form-key-salah',
                'mode' => DynamicFormMode::Questionnaire->value,
                'locale' => 'id',
                'sections' => $baseSections,
                'logic_config' => [
                    'visibility_rules' => [[
                        'target_field_key' => 'upload_bukti',
                        'match' => 'all',
                        'rules' => [[
                            'source_field_key' => 'kepatuhan',
                            'operator' => 'equals',
                            'value' => 'Tidak Patuh',
                        ]],
                    ]],
                ],
            ])
            ->call('create')
            ->assertHasFormErrors();

        $this->assertDatabaseMissing('dynamic_forms', ['slug' => 'form-key-salah']);

        // 2. Tolak aturan duplikat (visibility_rules, choice_jumps, section_jumps)
        Livewire::test(CreateDynamicForm::class)
            ->fillForm([
                'title' => 'Form Aturan Duplikat',
                'slug' => 'form-aturan-duplikat',
                'mode' => DynamicFormMode::Questionnaire->value,
                'locale' => 'id',
                'sections' => $baseSections,
                'logic_config' => [
                    'visibility_rules' => [
                        [
                            'target_field_key' => 'upload_bukti',
                            'match' => 'all',
                            'rules' => [[
                                'source_field_key' => 'kepatuhan',
                                'operator' => 'equals',
                                'value' => 'menyimpan_tidak_patuh',
                            ]],
                        ],
                        [
                            'target_field_key' => 'upload_bukti',
                            'match' => 'any',
                            'rules' => [[
                                'source_field_key' => 'kepatuhan',
                                'operator' => 'equals',
                                'value' => 'tidak_menyimpan_patuh',
                            ]],
                        ],
                    ],
                    'choice_jumps' => [
                        ['source_field_key' => 'kepatuhan', 'choice_key' => 'menyimpan_tidak_patuh', 'go_to_section_key' => 'sec_2'],
                        ['source_field_key' => 'kepatuhan', 'choice_key' => 'menyimpan_tidak_patuh', 'go_to_section_key' => '__submit__'],
                    ],
                    'section_jumps' => [
                        ['section_key' => 'sec_1', 'action' => 'jump', 'target_section_key' => 'sec_2'],
                        ['section_key' => 'sec_1', 'action' => 'submit', 'target_section_key' => null],
                    ],
                ],
            ])
            ->call('create')
            ->assertNotified('Aturan Percabangan Logika Tidak Valid')
            ->assertHasErrors();

        $this->assertDatabaseMissing('dynamic_forms', ['slug' => 'form-aturan-duplikat']);

        // 3. Tolak nilai pembanding kosong pada operator pembanding & operator tidak kompatibel pada file_upload
        Livewire::test(CreateDynamicForm::class)
            ->fillForm([
                'title' => 'Form Kosong Dan Operator Salah',
                'slug' => 'form-kosong-dan-operator-salah',
                'mode' => DynamicFormMode::Questionnaire->value,
                'locale' => 'id',
                'sections' => $baseSections,
                'logic_config' => [
                    'visibility_rules' => [
                        [
                            'target_field_key' => 'upload_bukti',
                            'match' => 'all',
                            'rules' => [[
                                'source_field_key' => 'kepatuhan',
                                'operator' => 'equals',
                                'value' => '   ',
                            ]],
                        ],
                        [
                            'target_field_key' => 'catatan',
                            'match' => 'all',
                            'rules' => [[
                                'source_field_key' => 'upload_bukti',
                                'operator' => 'gt',
                                'value' => '1',
                            ]],
                        ],
                    ],
                ],
            ])
            ->call('create')
            ->assertHasErrors();

        $this->assertDatabaseMissing('dynamic_forms', ['slug' => 'form-kosong-dan-operator-salah']);

        // 4. Trim spasi otomatis saat normalisasi & publikasi tetap menolak aturan tak valid di database
        $versionService = app(DynamicFormVersionService::class);
        $trimmed = $versionService->mergeLogicConfigIntoSections($baseSections, [
            'visibility_rules' => [
                [
                    'target_field_key' => 'upload_bukti',
                    'match' => 'all',
                    'rules' => [[
                        'source_field_key' => 'kepatuhan',
                        'operator' => 'equals',
                        'value' => '  menyimpan_tidak_patuh  ',
                    ]],
                ],
                [
                    'target_field_key' => 'catatan',
                    'match' => 'all',
                    'rules' => [[
                        'source_field_key' => 'kepatuhan',
                        'operator' => 'in',
                        'value' => ['  menyimpan_tidak_patuh  ', ' tidak_menyimpan_patuh ', '   '],
                    ]],
                ],
            ],
        ]);

        $this->assertSame(
            'menyimpan_tidak_patuh',
            $trimmed[0]['fields'][1]['conditional_logic']['visibility']['rules'][0]['value']
        );
        $this->assertSame(
            ['menyimpan_tidak_patuh', 'tidak_menyimpan_patuh'],
            $trimmed[0]['fields'][2]['conditional_logic']['visibility']['rules'][0]['value']
        );

        $badDraftForm = DynamicForm::factory()->create(['title' => 'Draft Buruk', 'slug' => 'draft-buruk']);
        $badSections = $baseSections;
        $badSections[0]['fields'][1]['conditional_logic'] = [
            'visibility' => [
                'enabled' => true,
                'match' => 'all',
                'rules' => [[
                    'source_field_key' => 'kepatuhan',
                    'operator' => 'equals',
                    'value' => 'Tidak Patuh',
                ]],
            ],
        ];
        $versionService->syncDraftStructure($badDraftForm, $badSections, false);

        $this->expectException(ValidationException::class);
        $versionService->publishDraftVersion($badDraftForm);
    }

    public function test_step3_value_select_remounts_across_source_switches_normalizes_scalar_arrays_and_migrates_legacy_csv(): void
    {
        $this->actingAs($this->marketing);
        $versionService = app(DynamicFormVersionService::class);

        // 1. Legacy form dengan nilai string koma ("opt_a, opt_b") pada operator `in` untuk radio
        //    harus terhidrasi sebagai array dan tersimpan ulang bersih tanpa menyentuh Step 3.
        $legacyForm = DynamicForm::factory()->create([
            'title' => 'Form Legacy Koma',
            'slug' => 'form-legacy-koma',
        ]);
        $versionService->syncDraftStructure($legacyForm, [[
            'section_key' => 'sec_1',
            'title' => 'Bagian 1',
            'fields' => [
                [
                    'field_key' => 'radio_a',
                    'type' => 'radio',
                    'label' => 'Radio A',
                    'options' => [
                        'choices' => [
                            ['key' => 'opt_a', 'label' => 'Opsi A'],
                            ['key' => 'opt_b', 'label' => 'Opsi B'],
                        ],
                    ],
                ],
                [
                    'field_key' => 'radio_b',
                    'type' => 'radio',
                    'label' => 'Radio B',
                    'options' => [
                        'choices' => [
                            ['key' => 'beta_1', 'label' => 'Beta 1'],
                            ['key' => 'beta_2', 'label' => 'Beta 2'],
                        ],
                    ],
                ],
                [
                    'field_key' => 'angka_sumber',
                    'type' => 'short_text',
                    'label' => 'Angka Sumber',
                    'options' => ['subtype' => 'number'],
                ],
                [
                    'field_key' => 'target_akhir',
                    'type' => 'short_text',
                    'label' => 'Target Akhir',
                    'conditional_logic' => [
                        'visibility' => [
                            'enabled' => true,
                            'match' => 'all',
                            'rules' => [[
                                'source_field_key' => 'radio_a',
                                'operator' => 'in',
                                'value' => 'opt_a, opt_b',
                            ]],
                        ],
                    ],
                ],
            ],
        ]], false);

        $edit = Livewire::test(EditDynamicForm::class, ['record' => $legacyForm->getRouteKey()]);
        $rulesState = array_values((array) $edit->get('data.logic_config.visibility_rules'));
        $firstCond = array_values((array) ($rulesState[0]['rules'] ?? []))[0] ?? [];
        $this->assertSame(['opt_a', 'opt_b'], $firstCond['value']);

        $edit->call('save')->assertHasNoFormErrors();
        $savedTarget = DynamicFormField::where('field_key', 'target_akhir')
            ->where('dynamic_form_version_id', $legacyForm->latestDraftVersion()->id)
            ->firstOrFail();
        $this->assertSame(['opt_a', 'opt_b'], $savedTarget->conditional_logic['visibility']['rules'][0]['value']);

        // 2. Berpindah source_field_key antar dua field bertipe Select (radio_a -> radio_b)
        //    harus menghasilkan wire:key berbeda dan hasDynamicOptions: true agar wire:ignore
        //    tidak membekukan opsi dropdown lama di browser.
        $switchTest = Livewire::test(EditDynamicForm::class, ['record' => $legacyForm->getRouteKey()])
            ->set('data.logic_config.visibility_rules', [
                'r0' => [
                    'target_field_key' => 'target_akhir',
                    'match' => 'all',
                    'rules' => [
                        'c0' => [
                            'source_field_key' => 'radio_a',
                            'operator' => 'equals',
                            'value' => 'opt_a',
                        ],
                    ],
                ],
            ]);

        $htmlA = $switchTest->html();
        preg_match('/wire:key="([^"]*rule_value_group\.value_select_single_[^"]+)"/', $htmlA, $matchA);
        $this->assertNotEmpty($matchA[1] ?? null);
        $posA = strpos($htmlA, 'selectFormComponent', (int) strpos($htmlA, $matchA[1]));
        $this->assertStringContainsString('hasDynamicOptions: true', substr($htmlA, (int) $posA, 3500));

        $switchTest->set('data.logic_config.visibility_rules.r0.rules.c0.source_field_key', 'radio_b');
        $htmlB = $switchTest->html();
        preg_match('/wire:key="([^"]*rule_value_group\.value_select_single_[^"]+)"/', $htmlB, $matchB);
        $this->assertNotEmpty($matchB[1] ?? null);
        $this->assertNotSame($matchA[1], $matchB[1]);

        // 3. Berpindah operator dari `in` ("10, 20") ke `gt` pada short_text (number)
        //    mereset nilai non-numerik agar input angka tidak menyimpan string koma basi.
        $switchTest
            ->set('data.logic_config.visibility_rules.r0.rules.c0.source_field_key', 'angka_sumber')
            ->set('data.logic_config.visibility_rules.r0.rules.c0.operator', 'in')
            ->set('data.logic_config.visibility_rules.r0.rules.c0.value', '10, 20')
            ->set('data.logic_config.visibility_rules.r0.rules.c0.operator', 'gt');

        $this->assertNull($switchTest->get('data.logic_config.visibility_rules.r0.rules.c0.value'));

        // 4. mergeLogicConfigIntoSections merapikan spasi kunci aturan dan menormalisasi
        //    array tunggal pada operator skalar menjadi string agar evaluator tidak gagal diam-diam.
        $merged = $versionService->mergeLogicConfigIntoSections(
            $versionService->loadEditableSectionsData($legacyForm),
            [
                'visibility_rules' => [[
                    'target_field_key' => ' target_akhir ',
                    'match' => 'all',
                    'rules' => [[
                        'source_field_key' => ' radio_a ',
                        'operator' => 'equals',
                        'value' => [' opt_a '],
                    ]],
                ]],
            ]
        );

        $this->assertTrue($merged[0]['fields'][3]['conditional_logic']['visibility']['enabled']);
        $this->assertSame('opt_a', $merged[0]['fields'][3]['conditional_logic']['visibility']['rules'][0]['value']);
        $this->assertSame([], app(DynamicFormLogicEvaluator::class)->validateSchemaLogic($merged));
    }

    public function test_inline_preview_and_attachment_download_support_unicode_filenames_and_enforce_mime_whitelist(): void
    {
        $form = DynamicForm::factory()->create([
            'title' => 'Survei Lampiran WebP & PDF',
            'slug' => 'survei-lampiran-webp-pdf',
        ]);

        $versionService = app(DynamicFormVersionService::class);
        $versionService->syncDraftStructure($form, [[
            'section_key' => 'sec_1',
            'title' => 'Lampiran',
            'fields' => [
                [
                    'field_key' => 'bukti_file',
                    'type' => 'file_upload',
                    'label' => 'Bukti File',
                    'options' => [
                        'allowed_extensions' => ['webp', 'pdf', 'txt'],
                        'max_file_size_mb' => 5,
                        'max_files' => 3,
                    ],
                ],
            ],
        ]]);
        $published = $versionService->publishDraftVersion($form, $this->superAdmin->email);

        Storage::disk('private-test')->put('dynamic-forms/test-preview.webp', 'RIFFxxxxWEBPVP8 ');
        Storage::disk('private-test')->put('dynamic-forms/test-preview.pdf', "%PDF-1.4\n%%EOF");
        Storage::disk('private-test')->put('dynamic-forms/test-note.txt', 'catatan teks biasa');

        $submission = DynamicFormSubmission::factory()->create([
            'dynamic_form_id' => $form->id,
            'dynamic_form_version_id' => $published->id,
            'answers' => [],
        ]);

        $webpUpload = DynamicFormUpload::create([
            'token' => (string) Str::uuid(),
            'dynamic_form_id' => $form->id,
            'submission_id' => $submission->id,
            'field_key' => 'bukti_file',
            'disk' => 'private-test',
            'storage_path' => 'dynamic-forms/test-preview.webp',
            // Nama berkas mengandung karakter non-ASCII untuk memastikan HeaderUtils::makeDisposition tidak error 500
            'original_name' => 'bukti_kepatuhan_日本語_★.webp',
            'mime_type' => 'image/webp',
            'size_bytes' => 16,
            'expires_at' => now()->addDay(),
            'attached_at' => now(),
        ]);

        $pdfUpload = DynamicFormUpload::create([
            'token' => (string) Str::uuid(),
            'dynamic_form_id' => $form->id,
            'submission_id' => $submission->id,
            'field_key' => 'bukti_file',
            'disk' => 'private-test',
            'storage_path' => 'dynamic-forms/test-preview.pdf',
            'original_name' => 'panduan-audit.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 14,
            'expires_at' => now()->addDay(),
            'attached_at' => now(),
        ]);

        $txtUpload = DynamicFormUpload::create([
            'token' => (string) Str::uuid(),
            'dynamic_form_id' => $form->id,
            'submission_id' => $submission->id,
            'field_key' => 'bukti_file',
            'disk' => 'private-test',
            'storage_path' => 'dynamic-forms/test-note.txt',
            'original_name' => 'catatan.txt',
            'mime_type' => 'text/plain',
            'size_bytes' => 18,
            'expires_at' => now()->addDay(),
            'attached_at' => now(),
        ]);

        $fileAnswerPayload = [
            [
                'upload_id' => $webpUpload->id,
                'token' => $webpUpload->token,
                'original_name' => $webpUpload->original_name,
                'mime_type' => $webpUpload->mime_type,
                'size_bytes' => $webpUpload->size_bytes,
            ],
            [
                'upload_id' => $pdfUpload->id,
                'token' => $pdfUpload->token,
                'original_name' => $pdfUpload->original_name,
                'mime_type' => $pdfUpload->mime_type,
                'size_bytes' => $pdfUpload->size_bytes,
            ],
            [
                'upload_id' => $txtUpload->id,
                'token' => $txtUpload->token,
                'original_name' => $txtUpload->original_name,
                'mime_type' => $txtUpload->mime_type,
                'size_bytes' => $txtUpload->size_bytes,
            ],
        ];

        $submission->update(['answers' => ['bukti_file' => $fileAnswerPayload]]);
        DynamicFormSubmissionAnswer::create([
            'submission_id' => $submission->id,
            'dynamic_form_id' => $form->id,
            'dynamic_form_version_id' => $published->id,
            'field_key' => 'bukti_file',
            'field_type' => 'file_upload',
            'value_text' => '3 berkas',
            'value_json' => $fileAnswerPayload,
            'created_at' => now(),
        ]);

        $this->switchUser($this->marketing);

        // 1. Mode inline=1 pada image/webp (dengan nama file Unicode) berhasil 200 dengan disposition inline & CSP
        $inlineWebpRes = $this->get(route('filament.admin.dynamic-forms.uploads.download', [
            'upload' => $webpUpload->id,
            'inline' => 1,
        ]));
        $inlineWebpRes->assertOk();
        $this->assertStringStartsWith('inline;', (string) $inlineWebpRes->headers->get('Content-Disposition'));
        $this->assertSame('image/webp', $inlineWebpRes->headers->get('Content-Type'));
        $this->assertSame('nosniff', $inlineWebpRes->headers->get('X-Content-Type-Options'));
        $this->assertNotEmpty($inlineWebpRes->headers->get('Content-Security-Policy'));
        $this->assertSame('16', $inlineWebpRes->headers->get('Content-Length'));

        // 2. Mode normal (tanpa inline=1) pada image/webp dengan nama file Unicode tetap attachment tanpa error 500
        $attachWebpRes = $this->get(route('filament.admin.dynamic-forms.uploads.download', [
            'upload' => $webpUpload->id,
        ]));
        $attachWebpRes->assertOk();
        $this->assertStringStartsWith('attachment;', (string) $attachWebpRes->headers->get('Content-Disposition'));

        // 3. Mode inline=1 pada text/plain (non-whitelist) dipaksa kembali menjadi attachment demi mencegah XSS
        $inlineTxtRes = $this->get(route('filament.admin.dynamic-forms.uploads.download', [
            'upload' => $txtUpload->id,
            'inline' => 1,
        ]));
        $inlineTxtRes->assertOk();
        $this->assertStringStartsWith('attachment;', (string) $inlineTxtRes->headers->get('Content-Disposition'));

        // 4. Halaman Detail Submission & halaman Ringkasan Analitik merender tautan pratinjau inline (?inline=1) untuk WebP & PDF, namun tidak untuk TXT
        $viewRes = $this->get(DynamicFormSubmissionResource::getUrl('view', ['record' => $submission]));
        $viewRes->assertOk()
            ->assertSee(route('filament.admin.dynamic-forms.uploads.download', ['upload' => $webpUpload->id, 'inline' => 1]), false)
            ->assertSee(route('filament.admin.dynamic-forms.uploads.download', ['upload' => $pdfUpload->id, 'inline' => 1]), false)
            ->assertDontSee(route('filament.admin.dynamic-forms.uploads.download', ['upload' => $txtUpload->id, 'inline' => 1]), false);

        $analyticsRes = $this->get(DynamicFormResource::getUrl('analytics', ['record' => $form]));
        $analyticsRes->assertOk()
            ->assertSee(route('filament.admin.dynamic-forms.uploads.download', ['upload' => $webpUpload->id, 'inline' => 1]), false)
            ->assertSee(route('filament.admin.dynamic-forms.uploads.download', ['upload' => $pdfUpload->id, 'inline' => 1]), false)
            ->assertDontSee(route('filament.admin.dynamic-forms.uploads.download', ['upload' => $txtUpload->id, 'inline' => 1]), false);
    }

    public function test_step2_builder_uses_wizard_step_wire_ignore_renderless_blur_and_scoped_partial_renders(): void
    {
        $this->actingAs($this->marketing);

        // 1. CreateDynamicForm: semua 4 <form class="fi-sc-wizard-step"> memiliki wire:ignore.self,
        //    Repeater memiliki overflow-anchor: none, tombol ergonomis tampil, dan Enter dicegah pada TextInput Step 2.
        $create = Livewire::test(CreateDynamicForm::class);
        $html = $create->html();

        preg_match_all('/<form\b[^>]*class="[^"]*\bfi-sc-wizard-step\b[^"]*"[^>]*>/i', $html, $stepForms);
        $this->assertCount(4, $stepForms[0]);
        foreach ($stepForms[0] as $formTag) {
            $this->assertStringContainsString('wire:ignore.self', $formTag);
        }

        $this->assertStringContainsString('overflow-anchor: none;', $html);
        $this->assertStringContainsString('Tambah Bagian (Section)', $html);
        $this->assertStringContainsString('Tambah Pertanyaan', $html);
        $this->assertStringContainsString('x-on:keydown.enter.prevent', $html);

        // 2. Step 1: title -> renderless auto-slug, mode -> tanpa ticket_prefix
        $titleField = $create->instance()->form->getComponentByStatePath('title', withHidden: true);
        $this->assertTrue($titleField->isRenderlessAfterStateUpdated());
        $this->assertNull($create->instance()->form->getComponentByStatePath('ticket_prefix', withHidden: true));

        $create
            ->set('data.title', 'Survei Uji Stabilitas Scroll')
            ->assertSet('data.slug', 'survei-uji-stabilitas-scroll')
            ->set('data.mode', DynamicFormMode::Feedback->value)
            ->assertHasNoErrors();

        // 3. Step 2: field auto-key memakai skipRenderAfterStateUpdated(), type menargetkan partial render field_config,
        //    dan pergantian type / subtype / allow_other berjalan tanpa error & menjaga kunci tetap sinkron.
        $sections = $create->get('data.sections');
        $secKey = array_key_first($sections);
        $fldKey = array_key_first($sections[$secKey]['fields']);

        $secTitleComponent = $create->instance()->form->getComponentByStatePath("sections.{$secKey}.title", withHidden: true);
        $fldLabelComponent = $create->instance()->form->getComponentByStatePath("sections.{$secKey}.fields.{$fldKey}.label", withHidden: true);
        $fldConfigKey = "form.sections.{$secKey}.fields.{$fldKey}.field_config";
        $fldConfigComponent = $create->instance()->form->getComponent($fldConfigKey, withHidden: true, isAbsoluteKey: true);

        $this->assertTrue($secTitleComponent->isRenderlessAfterStateUpdated());
        $this->assertTrue($fldLabelComponent->isRenderlessAfterStateUpdated());
        $this->assertNotNull($fldConfigComponent);
        $this->assertSame($fldConfigKey, $fldConfigComponent->getKey());

        // Blur title & label: key terisi otomatis namun render HTML penuh dilewati (skipRender -> partials kosong, tanpa full morph)
        $create
            ->set("data.sections.{$secKey}.title", 'Data Lokasi Kerja')
            ->assertSet("data.sections.{$secKey}.section_key", 'data_lokasi_kerja')
            ->set("data.sections.{$secKey}.fields.{$fldKey}.label", 'Apakah Pengguna Patuh?')
            ->assertSet("data.sections.{$secKey}.fields.{$fldKey}.field_key", 'apakah_pengguna_patuh');
        $this->assertSame([], $create->effects['partials'] ?? []);
        $this->assertArrayNotHasKey('html', $create->effects);

        // Ganti subtype, type, dan allow_other: hanya me-render partial Group field_config milik kartu pertanyaan ini
        $create->set("data.sections.{$secKey}.fields.{$fldKey}.options.subtype", 'number');
        $this->assertArrayHasKey("schema-component::{$fldConfigKey}", $create->effects['partials'] ?? []);
        $this->assertArrayNotHasKey('html', $create->effects);

        $create->set("data.sections.{$secKey}.fields.{$fldKey}.type", 'radio');
        $this->assertArrayHasKey("schema-component::{$fldConfigKey}", $create->effects['partials'] ?? []);
        $this->assertArrayNotHasKey('html', $create->effects);

        $create->set("data.sections.{$secKey}.fields.{$fldKey}.options.allow_other", true)
            ->assertHasNoErrors();
        $this->assertArrayHasKey("schema-component::{$fldConfigKey}", $create->effects['partials'] ?? []);
        $this->assertArrayNotHasKey('html', $create->effects);

        $choices = $create->get("data.sections.{$secKey}.fields.{$fldKey}.options.choices");
        $this->assertCount(2, $choices);
        $choiceKeys = array_keys($choices);
        $choiceLabelComponent = $create->instance()->form->getComponentByStatePath(
            "sections.{$secKey}.fields.{$fldKey}.options.choices.{$choiceKeys[0]}.label",
            withHidden: true
        );
        $this->assertTrue($choiceLabelComponent->isRenderlessAfterStateUpdated());

        $create
            ->set("data.sections.{$secKey}.fields.{$fldKey}.options.choices.{$choiceKeys[0]}.label", 'Patuh')
            ->set("data.sections.{$secKey}.fields.{$fldKey}.options.choices.{$choiceKeys[1]}.label", 'Tidak Patuh')
            ->assertSet("data.sections.{$secKey}.fields.{$fldKey}.options.choices.{$choiceKeys[0]}.key", 'patuh')
            ->assertSet("data.sections.{$secKey}.fields.{$fldKey}.options.choices.{$choiceKeys[1]}.key", 'tidak_patuh');
        $this->assertArrayNotHasKey('html', $create->effects);

        // 4. EditDynamicForm: perubahan di Langkah 2 + panggilan partiallyRenderSchemaComponent untuk Step 3 & Step 4
        //    berjalan mulus dan menyegarkan opsi pertanyaan Langkah 3 serta ringkasan kesiapan Langkah 4.
        $form = DynamicForm::factory()->create([
            'title' => 'Form Edit Partial Sync',
            'slug' => 'form-edit-partial-sync',
        ]);
        $versionService = app(DynamicFormVersionService::class);
        $versionService->syncDraftStructure($form, [[
            'section_key' => 'sec_awal',
            'title' => 'Bagian Awal',
            'fields' => [[
                'field_key' => 'pertanyaan_awal',
                'type' => 'short_text',
                'label' => 'Pertanyaan Awal',
                'is_required' => true,
            ]],
        ]], false);
        $versionService->publishDraftVersion($form, $this->marketing->email);

        $edit = Livewire::test(EditDynamicForm::class, ['record' => $form->getRouteKey()]);
        $editHtml = $edit->html();
        $this->assertStringContainsString('partiallyRenderSchemaComponent', $editHtml);
        $this->assertStringContainsString('form.step_conditional_logic_body', $editHtml);
        $this->assertStringContainsString('form.step_readiness_summary', $editHtml);

        $editSections = $edit->get('data.sections');
        $editSecKey = array_key_first($editSections);
        $editSections[$editSecKey]['fields']['fld_baru_item'] = [
            'field_key' => 'pertanyaan_baru_step2',
            'type' => 'short_text',
            'label' => 'Pertanyaan Baru Step 2',
            'is_required' => false,
        ];
        $edit->set('data.sections', $editSections);

        $edit->call('partiallyRenderSchemaComponent', 'form.step_conditional_logic_body')
            ->assertHasNoErrors();
        $this->assertArrayHasKey('schema-component::form.step_conditional_logic_body', $edit->effects['partials'] ?? []);

        $edit->call('partiallyRenderSchemaComponent', 'form.step_readiness_summary')
            ->assertHasNoErrors();
        $summaryPartialHtml = (string) ($edit->effects['partials']['schema-component::form.step_readiness_summary'] ?? '');
        $this->assertStringContainsString('Pertanyaan Input: 2', $summaryPartialHtml);
    }

    private function switchUser(User $user): void
    {
        session()->flush();
        app()->forgetScopedInstances();
        $this->actingAs($user);
    }
}
