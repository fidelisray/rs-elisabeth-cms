<?php

namespace Tests\Feature;

use App\Filament\Widgets\FeedbackSectionWidget;
use App\Filament\Widgets\FeedbackStatsWidget;
use App\Filament\Widgets\LatestFeedbackWidget;
use App\Filament\Widgets\QuestionnaireSectionWidget;
use App\Models\Feedback;
use App\Models\QuestionnaireResponse;
use App\Models\User;
use App\Services\FeedbackService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Dashboard CMS: section Kritik & Saran serta Kuesioner (IGD, Rawat Jalan, Rawat Inap).
 * Data berasal dari database, tidak ada dummy.
 */
class FeedbackDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->artisan('app:super-admin:init', ['--email' => 'admin@rs-elisabeth.com'])->assertExitCode(0);

        $user = User::where('email', 'marketing@rs-elisabeth.com')->firstOrFail();
        $user->update(['must_change_password' => false]);
        $this->actingAs($user);
    }

    public function test_feedback_section_shows_empty_state_when_no_data(): void
    {
        // assertSee meng-escape nilai; '&' di judul menjadi '&amp;' di HTML.
        Livewire::test(FeedbackSectionWidget::class)
            ->assertSee('Kritik')
            ->assertSee('Saran Pasien')
            ->assertSee('Belum ada kritik dan saran masuk.');
    }

    public function test_feedback_section_counts_only_database_rows(): void
    {
        Feedback::factory()->count(3)->create(['status' => 'baru']);
        Feedback::factory()->create(['status' => 'selesai']);

        $component = Livewire::test(FeedbackSectionWidget::class);
        $component->assertSee('FB-');

        $summary = (new FeedbackService)->getFeedbackSummary();
        $this->assertSame(4, $summary['total']);
        $this->assertSame(3, $summary['baru']);
        $this->assertSame(1, $summary['selesai']);
    }

    public function test_feedback_section_does_not_show_patient_identity(): void
    {
        Feedback::factory()->create([
            'patient_name' => 'Nama Pasien Rahasia',
            'phone_number' => '081299998888',
        ]);

        Livewire::test(FeedbackSectionWidget::class)
            ->assertDontSee('Nama Pasien Rahasia')
            ->assertDontSee('081299998888');
    }

    public function test_questionnaire_section_renders_three_subsections(): void
    {
        Livewire::test(QuestionnaireSectionWidget::class)
            ->assertSee('Kuesioner Kepuasan Pasien')
            ->assertSee('IGD')
            ->assertSee('Rawat Jalan')
            ->assertSee('Rawat Inap')
            ->assertSee('data-section="questionnaire-igd"', false)
            ->assertSee('data-section="questionnaire-rawat-jalan"', false)
            ->assertSee('data-section="questionnaire-rawat-inap"', false);
    }

    public function test_questionnaire_counts_are_per_type(): void
    {
        QuestionnaireResponse::factory()->count(2)->create(['questionnaire_type' => 'igd']);
        QuestionnaireResponse::factory()->create(['questionnaire_type' => 'rawat-jalan']);

        $service = new FeedbackService;

        $this->assertSame(2, $service->getQuestionnaireSummary('igd')['total']);
        $this->assertSame(1, $service->getQuestionnaireSummary('rawat-jalan')['total']);
        $this->assertSame(0, $service->getQuestionnaireSummary('rawat-inap')['total']);
    }

    public function test_questionnaire_satisfaction_is_derived_from_positive_scales(): void
    {
        QuestionnaireResponse::factory()->create([
            'questionnaire_type' => 'igd',
            'ratings' => ['q1' => 'Sangat Puas', 'q2' => 'Tidak Puas'],
        ]);

        $this->assertSame(50, (new FeedbackService)->getSatisfactionPercent('igd'));
    }

    public function test_dashboard_no_longer_contains_dummy_feedback(): void
    {
        $this->assertFalse(class_exists(FeedbackStatsWidget::class, false));
        $this->assertFalse(class_exists(LatestFeedbackWidget::class, false));

        Livewire::test(QuestionnaireSectionWidget::class)->assertDontSee('Budi Santoso');
        Livewire::test(FeedbackSectionWidget::class)->assertDontSee('Budi Santoso');
    }

    public function test_questionnaire_aspect_breakdown_and_enriched_widget_data_render_correctly(): void
    {
        QuestionnaireResponse::factory()->create([
            'questionnaire_type' => 'igd',
            'age_group' => '26-35',
            'payment_method' => 'BPJS',
            'suggestions' => 'Ruang tunggu IGD cukup bersih.',
            'ratings' => [
                'speed_triage_arrival' => 'Sangat Puas',
                'triage_efficiency' => 'Puas',
                'waiting_time_acceptable' => 'Tidak Setuju',
            ],
        ]);

        $service = new FeedbackService;
        $breakdown = $service->getQuestionnaireAspectBreakdown('igd');

        $this->assertNotEmpty($breakdown['highest']);
        $this->assertNotEmpty($breakdown['lowest']);
        $this->assertSame('waiting_time_acceptable', $breakdown['lowest'][0]['code']);
        $this->assertSame(100, $breakdown['lowest'][0]['percent']);
        $this->assertSame(1, $breakdown['lowest'][0]['negative']);

        Livewire::test(QuestionnaireSectionWidget::class)
            ->assertSee('Kecepatan penanganan saat tiba')
            ->assertSee('Kewajaran waktu tunggu')
            ->assertSee('100% (1/1)')
            ->assertSee('Ruang tunggu IGD cukup bersih.')
            ->assertSee('BPJS')
            ->assertSee('Baca jawaban');
    }
}
