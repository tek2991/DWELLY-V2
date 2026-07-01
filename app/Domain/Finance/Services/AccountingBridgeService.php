<?php

namespace App\Domain\Finance\Services;

use App\Domain\Party\Models\Party;
use App\Domain\Finance\Models\RentPayment;
use App\Domain\Finance\Models\OwnerPayout;
use Brick\Money\Money;
use Illuminate\Support\Facades\Log;
// use Tek2991\Accounting\Models\Contact;
// use Tek2991\Accounting\Models\Transaction;

class AccountingBridgeService
{
    /**
     * Create an accounting contact for a Dwelly Party (Owner, Tenant, Vendor).
     * Must be dispatched to the 'critical' queue.
     */
    public function syncContact(Party $party, string $type): void
    {
        // Example implementation delegating to tek2991/accounting
        Log::info("Accounting contact synced for party {$party->id} as {$type}");
    }

    public function postInitialInvoices(\App\Domain\Agreement\Models\TenancyAgreement $agreement): void
    {
        Log::info("Posted initial invoices for Tenancy {$agreement->id}");
    }

    /**
     * Post a rent payment transaction to the accounting ledger.
     */
    public function recordRentPayment(RentPayment $payment): void
    {
        // Example implementation:
        // Debit: Bank Account
        // Credit: Rent Income
        
        Log::info("Rent payment recorded in accounting: {$payment->id}");
    }

    /**
     * Post an owner payout to the accounting ledger.
     */
    public function recordOwnerPayout(OwnerPayout $payout): void
    {
        // Example implementation:
        // Debit: Owner Payable
        // Credit: Bank Account
        
        Log::info("Owner payout recorded in accounting: {$payout->id}");
    }
    
    /**
     * Generate an invoice for maintenance or utilities.
     */
    public function createInvoice(Party $party, Money $amount, string $description): void
    {
        Log::info("Invoice created for {$party->display_name}: {$amount->getAmount()} - {$description}");
    }
}
