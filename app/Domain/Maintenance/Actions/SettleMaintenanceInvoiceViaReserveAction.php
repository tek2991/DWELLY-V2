<?php

namespace App\Domain\Maintenance\Actions;

use App\Domain\Finance\Services\AccountingProvisioningService;
use App\Domain\Party\Models\Party;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tek2991\Accounting\Enums\InvoiceStatus;
use Tek2991\Accounting\Enums\JournalEntryType;
use Tek2991\Accounting\Enums\TransactionType;
use Tek2991\Accounting\Models\Invoice;
use Tek2991\Accounting\Services\TransactionService;

class SettleMaintenanceInvoiceViaReserveAction
{
    public function __construct(
        protected AccountingProvisioningService $provisioningService,
        protected TransactionService $txnService
    ) {}

    /**
     * Settle an unpaid maintenance invoice by drawing down from the owner's Maintenance Reserve float.
     */
    public function execute(Invoice $invoice, ?User $initiator = null): Invoice
    {
        if ($invoice->status === InvoiceStatus::Paid) {
            throw new \InvalidArgumentException("Invoice {$invoice->invoice_number} is already paid.");
        }

        return DB::transaction(function () use ($invoice, $initiator) {
            $invoice = Invoice::lockForUpdate()->find($invoice->id);

            // 1. Identify Owner Party
            $party = null;
            if ($invoice->contact && $invoice->contact->party_id) {
                $party = Party::find($invoice->contact->party_id);
            }
            if (!$party && $invoice->reference_type === \App\Domain\Maintenance\Models\MaintenanceRequest::class) {
                $request = \App\Domain\Maintenance\Models\MaintenanceRequest::find($invoice->reference_id);
                $party = $request?->owner ?? $request?->property?->owner;
            }

            if (!$party) {
                throw new \InvalidArgumentException("No owner party associated with invoice {$invoice->invoice_number}.");
            }

            // 2. Check Available Reserve Balance
            $reserveBalance = $this->provisioningService->getOwnerReserveBalance($party);
            $amountToSettle = (float) ($invoice->balance_due > 0 ? $invoice->balance_due : $invoice->grand_total);

            if ($reserveBalance < $amountToSettle) {
                throw new \InvalidArgumentException(
                    "Insufficient Maintenance Reserve balance for {$party->display_name}. " .
                    "Available: ₹" . number_format($reserveBalance, 2) . ", Required: ₹" . number_format($amountToSettle, 2) . "."
                );
            }

            // 3. Prepare Ledger Accounts
            $reserveAccount = $this->provisioningService->getOwnerReserveAccount($party);
            $incomeAccount = $this->provisioningService->getMaintenanceIncomeAccount();
            $receivableAccount = $invoice->contact?->receivableAccount 
                ?? \Tek2991\Accounting\Models\Account::where('contact_id', $invoice->contact_id)->where('type', 'asset')->first()
                ?? $incomeAccount;

            // 4. Assemble Balanced Double-Entry Entries
            // DR: Owner Maintenance Reserve [Liability] (Drawdown)
            // CR: Receivable Account / Maintenance Income [Asset/Revenue] (Settlement)
            $entries = [
                [
                    'account_id' => $reserveAccount->id,
                    'type' => JournalEntryType::Debit,
                    'amount' => $amountToSettle,
                    'description' => "Reserve Drawdown for Invoice {$invoice->invoice_number}",
                ],
                [
                    'account_id' => $receivableAccount->id,
                    'type' => JournalEntryType::Credit,
                    'amount' => $amountToSettle,
                    'description' => "Maintenance Settlement for Invoice {$invoice->invoice_number} ({$party->display_name})",
                ],
            ];

            $branchId = $invoice->branch_id ?? \App\Models\Branch::first()?->id;

            $transaction = $this->txnService->createTransaction([
                'branch_id' => $branchId,
                'type' => TransactionType::Journal,
                'description' => "Maintenance Reserve Settlement: {$invoice->invoice_number} ({$party->display_name})",
                'posted_at' => now()->toDateString(),
                'reference' => "RES-DRAW-{$invoice->invoice_number}",
                'reviewed' => true,
                'pending' => false,
            ], $entries);

            // 5. Update Invoice Status
            $invoice->amount_paid = $invoice->grand_total;
            $invoice->balance_due = 0.00;
            $invoice->status = InvoiceStatus::Paid;
            $settlementNote = "Settled via Maintenance Reserve Drawdown (Txn: {$transaction->reference}) on " . now()->format('d M Y');
            $invoice->notes = trim(($invoice->notes ? $invoice->notes . "\n" : '') . $settlementNote);
            $invoice->save();

            if ($invoice->contact_id) {
                $invoice->contact->decrement('receivable_balance', $amountToSettle);
            }

            return $invoice;
        });
    }
}
