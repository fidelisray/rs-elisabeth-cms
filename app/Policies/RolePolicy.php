<?php

namespace App\Policies;

use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Manajemen role dan permission HANYA untuk super_admin (K-2).
 * Pengecekan eksplisit, tidak bergantung pada permission.
 */
class RolePolicy
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

    public function view(AuthUser $authUser, \Spatie\Permission\Models\Role $role): bool
    {
        return $this->isSuperAdmin($authUser);
    }

    public function create(AuthUser $authUser): bool
    {
        return $this->isSuperAdmin($authUser);
    }

    public function update(AuthUser $authUser, \Spatie\Permission\Models\Role $role): bool
    {
        return $this->isSuperAdmin($authUser);
    }

    public function delete(AuthUser $authUser, \Spatie\Permission\Models\Role $role): bool
    {
        // Role super_admin tidak dapat dihapus oleh siapa pun (K-7).
        return $this->isSuperAdmin($authUser) && $role->name !== Role::SUPER_ADMIN;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $this->isSuperAdmin($authUser);
    }

    public function restore(AuthUser $authUser, \Spatie\Permission\Models\Role $role): bool
    {
        return $this->isSuperAdmin($authUser);
    }

    public function forceDelete(AuthUser $authUser, \Spatie\Permission\Models\Role $role): bool
    {
        return $this->isSuperAdmin($authUser) && $role->name !== Role::SUPER_ADMIN;
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $this->isSuperAdmin($authUser);
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $this->isSuperAdmin($authUser);
    }

    public function replicate(AuthUser $authUser, \Spatie\Permission\Models\Role $role): bool
    {
        return $this->isSuperAdmin($authUser);
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $this->isSuperAdmin($authUser);
    }
}
