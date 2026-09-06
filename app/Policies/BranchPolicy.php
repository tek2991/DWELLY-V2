<?php

namespace App\Policies;

use App\Domain\Auth\Enums\RoleName;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BranchPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole(RoleName::BUSINESS_OWNER) || $user->hasRole('admin')) {
            return true;
        }

        if ($user->roles->isEmpty()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('admin.geographic.viewAny')
            || $user->hasAnyRole([RoleName::BUSINESS_OWNER, RoleName::CITY_MANAGER]);
    }

    public function view(User $user, Branch $branch): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('admin.geographic.manage')
            || $user->hasRole(RoleName::BUSINESS_OWNER);
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->can('admin.geographic.manage')
            || $user->hasRole(RoleName::BUSINESS_OWNER);
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $user->can('admin.geographic.manage')
            || $user->hasRole(RoleName::BUSINESS_OWNER);
    }
}
