<?php

namespace Database\Factories;

use App\Enums\FeedbackTicketStatus;
use App\Models\DynamicForm;
use App\Models\DynamicFormSubmission;
use App\Models\DynamicFormVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DynamicFormSubmission>
 */
class DynamicFormSubmissionFactory extends Factory
{
    protected $model = DynamicFormSubmission::class;

    public function definition(): array
    {
        return [
            'dynamic_form_id' => DynamicForm::factory(),
            'dynamic_form_version_id' => DynamicFormVersion::factory(),
            'ticket_number' => null,
            'status' => null,
            'answers' => [],
            'locale' => 'id',
            'source' => 'web_public',
            'submitted_at' => now(),
            'created_by' => 'system',
        ];
    }

    public function feedbackTicket(): static
    {
        return $this->state(fn () => [
            'ticket_number' => 'DFB-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'status' => FeedbackTicketStatus::Baru,
        ]);
    }
}
