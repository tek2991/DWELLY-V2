<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Tek2991\Accounting\Models\Invoice;

class InvoicePolicy
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
        return $user->can('billing.invoice.viewAny')
            || $user->can('billing.viewAny')
            || $user->hasAnyRole(['Business Owner', 'Accountant', 'City Manager', 'Operations Manager']);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->can('billing.invoice.view')
            || $user->can('billing.view')
            || $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('billing.invoice.create')
            || $user->hasAnyRole(['Business Owner', 'Accountant', 'Operations Manager']);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->can('billing.invoice.create')
            || $user->hasAnyRole(['Business Owner', 'Accountant']);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->hasRole('Business Owner');
    }

    public function post(User $user, Invoice $invoice): bool
    {
        return $user->can('billing.invoice.post')
            || $user->hasAnyRole(['Business Owner', 'Accountant']);
    }

    public function recordPayment(User $user, Invoice $invoice): bool
    {
        return $user->can('billing.receipt.record')
            || $user->hasAnyRole(['Business Owner', 'Accountant']);
    }
}
