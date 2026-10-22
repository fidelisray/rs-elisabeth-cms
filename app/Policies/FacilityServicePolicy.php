<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FacilityService;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class FacilityServicePolicy
{
    use HandlesAuthorization;

    /**
     * Restore dan hard delete hanya untuk super_admin (I18N-9.2, I-15).
     */
    private function isSuperAdmin(AuthUser $authUser): bool
    {
        return method_exists($authUser, 'hasRole') && $authUser->hasRole('super_admin');
    }

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:FacilityService');
    }

    public function view(AuthUser $authUser, FacilityService $facilityService): bool
    {
        return $authUser->can('View:FacilityService');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:FacilityService');
    }

    public function update(AuthUser $authUser, FacilityService $facilityService): bool
    {
        return $authUser->can('Update:FacilityService');
    }

    public function delete(AuthUser $authUser, FacilityService $facilityService): bool
    {
        return $authUser->can('Delete:FacilityService');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:FacilityService');
    }

    public function restore(AuthUser $authUser, FacilityService $facilityService): bool
    {
        return $this->isSuperAdmin($authUser) && $authUser->can('Restore:FacilityService');
    }

    public function forceDelete(AuthUser $authUser, FacilityService $facilityService): bool
    {
        return $this->isSuperAdmin($authUser) && $authUser->can('ForceDelete:FacilityService');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $this->isSuperAdmin($authUser) && $authUser->can('ForceDeleteAny:FacilityService');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $this->isSuperAdmin($authUser) && $authUser->can('RestoreAny:FacilityService');
    }

    public function replicate(AuthUser $authUser, FacilityService $facilityService): bool
    {
        return $authUser->can('Replicate:FacilityService');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:FacilityService');
    }
}
