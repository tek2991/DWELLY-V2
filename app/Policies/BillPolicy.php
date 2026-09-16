<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Tek2991\Accounting\Models\Bill;

class BillPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('Business Owner') || $user->hasRole('admin')) {
            return true;
        }

        if ($user->roles->isEmpty()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('billing.bill.viewAny')
            || $user->hasAnyRole(['Business Owner', 'Accountant', 'City Manager', 'Operations Manager']);
    }

    public function view(User $user, Bill $bill): bool
    {
        return $user->can('billing.bill.view')
            || $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('billing.bill.create')
            || $user->hasAnyRole(['Business Owner', 'Accountant', 'Operations Manager']);
    }

    public function update(User $user, Bill $bill): bool
    {
        return $user->can('billing.bill.create')
            || $user->hasAnyRole(['Business Owner', 'Accountant', 'Operations Manager']);
    }

    public function delete(User $user, Bill $bill): bool
    {
        return $user->hasRole('Business Owner');
    }

    public function approve(User $user, Bill $bill): bool
    {
        return $user->can('billing.bill.approve')
            || $user->can('maintenance.sign_off')
            || $user->hasAnyRole(['Business Owner', 'Accountant', 'City Manager', 'Operations Manager']);
    }

    public function pay(User $user, Bill $bill): bool
    {
        return $user->can('billing.bill.pay')
            || $user->hasAnyRole(['Business Owner', 'Accountant']);
    }
}
