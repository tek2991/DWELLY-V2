<?php

namespace App\Domain\Finance\Services;

use App\Domain\Party\Models\Party;
use App\Domain\Party\Enums\BusinessRole;
use Tek2991\Accounting\Models\Contact;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Enums\ContactType;
use Tek2991\Accounting\Enums\AccountType;
use Tek2991\Accounting\Enums\SystemRole;

class AccountingProvisioningService
{
    /**
     * Ensure the Party has an associated Accounting Contact and the required Ledgers based on their role.
     */
    public function provisionForRole(Party $party, BusinessRole $role): void
    {
        $contact = $this->ensureContact($party);

        if ($role === BusinessRole::TENANT) {
            // Tenant needs an Accounts Receivable (AR) Ledger
            $this->ensureLedger($contact, AccountType::Asset, SystemRole::AccountsReceivable, "AR - {$contact->name}");
            
            // Update contact type if it was just vendor
            if ($contact->type !== ContactType::Customer->value && $contact->type !== ContactType::Both->value) {
                $contact->type = ContactType::Both->value;
                $contact->save();
            }
        }

        if ($role === BusinessRole::OWNER || $role === BusinessRole::VENDOR) {
            // Owner/Vendor needs an Accounts Payable (AP) Ledger
            $this->ensureLedger($contact, AccountType::Liability, SystemRole::AccountsPayable, "AP - {$contact->name}");
            
            // Update contact type if it was just customer
            if ($contact->type !== ContactType::Vendor->value && $contact->type !== ContactType::Both->value) {
                $contact->type = ContactType::Both->value;
                $contact->save();
            }
        }
    }

    protected function ensureContact(Party $party): Contact
    {
        if ($party->accounting_contact_id) {
            $contact = Contact::find($party->accounting_contact_id);
            if ($contact) {
                return $contact;
            }
        }

        // Check if there is an existing contact linked to this party_id
        $contact = Contact::where('party_id', $party->id)->first();

        if (!$contact) {
            $contact = Contact::create([
                'party_id' => $party->id,
                'name' => $party->display_name,
                'email' => $party->email,
                'phone' => $party->phone,
                'type' => ContactType::Customer->value, // default, will be updated by role provisioning
            ]);
        }

        $party->accounting_contact_id = $contact->id;
        $party->saveQuietly();

        return $contact;
    }

    protected function ensureLedger(Contact $contact, AccountType $type, SystemRole $systemRole, string $name): Account
    {
        $account = Account::where('contact_id', $contact->id)
            ->where('system_role', $systemRole)
            ->first();

        if (!$account) {
            $account = Account::create([
                'contact_id' => $contact->id,
                'type' => $type,
                'system_role' => $systemRole,
                'name' => $name,
                'is_control_account' => false,
            ]);
        }

        return $account;
    }
}
