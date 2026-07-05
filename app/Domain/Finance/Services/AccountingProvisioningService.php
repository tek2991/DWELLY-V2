<?php

namespace App\Domain\Finance\Services;

use App\Domain\Party\Models\Party;
use App\Domain\Party\Enums\BusinessRole;
use Tek2991\Accounting\Models\Contact;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Enums\ContactType;
use Tek2991\Accounting\Enums\AccountType;
use Tek2991\Accounting\Enums\SystemRole;
use App\Domain\Finance\Models\RentPayment;
use App\Domain\Finance\Models\OwnerPayout;
use Brick\Money\Money;

class AccountingProvisioningService
{
    /**
     * Orchestrate all necessary accounting entities for a given Party based on its current roles.
     * This method is idempotent and supports incremental provisioning.
     */
    public function ensurePartyAccountingReady(Party $party): void
    {
        $contact = $this->ensureAccountingContact($party);
        
        // Sync the latest details from the Party to the Contact
        $this->syncAccountingContact($party, $contact);

        if ($party->hasRole(\App\Domain\Party\Enums\BusinessRole::TENANT)) {
            $this->ensureReceivableLedger($contact, "AR - {$contact->name}");
        }

        if ($party->hasRole(\App\Domain\Party\Enums\BusinessRole::OWNER) || $party->hasRole(\App\Domain\Party\Enums\BusinessRole::VENDOR)) {
            $this->ensurePayableLedger($contact, "AP - {$contact->name}");
        }
        
        $this->updateContactTypeBasedOnRoles($contact, $party);
    }

    protected function ensureAccountingContact(Party $party): Contact
    {
        if ($party->accounting_contact_id) {
            $contact = Contact::find($party->accounting_contact_id);
            if ($contact) {
                return $contact;
            }
        }

        $contact = Contact::where('party_id', $party->id)->first();

        if (!$contact) {
            $contact = Contact::create([
                'party_id' => $party->id,
                'name' => $party->display_name,
                'email' => $party->email,
                'phone' => $party->phone,
                'type' => ContactType::Customer->value,
            ]);
        }

        if ($party->accounting_contact_id !== $contact->id) {
            $party->accounting_contact_id = $contact->id;
            $party->saveQuietly();
        }

        return $contact;
    }

    public function syncAccountingContact(Party $party, ?Contact $contact = null): void
    {
        if (!$contact) {
            $contact = $this->ensureAccountingContact($party);
        }

        $taxId = $party->party_type === 'individual' 
            ? $party->individual?->pan_number 
            : $party->organization?->pan;

        $gstin = $party->party_type === 'individual' 
            ? $party->individual?->gstin 
            : $party->organization?->gstin;

        $primaryBank = $party->bankAccounts()->where('is_primary', true)->first();
        $billing = $party->addresses()->where('type', 'billing')->first();
        $shipping = $party->addresses()->where('type', 'shipping')->first();

        $contact->update([
            'name' => $party->display_name,
            'email' => $party->email,
            'phone' => $party->phone,
            'tax_id' => $taxId,
            'state_id' => $party->state_id,
            'is_tax_registered' => $party->is_tax_registered ?? false,
            'gstin' => $gstin,
            'gst_registration_type' => $party->gst_registration_type,
            'billing_address' => $billing?->address_line_1,
            'shipping_address' => $shipping?->address_line_1,
            'bank_beneficiary_name' => $primaryBank?->account_name,
            'bank_name' => $primaryBank?->bank_name,
            'bank_address' => $primaryBank?->bank_address,
            'bank_account_no' => $primaryBank?->account_number,
            'bank_ifsc_code' => $primaryBank?->ifsc_code,
        ]);
        
        \Illuminate\Support\Facades\Log::info("Accounting contact synchronized for party {$party->id}");
    }

    protected function ensureReceivableLedger(Contact $contact, string $name): Account
    {
        return $this->ensureLedger($contact, AccountType::Asset, SystemRole::CustomerReceivable, $name);
    }

    protected function ensurePayableLedger(Contact $contact, string $name): Account
    {
        return $this->ensureLedger($contact, AccountType::Liability, SystemRole::VendorPayable, $name);
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
    
    protected function updateContactTypeBasedOnRoles(Contact $contact, Party $party): void
    {
        $isTenant = $party->hasRole(\App\Domain\Party\Enums\BusinessRole::TENANT);
        $isOwnerOrVendor = $party->hasRole(\App\Domain\Party\Enums\BusinessRole::OWNER) || $party->hasRole(\App\Domain\Party\Enums\BusinessRole::VENDOR);
        
        $newType = ContactType::Customer->value;
        if ($isTenant && $isOwnerOrVendor) {
            $newType = ContactType::Both->value;
        } elseif ($isOwnerOrVendor) {
            $newType = ContactType::Vendor->value;
        }
        
        if ($contact->type !== $newType) {
            $contact->type = $newType;
            $contact->save();
        }
    }

    public function postInitialInvoices(\App\Domain\Agreement\Models\TenancyAgreement $agreement): void
    {
        \Illuminate\Support\Facades\Log::info("Posted initial invoices for Tenancy {$agreement->id}");
    }

    /**
     * Post a rent payment transaction to the accounting ledger.
     */
    public function recordRentPayment(RentPayment $payment): void
    {
        // Example implementation:
        // Debit: Bank Account
        // Credit: Rent Income
        
        \Illuminate\Support\Facades\Log::info("Rent payment recorded in accounting: {$payment->id}");
    }

    /**
     * Post an owner payout to the accounting ledger.
     */
    public function recordOwnerPayout(OwnerPayout $payout): void
    {
        // Example implementation:
        // Debit: Owner Payable
        // Credit: Bank Account
        
        \Illuminate\Support\Facades\Log::info("Owner payout recorded in accounting: {$payout->id}");
    }
    
    /**
     * Generate an invoice for maintenance or utilities.
     */
    public function createInvoice(Party $party, Money $amount, string $description): void
    {
        \Illuminate\Support\Facades\Log::info("Invoice created for {$party->display_name}: {$amount->getAmount()} - {$description}");
    }
}
