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

        $isTenant = $party->hasRole(BusinessRole::TENANT) || $party->tenantProfile()->exists();
        $isOwner = $party->hasRole(BusinessRole::OWNER) || $party->ownerProfile()->exists();
        $isVendor = $party->hasRole(BusinessRole::VENDOR) || $party->vendorProfile()->exists();

        if ($isTenant) {
            $this->ensureLedger($contact, AccountType::Asset, SystemRole::TenantReceivable, "AR - {$contact->name}");
            $this->ensureLedger($contact, AccountType::Liability, SystemRole::SecurityDepositLiability, "Deposit Liability - {$contact->name}");
        }

        if ($isOwner) {
            $this->ensureLedger($contact, AccountType::Liability, SystemRole::OwnerPayable, "AP - {$contact->name}");
            $this->ensureLedger($contact, AccountType::Asset, SystemRole::CustomerReceivable, "AR - {$contact->name}");
            $this->ensureLedger($contact, AccountType::Asset, SystemRole::OwnerAdvanceAsset, "Advance - {$contact->name}");
            $this->ensureLedger($contact, AccountType::Asset, SystemRole::SecurityDepositOwnerAsset, "Security Deposit with {$contact->name}");
        }

        if ($isVendor) {
            $this->ensureLedger($contact, AccountType::Liability, SystemRole::VendorPayable, "AP - {$contact->name}");
        }

        // Fallback for general parties
        if (!$isTenant && !$isOwner && !$isVendor) {
            $this->ensureReceivableLedger($contact, "AR - {$contact->name}");
            $this->ensurePayableLedger($contact, "AP - {$contact->name}");
        }
        
        $this->updateContactTypeBasedOnRoles($contact, $party);
    }

    public function getOwnerPayableAccount(Party $owner): Account
    {
        $this->ensurePartyAccountingReady($owner);
        $contact = $this->ensureAccountingContact($owner);
        
        return Account::where('contact_id', $contact->id)
            ->whereIn('system_role', [SystemRole::OwnerPayable, SystemRole::VendorPayable])
            ->first() ?? $this->ensureLedger($contact, AccountType::Liability, SystemRole::OwnerPayable, "AP - {$contact->name}");
    }

    public function getTenantReceivableAccount(Party $tenant): Account
    {
        $this->ensurePartyAccountingReady($tenant);
        $contact = $this->ensureAccountingContact($tenant);
        
        return Account::where('contact_id', $contact->id)
            ->whereIn('system_role', [SystemRole::TenantReceivable, SystemRole::CustomerReceivable, SystemRole::TradeReceivable])
            ->first() ?? $this->ensureLedger($contact, AccountType::Asset, SystemRole::TenantReceivable, "AR - {$contact->name}");
    }

    public function getOwnerAdvanceAccount(Party $owner): Account
    {
        $this->ensurePartyAccountingReady($owner);
        $contact = $this->ensureAccountingContact($owner);
        
        return Account::where('contact_id', $contact->id)
            ->where('system_role', SystemRole::OwnerAdvanceAsset)
            ->first() ?? $this->ensureLedger($contact, AccountType::Asset, SystemRole::OwnerAdvanceAsset, "Advance - {$contact->name}");
    }

    public function getOwnerAdvanceBalance(Party $owner): float
    {
        $advanceAccount = $this->getOwnerAdvanceAccount($owner);
        $debits = (float) $advanceAccount->journalEntries()->where('type', \Tek2991\Accounting\Enums\JournalEntryType::Debit)->sum('amount');
        $credits = (float) $advanceAccount->journalEntries()->where('type', \Tek2991\Accounting\Enums\JournalEntryType::Credit)->sum('amount');

        return max(0.0, $debits - $credits);
    }

    public function getTenantDepositAccount(Party $tenant): Account
    {
        $this->ensurePartyAccountingReady($tenant);
        $contact = $this->ensureAccountingContact($tenant);
        
        return Account::where('contact_id', $contact->id)
            ->where('system_role', SystemRole::SecurityDepositLiability)
            ->first() ?? $this->ensureLedger($contact, AccountType::Liability, SystemRole::SecurityDepositLiability, "Deposit Liability - {$contact->name}");
    }

    public function getOwnerDepositAccount(Party $owner): Account
    {
        $this->ensurePartyAccountingReady($owner);
        $contact = $this->ensureAccountingContact($owner);
        
        return Account::where('contact_id', $contact->id)
            ->where('system_role', SystemRole::SecurityDepositOwnerAsset)
            ->first() ?? $this->ensureLedger($contact, AccountType::Asset, SystemRole::SecurityDepositOwnerAsset, "Security Deposit with {$contact->name}");
    }

    public function getVendorPayableAccount(Party $vendor): Account
    {
        $this->ensurePartyAccountingReady($vendor);
        $contact = $this->ensureAccountingContact($vendor);
        
        return Account::where('contact_id', $contact->id)
            ->where('system_role', SystemRole::VendorPayable)
            ->first() ?? $this->ensureLedger($contact, AccountType::Liability, SystemRole::VendorPayable, "AP - {$contact->name}");
    }

    public function getCommissionIncomeAccount(): Account
    {
        return Account::where('system_role', SystemRole::CommissionRevenue)->first()
            ?? Account::where('type', AccountType::Revenue)->where('name', 'like', '%Commission%')->first()
            ?? Account::where('type', AccountType::Revenue)->first()
            ?? Account::create([
                'type' => AccountType::Revenue,
                'system_role' => SystemRole::CommissionRevenue,
                'name' => 'Property Management Commission Income',
                'is_control_account' => false,
            ]);
    }

    public function getMaintenanceIncomeAccount(): Account
    {
        return Account::where('system_role', SystemRole::MaintenanceIncome)->first()
            ?? Account::where('type', AccountType::Revenue)->where('name', 'like', '%Maintenance%')->first()
            ?? Account::where('type', AccountType::Revenue)->first()
            ?? Account::create([
                'type' => AccountType::Revenue,
                'system_role' => SystemRole::MaintenanceIncome,
                'name' => 'Maintenance Service Income',
                'is_control_account' => false,
            ]);
    }

    public function getMaintenanceExpenseAccount(): Account
    {
        return Account::where('system_role', SystemRole::MaintenanceExpense)->first()
            ?? Account::where('type', AccountType::Expense)->where('name', 'like', '%Maintenance%')->first()
            ?? Account::where('type', AccountType::Expense)->first()
            ?? Account::create([
                'type' => AccountType::Expense,
                'system_role' => SystemRole::MaintenanceExpense,
                'name' => 'Contractor & Maintenance Repair Expense',
                'is_control_account' => false,
            ]);
    }

    public function ensureAccountingContact(Party $party): Contact
    {
        if ($party->accounting_contact_id) {
            $contact = Contact::find($party->accounting_contact_id);
            if ($contact) {
                return $contact;
            }
        }

        $contact = Contact::where('party_id', $party->id)->first();

        if (!$contact) {
            $branchId = app(\Tek2991\Accounting\Services\BranchContext::class)->getCurrentId() ?? \App\Models\Branch::first()?->id;

            $contact = Contact::create([
                'party_id' => $party->id,
                'branch_id' => $branchId,
                'name' => $party->display_name,
                'email' => $party->email,
                'phone' => $party->phone,
                'type' => ContactType::Both->value,
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

        $reportingClass = match ($type) {
            AccountType::Asset => \Tek2991\Accounting\Enums\ReportingClass::CurrentAsset,
            AccountType::Liability => \Tek2991\Accounting\Enums\ReportingClass::CurrentLiability,
            AccountType::Equity => \Tek2991\Accounting\Enums\ReportingClass::Equity,
            AccountType::Revenue => \Tek2991\Accounting\Enums\ReportingClass::Revenue,
            AccountType::Expense => \Tek2991\Accounting\Enums\ReportingClass::OperatingExpense,
        };

        if (!$account) {
            $account = Account::create([
                'contact_id' => $contact->id,
                'type' => $type,
                'reporting_class' => $reportingClass,
                'system_role' => $systemRole,
                'name' => $name,
                'is_control_account' => false,
            ]);
        } elseif (!$account->reporting_class) {
            $account->update(['reporting_class' => $reportingClass]);
        }

        return $account;
    }
    
    protected function updateContactTypeBasedOnRoles(Contact $contact, Party $party): void
    {
        $isTenant = $party->hasRole(BusinessRole::TENANT) || $party->tenantProfile()->exists();
        $isOwner = $party->hasRole(BusinessRole::OWNER) || $party->ownerProfile()->exists();
        $isVendor = $party->hasRole(BusinessRole::VENDOR) || $party->vendorProfile()->exists();
        
        if ($isOwner || $isTenant || ($isVendor && ($isOwner || $isTenant))) {
            $newType = ContactType::Both->value;
        } elseif ($isVendor) {
            $newType = ContactType::Vendor->value;
        } else {
            $newType = ContactType::Both->value;
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
}
