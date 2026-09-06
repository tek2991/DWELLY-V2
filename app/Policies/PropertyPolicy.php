<?php

namespace App\Policies;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Property\Models\Property;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PropertyPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole(RoleName::BUSINESS_OWNER) || $user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('property.viewAny');
    }

    public function view(User $user, Property $property): bool
    {
        return $user->can('property.view');
    }

    public function create(User $user): bool
    {
        return $user->can('property.create');
    }

    public function update(User $user, Property $property): bool
    {
        $status = strtolower($property->status ?? 'draft');
        $onboardingStatus = $property->onboardingProject?->status ?? 'Draft';

        // When in draft or onboarding phase
        if (in_array($status, ['draft', 'onboarding']) || $onboardingStatus !== 'Activated') {
            return $user->can('property.update');
        }

        // Once Live / Active (Vacant or Occupied), update is restricted to managerial roles
        return $user->hasAnyRole([RoleName::BUSINESS_OWNER, RoleName::CITY_MANAGER, RoleName::OPERATIONS_MANAGER]);
    }

    public function review(User $user, Property $property): bool
    {
        return $user->can('property.review') || $user->hasAnyRole([RoleName::BUSINESS_OWNER, RoleName::CITY_MANAGER, RoleName::OPERATIONS_MANAGER]);
    }

    public function activate(User $user, Property $property): bool
    {
        return $user->can('property.activate') || $user->hasAnyRole([RoleName::BUSINESS_OWNER, RoleName::CITY_MANAGER, RoleName::OPERATIONS_MANAGER]);
    }

    public function updateStructure(User $user, Property $property): bool
    {
        return $user->can('property.structure.update') || $user->hasAnyRole([RoleName::BUSINESS_OWNER, RoleName::CITY_MANAGER, RoleName::OPERATIONS_MANAGER]);
    }

    public function viewFinancials(User $user, Property $property): bool
    {
        // Demand Managers and Operations Executives are strictly forbidden from viewing financials
        if ($user->hasAnyRole([RoleName::DEMAND_MANAGER, RoleName::OPERATIONS_EXECUTIVE]) && ! $user->hasAnyRole([RoleName::BUSINESS_OWNER, RoleName::CITY_MANAGER, RoleName::ACCOUNTANT, RoleName::OPERATIONS_MANAGER, RoleName::SUPPLY_MANAGER])) {
            return false;
        }

        return $user->can('property.financials.view') || $user->can('finance.access');
    }

    public function manageFinancials(User $user, Property $property): bool
    {
        return $user->can('property.financials.manage') || $user->hasAnyRole([RoleName::BUSINESS_OWNER, RoleName::CITY_MANAGER, RoleName::ACCOUNTANT]);
    }

    public function archive(User $user, Property $property): bool
    {
        return $user->can('property.archive') || $user->hasAnyRole([RoleName::BUSINESS_OWNER, RoleName::CITY_MANAGER]);
    }

    public function delete(User $user, Property $property): bool
    {
        return false;
    }
}
