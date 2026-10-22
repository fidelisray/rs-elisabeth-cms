<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BannerPromotion;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class BannerPromotionPolicy
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
        return $authUser->can('ViewAny:BannerPromotion');
    }

    public function view(AuthUser $authUser, BannerPromotion $bannerPromotion): bool
    {
        return $authUser->can('View:BannerPromotion');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:BannerPromotion');
    }

    public function update(AuthUser $authUser, BannerPromotion $bannerPromotion): bool
    {
        return $authUser->can('Update:BannerPromotion');
    }

    public function delete(AuthUser $authUser, BannerPromotion $bannerPromotion): bool
    {
        return $authUser->can('Delete:BannerPromotion');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:BannerPromotion');
    }

    public function restore(AuthUser $authUser, BannerPromotion $bannerPromotion): bool
    {
        return $this->isSuperAdmin($authUser) && $authUser->can('Restore:BannerPromotion');
    }

    public function forceDelete(AuthUser $authUser, BannerPromotion $bannerPromotion): bool
    {
        return $this->isSuperAdmin($authUser) && $authUser->can('ForceDelete:BannerPromotion');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $this->isSuperAdmin($authUser) && $authUser->can('ForceDeleteAny:BannerPromotion');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $this->isSuperAdmin($authUser) && $authUser->can('RestoreAny:BannerPromotion');
    }

    public function replicate(AuthUser $authUser, BannerPromotion $bannerPromotion): bool
    {
        return $authUser->can('Replicate:BannerPromotion');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:BannerPromotion');
    }
}
