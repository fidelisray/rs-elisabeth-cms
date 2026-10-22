<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RoomFacility;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class RoomFacilityPolicy
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
        return $authUser->can('ViewAny:RoomFacility');
    }

    public function view(AuthUser $authUser, RoomFacility $roomFacility): bool
    {
        return $authUser->can('View:RoomFacility');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RoomFacility');
    }

    public function update(AuthUser $authUser, RoomFacility $roomFacility): bool
    {
        return $authUser->can('Update:RoomFacility');
    }

    public function delete(AuthUser $authUser, RoomFacility $roomFacility): bool
    {
        return $authUser->can('Delete:RoomFacility');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:RoomFacility');
    }

    public function restore(AuthUser $authUser, RoomFacility $roomFacility): bool
    {
        return $this->isSuperAdmin($authUser) && $authUser->can('Restore:RoomFacility');
    }

    public function forceDelete(AuthUser $authUser, RoomFacility $roomFacility): bool
    {
        return $this->isSuperAdmin($authUser) && $authUser->can('ForceDelete:RoomFacility');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $this->isSuperAdmin($authUser) && $authUser->can('ForceDeleteAny:RoomFacility');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $this->isSuperAdmin($authUser) && $authUser->can('RestoreAny:RoomFacility');
    }

    public function replicate(AuthUser $authUser, RoomFacility $roomFacility): bool
    {
        return $authUser->can('Replicate:RoomFacility');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:RoomFacility');
    }
}
