<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OnlineLibrary;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class OnlineLibraryPolicy
{
    use HandlesAuthorization;

    /**
     * Restore dan hard delete hanya untuk super_admin.
     */
    private function isSuperAdmin(AuthUser $authUser): bool
    {
        return method_exists($authUser, 'hasRole') && $authUser->hasRole('super_admin');
    }

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:OnlineLibrary');
    }

    public function view(AuthUser $authUser, OnlineLibrary $onlineLibrary): bool
    {
        return $authUser->can('View:OnlineLibrary');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:OnlineLibrary');
    }

    public function update(AuthUser $authUser, OnlineLibrary $onlineLibrary): bool
    {
        return $authUser->can('Update:OnlineLibrary');
    }

    public function delete(AuthUser $authUser, OnlineLibrary $onlineLibrary): bool
    {
        return $authUser->can('Delete:OnlineLibrary');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:OnlineLibrary');
    }

    public function restore(AuthUser $authUser, OnlineLibrary $onlineLibrary): bool
    {
        return $this->isSuperAdmin($authUser) && $authUser->can('Restore:OnlineLibrary');
    }

    public function forceDelete(AuthUser $authUser, OnlineLibrary $onlineLibrary): bool
    {
        return $this->isSuperAdmin($authUser) && $authUser->can('ForceDelete:OnlineLibrary');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $this->isSuperAdmin($authUser) && $authUser->can('ForceDeleteAny:OnlineLibrary');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $this->isSuperAdmin($authUser) && $authUser->can('RestoreAny:OnlineLibrary');
    }

    public function replicate(AuthUser $authUser, OnlineLibrary $onlineLibrary): bool
    {
        return $authUser->can('Replicate:OnlineLibrary');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:OnlineLibrary');
    }
}
