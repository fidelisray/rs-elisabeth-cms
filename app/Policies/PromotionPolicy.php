<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Promotion;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PromotionPolicy
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
        return $authUser->can('ViewAny:Promotion');
    }

    public function view(AuthUser $authUser, Promotion $promotion): bool
    {
        return $authUser->can('View:Promotion');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Promotion');
    }

    public function update(AuthUser $authUser, Promotion $promotion): bool
    {
        return $authUser->can('Update:Promotion');
    }

    public function delete(AuthUser $authUser, Promotion $promotion): bool
    {
        return $authUser->can('Delete:Promotion');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Promotion');
    }

    public function restore(AuthUser $authUser, Promotion $promotion): bool
    {
        return $this->isSuperAdmin($authUser) && $authUser->can('Restore:Promotion');
    }

    public function forceDelete(AuthUser $authUser, Promotion $promotion): bool
    {
        return $this->isSuperAdmin($authUser) && $authUser->can('ForceDelete:Promotion');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $this->isSuperAdmin($authUser) && $authUser->can('ForceDeleteAny:Promotion');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $this->isSuperAdmin($authUser) && $authUser->can('RestoreAny:Promotion');
    }

    public function replicate(AuthUser $authUser, Promotion $promotion): bool
    {
        return $authUser->can('Replicate:Promotion');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Promotion');
    }
}
