<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Feedback;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class FollowUpFeedback
{
    public function handle(Feedback $feedback, array $data): void
    {
        Gate::authorize('update', $feedback);
        $validated = Validator::make($data, [
            'status' => ['required', Rule::in(Feedback::STATUSES)],
            'internal_note' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        DB::transaction(function () use ($feedback, $validated): void {
            $locked = Feedback::whereKey($feedback->getKey())->lockForUpdate()->firstOrFail();
            $previousStatus = $locked->status;
            // Hanya dua field operasional; isi laporan dan identitas pengirim tidak berubah.
            $locked->update($validated);
            AuditLog::record('feedback.followed_up', $locked, [
                'previous_status' => $previousStatus,
                'status' => $locked->status,
                'note_changed' => $locked->wasChanged('internal_note'),
                'source' => 'panel',
            ]);
        });
    }
}
