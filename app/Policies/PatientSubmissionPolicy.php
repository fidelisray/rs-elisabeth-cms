<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Submission pasien: seluruh role aktif boleh membaca; tidak diubah/dihapus manual. */
class PatientSubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->roles()->exists();
    }

    public function view(User $user, Model $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Model $record): bool
    {
        return false;
    }

    public function delete(User $user, Model $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Model $record): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, Model $record): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, Model $record): bool
    {
        return false;
    }
}
