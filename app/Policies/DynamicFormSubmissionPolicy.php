<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DynamicFormSubmission;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class DynamicFormSubmissionPolicy
{
    use HandlesAuthorization;

    private function isActive(AuthUser $authUser): bool
    {
        return (bool) ($authUser->is_active ?? true);
    }

    public function viewAny(AuthUser $authUser): bool
    {
        return $this->isActive($authUser) && $authUser->can('ViewAny:DynamicFormSubmission');
    }

    public function view(AuthUser $authUser, DynamicFormSubmission $submission): bool
    {
        return $this->isActive($authUser) && $authUser->can('View:DynamicFormSubmission');
    }

    public function create(AuthUser $authUser): bool
    {
        return false;
    }

    public function update(AuthUser $authUser, DynamicFormSubmission $submission): bool
    {
        return false;
    }

    public function followUp(AuthUser $authUser, DynamicFormSubmission $submission): bool
    {
        return false;
    }

    public function downloadAttachment(AuthUser $authUser, DynamicFormSubmission $submission): bool
    {
        return $this->isActive($authUser) && $authUser->can('View:DynamicFormSubmission');
    }

    public function delete(AuthUser $authUser, DynamicFormSubmission $submission): bool
    {
        return false;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function restore(AuthUser $authUser, DynamicFormSubmission $submission): bool
    {
        return false;
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function forceDelete(AuthUser $authUser, DynamicFormSubmission $submission): bool
    {
        return false;
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function replicate(AuthUser $authUser, DynamicFormSubmission $submission): bool
    {
        return false;
    }
}
