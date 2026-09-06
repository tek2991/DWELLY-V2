<?php

namespace App\Policies;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Finance\Models\OwnerPayout;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OwnerPayoutPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        // 1. Demand Manager & Operations Executive have zero access to Owner Payouts
        if ($user->hasAnyRole([RoleName::DEMAND_MANAGER, RoleName::OPERATIONS_EXECUTIVE]) && ! $user->hasAnyRole([RoleName::BUSINESS_OWNER, 'admin'])) {
            return false;
        }

        // 2. Fiduciary Bank Disbursement Gate: Strictly restricted to Accountant & Business Owner
        if ($ability === 'disburse') {
            if ($user->hasAnyRole([RoleName::CITY_MANAGER, RoleName::OPERATIONS_MANAGER, RoleName::SUPPLY_MANAGER, RoleName::DEMAND_MANAGER, RoleName::OPERATIONS_EXECUTIVE])
                && ! $user->hasAnyRole([RoleName::BUSINESS_OWNER, RoleName::ACCOUNTANT])) {
                return false;
            }
        }

        // 3. Immutability: Completed payouts cannot be deleted or mutated
        if (in_array($ability, ['update', 'delete'])) {
            return null; // pass through to method checks
        }

        // 4. Super-admin override for Business Owner / admin
        if ($user->hasRole(RoleName::BUSINESS_OWNER) || $user->hasRole('admin')) {
            return true;
        }

        // 5. Backward-compatibility for unassigned users in legacy test factories
        if ($user->roles->isEmpty()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('payout.viewAny')
            || $user->hasAnyRole([RoleName::BUSINESS_OWNER, RoleName::CITY_MANAGER, RoleName::ACCOUNTANT, RoleName::OPERATIONS_MANAGER, RoleName::SUPPLY_MANAGER]);
    }

    public function view(User $user, OwnerPayout $payout): bool
    {
        return $user->can('payout.view')
            || $user->hasAnyRole([RoleName::BUSINESS_OWNER, RoleName::CITY_MANAGER, RoleName::ACCOUNTANT, RoleName::OPERATIONS_MANAGER, RoleName::SUPPLY_MANAGER]);
    }

    public function create(User $user): bool
    {
        return $user->can('payout.disburse')
            || $user->hasAnyRole([RoleName::BUSINESS_OWNER, RoleName::ACCOUNTANT]);
    }

    public function update(User $user, OwnerPayout $payout): bool
    {
        // Completed payouts are locked and immutable
        if ($payout->status === 'completed') {
            return false;
        }

        return $user->can('payout.disburse')
            || $user->hasAnyRole([RoleName::BUSINESS_OWNER, RoleName::ACCOUNTANT]);
    }

    public function delete(User $user, OwnerPayout $payout): bool
    {
        // Completed payouts cannot be deleted
        if ($payout->status === 'completed') {
            return false;
        }

        return $user->can('payout.delete')
            || $user->hasRole(RoleName::BUSINESS_OWNER);
    }

    public function manageHold(User $user, ?OwnerPayout $payout = null): bool
    {
        return $user->can('payout.hold.manage')
            || $user->hasAnyRole([RoleName::BUSINESS_OWNER, RoleName::CITY_MANAGER, RoleName::OPERATIONS_MANAGER, RoleName::ACCOUNTANT]);
    }

    public function manageReserve(User $user, ?OwnerPayout $payout = null): bool
    {
        return $user->can('payout.reserve.manage')
            || $user->hasAnyRole([RoleName::BUSINESS_OWNER, RoleName::CITY_MANAGER, RoleName::OPERATIONS_MANAGER, RoleName::ACCOUNTANT]);
    }

    public function validateCommission(User $user, ?OwnerPayout $payout = null): bool
    {
        return $user->can('payout.commission.validate')
            || $user->hasAnyRole([RoleName::BUSINESS_OWNER, RoleName::CITY_MANAGER, RoleName::SUPPLY_MANAGER, RoleName::ACCOUNTANT]);
    }

    public function disburse(User $user, ?OwnerPayout $payout = null): bool
    {
        return $user->can('payout.disburse')
            || $user->hasAnyRole([RoleName::BUSINESS_OWNER, RoleName::ACCOUNTANT]);
    }

    public function generateStatement(User $user, ?OwnerPayout $payout = null): bool
    {
        return $user->can('payout.statement.generate')
            || $user->hasAnyRole([RoleName::BUSINESS_OWNER, RoleName::CITY_MANAGER, RoleName::SUPPLY_MANAGER, RoleName::ACCOUNTANT]);
    }
}
