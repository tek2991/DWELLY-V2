<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Tek2991\Accounting\Models\Account;

class AccountingAccountPolicy
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
        return $user->can('accounting.coa.manage')
            || $user->can('accounting.panel.access')
            || $user->hasAnyRole(['Business Owner', 'Accountant', 'City Manager']);
    }

    public function view(User $user, Account $account): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('accounting.coa.manage')
            || $user->hasAnyRole(['Business Owner', 'Accountant']);
    }

    public function update(User $user, Account $account): bool
    {
        return $user->can('accounting.coa.manage')
            || $user->hasAnyRole(['Business Owner', 'Accountant']);
    }

    public function delete(User $user, Account $account): bool
    {
        return $user->can('accounting.coa.manage')
            || $user->hasRole('Business Owner');
    }
}
