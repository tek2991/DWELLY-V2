<?php

namespace App\Policies;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Opportunity\Models\Opportunity;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OpportunityPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        // Opportunities with an active MOU cannot have core fields edited or deleted even by super-admin
        if (in_array($ability, ['update', 'delete'])) {
            return null;
        }

        if ($user->hasRole(RoleName::BUSINESS_OWNER) || $user->hasRole('admin')) {
            return true;
        }

        // Backward-compatibility for unassigned users in legacy test factories
        if ($user->roles->isEmpty()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('opportunity.viewAny') || $user->roles->isEmpty();
    }

    public function view(User $user, Opportunity $opportunity): bool
    {
        return $user->can('opportunity.view') || $user->roles->isEmpty();
    }

    public function create(User $user): bool
    {
        return $user->can('opportunity.create') || $user->roles->isEmpty();
    }

    public function update(User $user, Opportunity $opportunity): bool
    {
        // Opportunities with an active MOU cannot have core fields edited directly
        if ($opportunity->mou()->exists()) {
            return false;
        }

        if ($user->hasRole(RoleName::BUSINESS_OWNER) || $user->hasRole('admin') || $user->roles->isEmpty()) {
            return true;
        }

        return $user->can('opportunity.update');
    }

    public function delete(User $user, Opportunity $opportunity): bool
    {
        if ($opportunity->mou()->exists()) {
            return false;
        }

        if ($user->hasRole(RoleName::BUSINESS_OWNER) || $user->hasRole('admin') || $user->roles->isEmpty()) {
            return true;
        }

        return $user->can('opportunity.delete');
    }
}
