<?php

namespace Tests\Feature;

use App\Filament\Resources\Feedbacks\FeedbackResource;
use App\Filament\Resources\Feedbacks\Pages\ListFeedbacks;
use App\Filament\Resources\Feedbacks\Pages\ViewFeedback;
use App\Filament\Resources\Questionnaires\Pages\ListQuestionnaires;
use App\Filament\Resources\Questionnaires\QuestionnaireResource;
use App\Models\AuditLog;
use App\Models\Feedback;
use App\Models\QuestionnaireResponse;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PatientSubmissionPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_all_roles_can_read_submission_lists_and_details_including_full_answers(): void
    {
        $feedback = Feedback::factory()->create(['complaint_detail' => 'Keluhan lengkap pasien untuk dibaca.', 'expected_solution' => 'Harapan tindak lanjut.']);
        $questionnaire = QuestionnaireResponse::factory()->create(['suggestions' => 'Saran pasien yang lengkap.']);
        $auditor = User::factory()->create(['must_change_password' => false]);
        $auditor->assignRole(Role::findOrCreate('Auditor', 'web'));
        foreach (['marketing@rs-elisabeth.com', 'it@rs-elisabeth.com', $auditor->email] as $email) {
            $this->login($email);
            $this->get(FeedbackResource::getUrl('index'))->assertOk();
            $this->get(FeedbackResource::getUrl('view', ['record' => $feedback]))->assertOk()->assertSee('Keluhan lengkap pasien untuk dibaca.');
            $this->get(QuestionnaireResource::getUrl('view', ['record' => $questionnaire]))->assertOk()->assertSee('Saran pasien yang lengkap.');
        }
    }

    public function test_feedback_list_search_and_questionnaire_service_filter_work(): void
    {
        $this->login('marketing@rs-elisabeth.com');
        $first = Feedback::factory()->create(['ticket_number' => 'FB-SEARCH-123']);
        $other = Feedback::factory()->create(['ticket_number' => 'FB-OTHER-456']);
        Livewire::test(ListFeedbacks::class)->searchTable('FB-SEARCH-123')
            ->assertCanSeeTableRecords([$first])->assertCanNotSeeTableRecords([$other]);
        $igd = QuestionnaireResponse::factory()->create(['questionnaire_type' => 'igd']);
        $inpatient = QuestionnaireResponse::factory()->create(['questionnaire_type' => 'rawat-inap']);
        Livewire::test(ListQuestionnaires::class)->filterTable('questionnaire_type', 'igd')
            ->assertCanSeeTableRecords([$igd])->assertCanNotSeeTableRecords([$inpatient]);
    }

    public function test_super_admin_can_follow_up_without_changing_patient_submission(): void
    {
        $this->artisan('app:super-admin:init')->assertSuccessful();
        $this->login('admin@rs-elisabeth.com');
        $feedback = Feedback::factory()->create();
        Livewire::test(ViewFeedback::class, ['record' => $feedback->getKey()])
            ->callAction('followUp', data: ['status' => 'diproses', 'internal_note' => 'Hubungi unit terkait.'])
            ->assertHasNoActionErrors();
        $feedback->refresh();
        $this->assertSame('diproses', $feedback->status);
        $this->assertSame('Hubungi unit terkait.', $feedback->internal_note);
        $this->assertSame('Waktu tunggu di poliklinik terlalu lama.', $feedback->complaint_detail);
        $audit = AuditLog::where('action', 'feedback.followed_up')->sole();
        $this->assertSame('diproses', $audit->metadata['status']);
        $this->assertStringNotContainsString('Hubungi unit terkait.', json_encode($audit->metadata));
    }

    public function test_marketing_and_it_cannot_invoke_followup_even_with_direct_permission(): void
    {
        $feedback = Feedback::factory()->create();
        foreach (['marketing@rs-elisabeth.com', 'it@rs-elisabeth.com'] as $email) {
            $user = $this->login($email);
            $user->givePermissionTo('Update:Feedback');
            $this->assertFalse($user->can('update', $feedback));
            Livewire::test(ViewFeedback::class, ['record' => $feedback->getKey()])
                ->assertActionHidden('followUp');
            $this->assertSame('baru', $feedback->fresh()->status);
        }
    }

    public function test_invalid_followup_is_rejected_and_submission_has_no_manual_create_or_delete(): void
    {
        $this->artisan('app:super-admin:init')->assertSuccessful();
        $this->login('admin@rs-elisabeth.com');
        $feedback = Feedback::factory()->create();
        Livewire::test(ViewFeedback::class, ['record' => $feedback->getKey()])
            ->callAction('followUp', data: ['status' => 'invalid', 'internal_note' => ''])
            ->assertHasActionErrors(['status']);
        $this->assertSame('baru', $feedback->fresh()->status);
        $this->assertFalse(FeedbackResource::canCreate());
        $this->assertFalse(FeedbackResource::canDelete($feedback));
        $this->assertFalse(QuestionnaireResource::canEdit(QuestionnaireResponse::factory()->create()));
    }

    public function test_guests_and_inactive_accounts_cannot_read_submissions(): void
    {
        $this->get(FeedbackResource::getUrl('index'))->assertRedirect();
        $user = $this->login('marketing@rs-elisabeth.com');
        $user->update(['is_active' => false]);
        $this->assertFalse($user->can('viewAny', Feedback::class));
        $this->get(QuestionnaireResource::getUrl('index'))->assertForbidden();
    }

    private function login(string $email): User
    {
        session()->flush();
        $user = User::where('email', $email)->firstOrFail();
        $user->update(['must_change_password' => false]);
        $this->actingAs($user);

        return $user;
    }
}
