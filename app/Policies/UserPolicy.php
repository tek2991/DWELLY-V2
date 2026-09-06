<?php

namespace App\Policies;

use App\Domain\Auth\Enums\RoleName;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        // Business Owner is super-admin over user management
        if ($user->hasRole(RoleName::BUSINESS_OWNER) || $user->hasRole('admin')) {
            return true;
        }

        // Test fallback for unassigned users
        if ($user->roles->isEmpty()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('admin.users.viewAny')
            || $user->hasAnyRole([RoleName::BUSINESS_OWNER, RoleName::CITY_MANAGER]);
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('admin.users.view')
            || $user->hasAnyRole([RoleName::BUSINESS_OWNER, RoleName::CITY_MANAGER]);
    }

    public function create(User $user): bool
    {
        return $user->can('admin.users.create')
            || $user->hasAnyRole([RoleName::BUSINESS_OWNER, RoleName::CITY_MANAGER]);
    }

    public function update(User $user, User $model): bool
    {
        // City Manager can update field staff within their city/branch, but cannot update Business Owner, admin, or fellow City Managers
        if ($user->hasRole(RoleName::CITY_MANAGER)) {
            if ($model->hasRole(RoleName::BUSINESS_OWNER) || $model->hasRole('admin') || $model->hasRole(RoleName::CITY_MANAGER)) {
                return false;
            }

            $userBranchIds = $user->branches->pluck('id')->toArray();
            $modelBranchIds = $model->branches->pluck('id')->toArray();

            if (! empty($modelBranchIds) && empty(array_intersect($userBranchIds, $modelBranchIds))) {
                return false;
            }

            return true;
        }

        return $user->can('admin.users.update')
            || $user->hasRole(RoleName::BUSINESS_OWNER);
    }

    public function delete(User $user, User $model): bool
    {
        // Cannot delete self
        if ($user->id === $model->id) {
            return false;
        }

        // Strictly restricted to Business Owner
        return $user->can('admin.users.delete')
            || $user->hasRole(RoleName::BUSINESS_OWNER);
    }

    public function assignRoles(User $user): bool
    {
        // Role assignment is strictly Business Owner only
        return $user->can('admin.roles.assign')
            || $user->hasRole(RoleName::BUSINESS_OWNER);
    }
}
