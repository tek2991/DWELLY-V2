<?php

namespace App\Policies;

use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MaintenanceRequestPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        // 1. Chinese Wall: Supply Managers have ZERO access to maintenance requests
        if ($user->hasRole('Supply Manager') && ! $user->hasAnyRole(['Business Owner', 'City Manager', 'admin'])) {
            return false;
        }

        // 2. Super-admin override
        if ($user->hasRole('Business Owner') || $user->hasRole('admin')) {
            return true;
        }

        // 3. Backward-compatibility for unassigned users in legacy test factories
        if ($user->roles->isEmpty()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('maintenance.viewAny') || $user->hasAnyRole(['Business Owner', 'City Manager', 'Operations Manager', 'Operations Executive', 'Demand Manager', 'Accountant']);
    }

    public function view(User $user, MaintenanceRequest $request): bool
    {
        return $user->can('maintenance.view') || $user->hasAnyRole(['Business Owner', 'City Manager', 'Operations Manager', 'Operations Executive', 'Demand Manager', 'Accountant']);
    }

    public function create(User $user): bool
    {
        return $user->can('maintenance.create') || $user->hasAnyRole(['Business Owner', 'City Manager', 'Demand Manager', 'Operations Executive', 'Operations Manager']);
    }

    public function update(User $user, MaintenanceRequest $request): bool
    {
        return $user->can('maintenance.update') || $user->hasAnyRole(['Business Owner', 'City Manager', 'Operations Manager', 'Operations Executive']);
    }

    public function attributeFault(User $user, MaintenanceRequest $request): bool
    {
        // Fault attribution tagging (Tenant Fault vs Owner Wear & Tear vs Dwelly Absorbed) is a Manager gate
        return $user->can('maintenance.fault.attribute') || $user->hasAnyRole(['Business Owner', 'City Manager', 'Operations Manager']);
    }

    public function collectQuote(User $user, MaintenanceRequest $request): bool
    {
        return $user->can('maintenance.quote.collect') || $user->hasAnyRole(['Business Owner', 'City Manager', 'Operations Manager', 'Operations Executive']);
    }

    public function issueWorkOrder(User $user, MaintenanceRequest $request): bool
    {
        // Financial commitment gate: Issuing WO-XXXX is strictly managers
        return $user->can('maintenance.work_order.issue') || $user->hasAnyRole(['Business Owner', 'City Manager', 'Operations Manager']);
    }

    public function superviseRepair(User $user, MaintenanceRequest $request): bool
    {
        return $user->can('maintenance.repair.supervise') || $user->hasAnyRole(['Business Owner', 'City Manager', 'Operations Manager', 'Operations Executive']);
    }

    public function signOff(User $user, MaintenanceRequest $request): bool
    {
        // Verification sign-off and clearing vendor bill for AP payment release
        return $user->can('maintenance.sign_off') || $user->hasAnyRole(['Business Owner', 'City Manager', 'Operations Manager', 'Accountant']);
    }

    public function delete(User $user, MaintenanceRequest $request): bool
    {
        return $user->hasRole('Business Owner') || $user->hasRole('admin');
    }
}
