<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Tek2991\Accounting\Models\BankAccount;

class AccountingBankAccountPolicy
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
        return $user->can('accounting.bank.recon')
            || $user->hasAnyRole(['Business Owner', 'Accountant']);
    }

    public function view(User $user, BankAccount $account): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('accounting.bank.recon')
            || $user->hasAnyRole(['Business Owner', 'Accountant']);
    }

    public function update(User $user, BankAccount $account): bool
    {
        return $user->can('accounting.bank.recon')
            || $user->hasAnyRole(['Business Owner', 'Accountant']);
    }

    public function delete(User $user, BankAccount $account): bool
    {
        return $user->can('accounting.bank.recon')
            || $user->hasRole('Business Owner');
    }
}
