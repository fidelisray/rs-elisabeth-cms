<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DynamicFormMode;
use App\Enums\DynamicFormStatus;
use App\Filament\Widgets\DashboardKpiOverviewWidget;
use App\Filament\Widgets\PatientVoiceWorkspaceWidget;
use App\Http\Middleware\SetAdminPanelLocale;
use App\Models\Article;
use App\Models\Doctor;
use App\Models\DynamicForm;
use App\Models\DynamicFormSubmission;
use App\Models\Feedback;
use App\Models\News;
use App\Models\Promotion;
use App\Models\QuestionnaireResponse;
use App\Models\Role;
use App\Models\User;
use App\Services\DashboardKpiService;
use App\Services\DynamicForm\DynamicFormVersionService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Concerns\SeedsTestRolesAndUsers;
use Tests\TestCase;

class DashboardKpiAndPatientVoiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsTestRolesAndUsers;

    private User $superAdmin;

    private User $marketing;

    private User $staffIt;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.media_disk' => 'media-test']);
        Storage::fake('media-test');

        $this->seedDefaultTestUsersAndRoles();
        $this->artisan('app:super-admin:init', ['--email' => 'admin@rs-elisabeth.com'])->assertExitCode(0);

        $this->superAdmin = User::where('email', 'admin@rs-elisabeth.com')->firstOrFail();
        $this->marketing = User::where('email', 'marketing@rs-elisabeth.com')->firstOrFail();
        $this->staffIt = User::where('email', 'it@rs-elisabeth.com')->firstOrFail();

        foreach ([$this->superAdmin, $this->marketing, $this->staffIt] as $user) {
            $user->update(['must_change_password' => false]);
        }

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_dashboard_kpi_service_computes_accurate_counts_across_all_modules_and_click_through_urls(): void
    {
        // Articles menggunakan string 'yes'/'no' di kolom is_active
        Article::factory()->create(['is_active' => true]);
        Article::factory()->create(['is_active' => false]);

        News::factory()->create(['is_published' => true]);
        News::factory()->create(['is_published' => false]);

        $today = Carbon::parse(Promotion::today(), (string) config('app.timezone', 'Asia/Jakarta'));

        // 1 promo aktif berakhir dalam 3 hari (<= 7 hari), 1 promo aktif jangka panjang
        Promotion::factory()->create([
            'is_active' => true,
            'start_date' => $today->copy()->subDay()->toDateString(),
            'end_date' => $today->copy()->addDays(3)->toDateString(),
        ]);
        Promotion::factory()->create([
            'is_active' => true,
            'start_date' => $today->copy()->subDay()->toDateString(),
            'end_date' => $today->copy()->addDays(20)->toDateString(),
        ]);

        // 1 dokter aktif lengkap, 1 dokter aktif belum lengkap
        Doctor::create([
            'paramedic_code' => 'KPI-DOC-001',
            'name' => 'dr. KPI Lengkap',
            'specialty_code' => 'KSM01',
            'specialty_name' => 'Penyakit Dalam',
            'service_unit_name' => 'Poliklinik Penyakit Dalam',
            'is_active' => true,
            'bio' => ['id' => 'Bio ID', 'en' => 'Bio EN'],
            'location' => ['id' => 'Lokasi ID', 'en' => 'Location EN'],
            'experience' => ['id' => 'Pengalaman ID', 'en' => 'Experience EN'],
            'education' => ['id' => 'Pendidikan ID', 'en' => 'Education EN'],
        ]);
        Doctor::create([
            'paramedic_code' => 'KPI-DOC-002',
            'name' => 'dr. KPI Belum Lengkap',
            'specialty_code' => 'KSM01',
            'specialty_name' => 'Penyakit Dalam',
            'service_unit_name' => 'Poliklinik Penyakit Dalam',
            'is_active' => true,
            'bio' => null,
            'location' => null,
            'experience' => null,
            'education' => null,
        ]);

        Feedback::factory()->create(['status' => 'baru']);
        Feedback::factory()->create(['status' => 'diproses']);
        Feedback::factory()->create(['status' => 'selesai']);

        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\SendCacheInvalidationWebhookJob']),
            'exception' => "RuntimeException: Connection refused\n#0 stacktrace",
            'failed_at' => now(),
        ]);

        $this->actingAs($this->superAdmin);
        $cards = collect(app(DashboardKpiService::class)->buildForUser($this->superAdmin))->keyBy('key');

        $this->assertCount(5, $cards);

        // Published: 1 Article + 1 News + 2 Promotions = 4; Draft: 1 Article + 1 News = 2
        $this->assertSame('4', $cards['content_overview']['value']);
        $this->assertStringContainsString('2 draf', $cards['content_overview']['secondary']);
        $this->assertStringContainsString('filters%5Bis_published%5D%5Bvalue%5D=0', $cards['content_overview']['url']);

        $this->assertSame('2', $cards['promotions']['value']);
        $this->assertStringContainsString('expiring_soon', $cards['promotions']['url']);

        $this->assertSame('50%', $cards['doctors']['value']);
        $this->assertStringContainsString('profile_status', $cards['doctors']['url']);

        $this->assertSame('2', $cards['feedback']['value']);
        $this->assertStringContainsString('unresolved', $cards['feedback']['url']);

        $this->assertStringContainsString('1 Gagal', $cards['system_health']['value']);
        $this->assertStringContainsString('/admin/system-monitor', $cards['system_health']['url']);
    }

    public function test_dashboard_kpi_visibility_and_system_health_link_respect_role_policies(): void
    {
        $limitedRole = Role::findOrCreate('feedback_only', 'web');
        $limitedUser = User::factory()->create(['must_change_password' => false]);
        $limitedUser->assignRole($limitedRole);

        // Marketing tidak punya View:SystemMonitor -> kartu system_health fallback ke /admin/doctors
        $this->actingAs($this->marketing);
        $marketingCards = collect(app(DashboardKpiService::class)->buildForUser($this->marketing))->keyBy('key');
        $this->assertTrue($marketingCards->has('system_health'));
        $this->assertStringContainsString('/admin/doctors', $marketingCards['system_health']['url']);

        // User tanpa izin modul konten & dokter -> hanya kartu feedback (via PatientSubmissionPolicy)
        $this->actingAs($limitedUser);
        $limitedCards = collect(app(DashboardKpiService::class)->buildForUser($limitedUser))->keyBy('key');
        $this->assertSame(['feedback'], $limitedCards->keys()->all());
    }

    public function test_dashboard_renders_unified_kpi_and_tabbed_patient_voice_without_pii_or_account_widget(): void
    {
        Feedback::factory()->create([
            'ticket_number' => 'FB-20261128-SECRET01',
            'reporter_name' => 'Pelapor Rahasia KPI',
            'patient_name' => 'Pasien Rahasia KPI',
            'phone_number' => '081234567890',
            'internal_note' => 'Catatan Internal Rahasia KPI',
            'status' => 'baru',
        ]);

        QuestionnaireResponse::factory()->create([
            'questionnaire_type' => 'igd',
            'ratings' => [
                'speed_triage_arrival' => 'Sangat Puas',
                'waiting_time_acceptable' => 'Tidak Setuju',
            ],
        ]);

        $form = DynamicForm::create([
            'title' => 'Survei Unit Rawat Jalan',
            'slug' => 'survei-unit-rawat-jalan',
            'mode' => DynamicFormMode::Questionnaire,
            'locale' => 'id',
            'status' => DynamicFormStatus::Draft,
            'allow_multiple_submissions' => true,
            'is_public_listed' => true,
        ]);
        /** @var DynamicFormVersionService $versionService */
        $versionService = app(DynamicFormVersionService::class);
        $versionService->syncDraftStructure($form, [
            [
                'section_key' => 'sec_1',
                'title' => 'Umum',
                'fields' => [
                    [
                        'field_key' => 'nilai',
                        'type' => 'rating',
                        'label' => 'Penilaian',
                        'is_required' => true,
                        'options' => ['max_stars' => 5, 'icon' => 'star'],
                    ],
                ],
            ],
        ], false);
        $version = $versionService->publishDraftVersion($form, $this->superAdmin->email);

        DynamicFormSubmission::create([
            'dynamic_form_id' => $form->id,
            'dynamic_form_version_id' => $version->id,
            'locale' => 'id',
            'source' => 'web_public',
            'answers' => ['nilai' => 5],
            'submitted_at' => now(),
        ]);

        $this->actingAs($this->superAdmin);

        $response = $this->get('/admin')->assertOk();
        $response->assertSee('data-rse-dashboard-kpi-strip', false);
        $response->assertSee('data-rse-patient-voice-workspace', false);
        $response->assertSee('FB-20261128-SECRET01');
        $response->assertSee('Survei Unit Rawat Jalan');
        $response->assertDontSee('fi-wi-account', false);

        // NOL PII pasien di Dashboard!
        $response->assertDontSee('Pelapor Rahasia KPI');
        $response->assertDontSee('Pasien Rahasia KPI');
        $response->assertDontSee('081234567890');
        $response->assertDontSee('Catatan Internal Rahasia KPI');

        // Verifikasi dual-locale EN pada Dashboard
        app()->forgetScopedInstances();
        $enResponse = $this->withSession([SetAdminPanelLocale::SESSION_KEY => 'en'])
            ->get('/admin')
            ->assertOk();
        $enResponse->assertSee('Published &amp; Draft Content', false);
        $enResponse->assertSee('Patient Voice &amp; Surveys', false);
        $enResponse->assertSee('Custom Forms');

        // Verifikasi direct Livewire component render
        Livewire::test(DashboardKpiOverviewWidget::class)->assertOk();
        Livewire::test(PatientVoiceWorkspaceWidget::class)
            ->assertOk()
            ->assertDontSee('Pasien Rahasia KPI');
    }
}
