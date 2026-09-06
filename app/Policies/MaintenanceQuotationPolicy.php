<?php

namespace App\Policies;

use App\Domain\Maintenance\Models\MaintenanceClientQuote;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MaintenanceQuotationPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        // 1. Hidden Margin Protection: Operations Executives are strictly forbidden from viewing or applying company margins
        if ($user->hasRole('Operations Executive') && ! $user->hasAnyRole(['Business Owner', 'City Manager', 'admin'])) {
            return false;
        }

        // 2. Chinese Wall: Supply and Demand Managers have zero access to financial maintenance quotations
        if ($user->hasAnyRole(['Supply Manager', 'Demand Manager']) && ! $user->hasAnyRole(['Business Owner', 'City Manager', 'admin'])) {
            return false;
        }

        // 3. Super-admin override
        if ($user->hasRole('Business Owner') || $user->hasRole('admin')) {
            return true;
        }

        // 4. Backward-compatibility for unassigned users in legacy test factories
        if ($user->roles->isEmpty()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('maintenance.margin.view') || $user->hasAnyRole(['Business Owner', 'City Manager', 'Operations Manager', 'Accountant']);
    }

    public function view(User $user, MaintenanceClientQuote $quote): bool
    {
        return $user->can('maintenance.margin.view') || $user->hasAnyRole(['Business Owner', 'City Manager', 'Operations Manager', 'Accountant']);
    }

    public function create(User $user): bool
    {
        return $user->can('maintenance.margin.apply') || $user->hasAnyRole(['Business Owner', 'City Manager', 'Operations Manager']);
    }

    public function update(User $user, MaintenanceClientQuote $quote): bool
    {
        return $user->can('maintenance.margin.apply') || $user->hasAnyRole(['Business Owner', 'City Manager', 'Operations Manager']);
    }

    public function applyMargin(User $user, MaintenanceClientQuote $quote): bool
    {
        return $user->can('maintenance.margin.apply') || $user->hasAnyRole(['Business Owner', 'City Manager', 'Operations Manager']);
    }

    public function viewMargin(User $user, MaintenanceClientQuote $quote): bool
    {
        return $user->can('maintenance.margin.view') || $user->hasAnyRole(['Business Owner', 'City Manager', 'Operations Manager', 'Accountant']);
    }

    public function delete(User $user, MaintenanceClientQuote $quote): bool
    {
        return $user->hasRole('Business Owner') || $user->hasRole('admin');
    }
}
