<?php

namespace Tek2991\Accounting\Observers;

use Tek2991\Accounting\Models\Contact;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Enums\AccountType;
use Tek2991\Accounting\Enums\ReportingClass;
use Tek2991\Accounting\Enums\SystemRole;

class ContactObserver
{
    /**
     * Handle the Contact "created" event.
     */
    public function created(Contact $contact): void
    {
        if ($contact->isCustomer()) {
            $hasReceivable = $contact->accounts()->whereIn('system_role', [
                SystemRole::CustomerReceivable,
                SystemRole::TenantReceivable,
            ])->exists();

            if (!$hasReceivable) {
                $receivablesControl = Account::whereIn('system_role', [SystemRole::TradeReceivable, SystemRole::TenantReceivable])
                    ->first();

                $contact->receivableAccount()->create([
                    'parent_id' => $receivablesControl?->id,
                    'type' => AccountType::Asset,
                    'reporting_class' => ReportingClass::CurrentAsset,
                    'system_role' => SystemRole::CustomerReceivable,
                    'is_control_account' => false,
                    'name' => "AR - {$contact->name}",
                    'currency_code' => \Tek2991\Accounting\Facades\Accounting::getCurrency(),
                ]);
            }
        }

        if ($contact->isVendor()) {
            $hasPayable = $contact->accounts()->whereIn('system_role', [
                SystemRole::VendorPayable,
                SystemRole::OwnerPayable,
            ])->exists();

            if (!$hasPayable) {
                $payablesControl = Account::whereIn('system_role', [SystemRole::TradePayable, SystemRole::OwnerPayable])
                    ->first();

                $contact->payableAccount()->create([
                    'parent_id' => $payablesControl?->id,
                    'type' => AccountType::Liability,
                    'reporting_class' => ReportingClass::CurrentLiability,
                    'system_role' => SystemRole::VendorPayable,
                    'is_control_account' => false,
                    'name' => "AP - {$contact->name}",
                    'currency_code' => \Tek2991\Accounting\Facades\Accounting::getCurrency(),
                ]);
            }
        }
    }

    /**
     * Handle the Contact "updated" event.
     */
    public function updated(Contact $contact): void
    {
        if ($contact->wasChanged('name')) {
            foreach ($contact->accounts as $account) {
                if (in_array($account->system_role, [SystemRole::CustomerReceivable, SystemRole::TenantReceivable])) {
                    $account->update(['name' => "AR - {$contact->name}"]);
                } elseif (in_array($account->system_role, [SystemRole::VendorPayable, SystemRole::OwnerPayable])) {
                    $account->update(['name' => "AP - {$contact->name}"]);
                } elseif ($account->system_role === SystemRole::SecurityDepositLiability) {
                    $account->update(['name' => "Deposit Liability - {$contact->name}"]);
                } elseif ($account->system_role === SystemRole::OwnerAdvanceAsset) {
                    $account->update(['name' => "Advance - {$contact->name}"]);
                } elseif ($account->system_role === SystemRole::SecurityDepositOwnerAsset) {
                    $account->update(['name' => "Security Deposit with {$contact->name}"]);
                }
            }
        }
    }
}
