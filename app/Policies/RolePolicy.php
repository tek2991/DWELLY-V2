<?php

namespace App\Policies;

use App\Domain\Auth\Enums\RoleName;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RolePolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        // Allow delete() to enforce system role immutability explicitly
        if ($ability === 'delete') {
            return null;
        }

        if ($user->hasRole(RoleName::BUSINESS_OWNER) || $user->hasRole('admin')) {
            return true;
        }

        if ($user->roles->isEmpty()) {
            return true;
        }

        // Strictly forbidden for all non-owner roles
        return false;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('admin.roles.viewAny')
            || $user->hasRole(RoleName::BUSINESS_OWNER);
    }

    public function view(User $user, $role): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('admin.roles.create')
            || $user->hasRole(RoleName::BUSINESS_OWNER);
    }

    public function update(User $user, $role): bool
    {
        return $user->can('admin.roles.update')
            || $user->hasRole(RoleName::BUSINESS_OWNER);
    }

    public function delete(User $user, $role): bool
    {
        $isSystem = false;
        if ($role instanceof Role) {
            $isSystem = $role->isSystem();
        } elseif ($role instanceof \Spatie\Permission\Models\Role) {
            $isSystem = (bool) ($role->is_system ?? false) || RoleName::isSystem($role->name);
        } elseif (is_string($role)) {
            $isSystem = RoleName::isSystem($role);
        }

        // Core system roles cannot be deleted under any circumstances
        if ($isSystem) {
            return false;
        }

        if ($user->roles->isEmpty()) {
            return true;
        }

        return $user->can('admin.roles.delete')
            || $user->hasRole(RoleName::BUSINESS_OWNER);
    }
}
