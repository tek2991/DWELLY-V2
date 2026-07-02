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
            $receivablesControl = Account::where('system_role', SystemRole::TradeReceivable)
                ->first();

            if ($receivablesControl) {
                $contact->receivableAccount()->create([
                    'parent_id' => $receivablesControl->id,
                    'type' => AccountType::Asset,
                    'reporting_class' => ReportingClass::CurrentAsset,
                    'system_role' => SystemRole::CustomerReceivable,
                    'is_control_account' => false,
                    'name' => $contact->name,
                    'currency_code' => \Tek2991\Accounting\Facades\Accounting::getCurrency(),
                ]);
            }
        }

        if ($contact->isVendor()) {
            $payablesControl = Account::where('system_role', SystemRole::TradePayable)
                ->first();

            if ($payablesControl) {
                $contact->payableAccount()->create([
                    'parent_id' => $payablesControl->id,
                    'type' => AccountType::Liability,
                    'reporting_class' => ReportingClass::CurrentLiability,
                    'system_role' => SystemRole::VendorPayable,
                    'is_control_account' => false,
                    'name' => $contact->name,
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
            $contact->accounts()->update(['name' => $contact->name]);
        }
    }
}
