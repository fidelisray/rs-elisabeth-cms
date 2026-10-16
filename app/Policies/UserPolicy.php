<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Manajemen user HANYA untuk super_admin (K-1, K-2).
 *
 * Pengecekan role bersifat eksplisit dan tidak bergantung pada permission,
 * sehingga kesalahan konfigurasi permission tidak dapat membuka akses
 * (pertahanan berlapis).
 */
class UserPolicy
{
    use HandlesAuthorization;

    private function isSuperAdmin(AuthUser $authUser): bool
    {
        return method_exists($authUser, 'hasRole') && $authUser->hasRole('super_admin');
    }

    public function viewAny(AuthUser $authUser): bool
    {
        return $this->isSuperAdmin($authUser);
    }

    public function view(AuthUser $authUser): bool
    {
        return $this->isSuperAdmin($authUser);
    }

    public function create(AuthUser $authUser): bool
    {
        return $this->isSuperAdmin($authUser);
    }

    public function update(AuthUser $authUser): bool
    {
        return $this->isSuperAdmin($authUser);
    }

    public function delete(AuthUser $authUser): bool
    {
        return $this->isSuperAdmin($authUser);
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $this->isSuperAdmin($authUser);
    }

    public function restore(AuthUser $authUser): bool
    {
        return $this->isSuperAdmin($authUser);
    }

    public function forceDelete(AuthUser $authUser): bool
    {
        return $this->isSuperAdmin($authUser);
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $this->isSuperAdmin($authUser);
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $this->isSuperAdmin($authUser);
    }

    public function replicate(AuthUser $authUser): bool
    {
        return $this->isSuperAdmin($authUser);
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $this->isSuperAdmin($authUser);
    }
}
