<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class QuestionnaireResponsePolicy extends PatientSubmissionPolicy
{
    public function export(User $user): bool
    {
        return $this->viewAny($user) && ($user->isSuperAdmin() || $user->can('Export:QuestionnaireResponse'));
    }
}
