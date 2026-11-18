<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DynamicForm;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class DynamicFormPolicy
{
    use HandlesAuthorization;

    private function isActive(AuthUser $authUser): bool
    {
        return (bool) ($authUser->is_active ?? true);
    }

    private function isSuperAdmin(AuthUser $authUser): bool
    {
        return method_exists($authUser, 'hasRole') && $authUser->hasRole('super_admin');
    }

    public function viewAny(AuthUser $authUser): bool
    {
        return $this->isActive($authUser) && $authUser->can('ViewAny:DynamicForm');
    }

    public function view(AuthUser $authUser, DynamicForm $dynamicForm): bool
    {
        return $this->isActive($authUser) && $authUser->can('View:DynamicForm');
    }

    public function create(AuthUser $authUser): bool
    {
        return $this->isActive($authUser) && $authUser->can('Create:DynamicForm');
    }

    public function update(AuthUser $authUser, DynamicForm $dynamicForm): bool
    {
        return $this->isActive($authUser) && $authUser->can('Update:DynamicForm');
    }

    public function delete(AuthUser $authUser, DynamicForm $dynamicForm): bool
    {
        return $this->isActive($authUser) && $authUser->can('Delete:DynamicForm');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $this->isActive($authUser) && $authUser->can('DeleteAny:DynamicForm');
    }

    public function restore(AuthUser $authUser, DynamicForm $dynamicForm): bool
    {
        return $this->isActive($authUser) && $this->isSuperAdmin($authUser) && $authUser->can('Restore:DynamicForm');
    }

    public function forceDelete(AuthUser $authUser, DynamicForm $dynamicForm): bool
    {
        return $this->isActive($authUser) && $this->isSuperAdmin($authUser) && $authUser->can('ForceDelete:DynamicForm');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $this->isActive($authUser) && $this->isSuperAdmin($authUser) && $authUser->can('ForceDeleteAny:DynamicForm');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $this->isActive($authUser) && $this->isSuperAdmin($authUser) && $authUser->can('RestoreAny:DynamicForm');
    }

    public function replicate(AuthUser $authUser, DynamicForm $dynamicForm): bool
    {
        return $this->isActive($authUser) && $authUser->can('Replicate:DynamicForm');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $this->isActive($authUser) && $authUser->can('Reorder:DynamicForm');
    }
}
