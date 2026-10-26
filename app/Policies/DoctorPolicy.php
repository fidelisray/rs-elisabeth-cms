<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Doctor;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Kebijakan akses Profil Dokter:
 * - Data master bersumber dari SIMRS MedInfras (Create & Delete dilarang untuk semua role).
 * - Marketing & Super Admin memiliki izin View & Update untuk 4 field konten marketing.
 * - Staff IT hanya memiliki izin View (read-only).
 */
class DoctorPolicy
{
    use HandlesAuthorization;

    private function isSuperAdmin(AuthUser $authUser): bool
    {
        return method_exists($authUser, 'hasRole') && $authUser->hasRole('super_admin');
    }

    public function viewAny(AuthUser $authUser): bool
    {
        return $this->isSuperAdmin($authUser) || $authUser->can('ViewAny:Doctor');
    }

    public function view(AuthUser $authUser, Doctor $doctor): bool
    {
        return $this->isSuperAdmin($authUser) || $authUser->can('View:Doctor');
    }

    public function create(AuthUser $authUser): bool
    {
        return false;
    }

    public function update(AuthUser $authUser, Doctor $doctor): bool
    {
        return $this->isSuperAdmin($authUser) || $authUser->can('Update:Doctor');
    }

    public function delete(AuthUser $authUser, Doctor $doctor): bool
    {
        return false;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function restore(AuthUser $authUser, Doctor $doctor): bool
    {
        return false;
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function forceDelete(AuthUser $authUser, Doctor $doctor): bool
    {
        return false;
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function replicate(AuthUser $authUser, Doctor $doctor): bool
    {
        return false;
    }

    public function reorder(AuthUser $authUser): bool
    {
        return false;
    }
}
