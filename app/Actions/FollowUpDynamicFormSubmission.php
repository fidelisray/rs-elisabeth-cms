<?php

namespace App\Actions;

use App\Enums\FeedbackTicketStatus;
use App\Models\AuditLog;
use App\Models\DynamicFormSubmission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class FollowUpDynamicFormSubmission
{
    public function handle(DynamicFormSubmission $submission, array $data): void
    {
        Gate::authorize('followUp', $submission);

        $validated = Validator::make($data, [
            'status' => ['required', Rule::in(array_map(fn (FeedbackTicketStatus $s) => $s->value, FeedbackTicketStatus::cases()))],
            'internal_note' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        DB::transaction(function () use ($submission, $validated): void {
            $locked = DynamicFormSubmission::whereKey($submission->getKey())->lockForUpdate()->firstOrFail();
            $previousStatus = $locked->status instanceof FeedbackTicketStatus
                ? $locked->status->value
                : $locked->status;

            // Hanya kolom status & catatan internal yang berubah; jawaban responden tetap utuh.
            $locked->update([
                'status' => $validated['status'],
                'internal_note' => $validated['internal_note'] ?? null,
            ]);

            $newStatus = $locked->status instanceof FeedbackTicketStatus
                ? $locked->status->value
                : $locked->status;

            AuditLog::record('dynamic_form_submission.followed_up', $locked, [
                'previous_status' => $previousStatus,
                'status' => $newStatus,
                'note_changed' => $locked->wasChanged('internal_note'),
                'source' => 'panel',
            ]);
        });
    }
}
