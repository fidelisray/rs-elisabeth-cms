<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\BannerPromotion;
use Illuminate\Auth\Access\HandlesAuthorization;

class BannerPromotionPolicy
{
    use HandlesAuthorization;
    
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
        return $authUser->can('Restore:BannerPromotion');
    }

    public function forceDelete(AuthUser $authUser, BannerPromotion $bannerPromotion): bool
    {
        return $authUser->can('ForceDelete:BannerPromotion');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:BannerPromotion');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:BannerPromotion');
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