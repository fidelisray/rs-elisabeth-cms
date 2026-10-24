<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class FeedbackPolicy extends PatientSubmissionPolicy
{
    public function update(User $user, Model $record): bool
    {
        return $user->is_active && $user->isSuperAdmin() && $user->can('Update:Feedback');
    }
}
