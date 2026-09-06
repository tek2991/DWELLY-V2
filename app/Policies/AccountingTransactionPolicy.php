<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Tek2991\Accounting\Models\Transaction;

class AccountingTransactionPolicy
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
        return $user->can('accounting.reports.view')
            || $user->can('accounting.journal.post')
            || $user->hasAnyRole(['Business Owner', 'Accountant', 'City Manager']);
    }

    public function view(User $user, Transaction $transaction): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('accounting.journal.post')
            || $user->hasAnyRole(['Business Owner', 'Accountant']);
    }

    public function update(User $user, Transaction $transaction): bool
    {
        return $user->can('accounting.journal.post')
            || $user->hasAnyRole(['Business Owner', 'Accountant']);
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        return $user->can('accounting.journal.post')
            || $user->hasRole('Business Owner');
    }
}
