<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Models\OwnerPayout;
use App\Domain\Finance\Services\AccountingProvisioningService;
use App\Domain\Finance\Services\OwnerPayoutService;
use App\Domain\Property\Models\Property;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tek2991\Accounting\Enums\DocumentLineType;
use Tek2991\Accounting\Enums\InvoiceStatus;
use Tek2991\Accounting\Enums\JournalEntryType;
use Tek2991\Accounting\Enums\SystemRole;
use Tek2991\Accounting\Enums\TransactionType;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Models\Invoice;
use Tek2991\Accounting\Models\InvoiceItem;
use Tek2991\Accounting\Services\DocumentNumberService;
use Tek2991\Accounting\Services\TransactionService;

class ProcessOwnerPayoutAction
{
    public function __construct(
        private AccountingProvisioningService $provisioning,
        private TransactionService $txnService,
        private DocumentNumberService $docNumberService
    ) {}

    /**
     * Process an Owner Payout and record the balanced double-entry transaction.
     *
     * Journal:
     * DR: Owner AP [Liability]                (Gross Rent Collected)
     * CR: Commission Revenue [Revenue/P&L]    (Management Fee)
     * CR: Owner Advance / Asset [Asset]       (Advance Offset, if any)
     * CR: Bank Account [Asset]                (Net Cash Disbursed)
     */
    public function execute(
        Property $property,
        string $periodStart,
        string $periodEnd,
        ?User $initiator = null,
        array $options = []
    ): OwnerPayout {
        return DB::transaction(function () use ($property, $periodStart, $periodEnd, $initiator, $options) {
            // 1. Identify Owner Party
            $owner = $property->owner;
            if (!$owner && $property->id) {
                $mouPartyId = \App\Domain\Mou\Models\Mou::where('property_id', $property->id)
                    ->whereNotNull('party_id')
                    ->latest()
                    ->value('party_id');
                if ($mouPartyId) {
                    $owner = \App\Domain\Party\Models\Party::find($mouPartyId);
                }
            }
            if (!$owner) {
                $owner = \App\Domain\Party\Models\Party::whereHas('roles', fn ($q) => $q->where('name', 'owner'))
                    ->orWhereHas('ownerProfile')
                    ->first();
            }

            if (!$owner) {
                throw new \InvalidArgumentException("No owner party linked to property {$property->building_name}");
            }

            // 2. Determine Rent Collected in Period
            $activeAgreement = $property->agreements()->where('status', 'active')->first();
            $rentCollected = isset($options['rent_collected'])
                ? (float) $options['rent_collected']
                : (float) ($activeAgreement ? $activeAgreement->rent_amount : 0);

            if ($rentCollected <= 0) {
                throw new \InvalidArgumentException("Rent collected must be greater than zero to process an owner payout.");
            }

            // 3. Calculate Management Fee (Commission)
            $managementFeePercent = isset($options['management_fee_percent'])
                ? (float) $options['management_fee_percent']
                : 10.00;
            $managementFee = round(($rentCollected * $managementFeePercent) / 100, 2);

            // 4. Calculate Advance Offset (e.g. Geyser purchase / repair advance recovery)
            $advanceOffset = isset($options['advance_offset']) ? (float) $options['advance_offset'] : 0.00;
            $reserveDeduction = isset($options['reserve_deduction']) ? (float) $options['reserve_deduction'] : 0.00;

            // 5. Net Payout Amount
            $netPayout = $rentCollected - $managementFee - $advanceOffset - $reserveDeduction;
            if ($netPayout < 0) {
                $netPayout = 0;
            }

            // 6. Ensure Accounts
            $ownerPayableAccount = $this->provisioning->getOwnerPayableAccount($owner);
            $commissionAccount = $this->provisioning->getCommissionIncomeAccount();
            $ownerAdvanceAccount = $this->provisioning->getOwnerAdvanceAccount($owner);

            $bankAccountId = $options['bank_account_id'] ?? null;
            $bankAccount = $bankAccountId 
                ? Account::find($bankAccountId)
                : (Account::where('type', 'asset')->whereIn('system_role', [SystemRole::Bank, SystemRole::Cash])->first()
                    ?? Account::where('type', 'asset')->first());

            if (!$bankAccount) {
                throw new \RuntimeException("No Bank Account found to disburse owner payout.");
            }

            // 7. Assemble Balanced Double-Entry Journal Entries
            $entries = [];

            // DR: Owner AP (Gross Rent being settled)
            $entries[] = [
                'account_id' => $ownerPayableAccount->id,
                'type' => JournalEntryType::Debit,
                'amount' => $rentCollected,
                'description' => "Owner Payout Settlement for {$property->building_name} ({$periodStart} to {$periodEnd})",
            ];

            // CR: Commission Revenue (Dwelly's true revenue on P&L)
            if ($managementFee > 0) {
                $entries[] = [
                    'account_id' => $commissionAccount->id,
                    'type' => JournalEntryType::Credit,
                    'amount' => $managementFee,
                    'description' => "Management Fee ({$managementFeePercent}%) for {$property->building_name}",
                ];
            }

            // CR: Owner Advance (Recovery of appliances/advances paid on owner's behalf)
            if ($advanceOffset > 0) {
                $entries[] = [
                    'account_id' => $ownerAdvanceAccount->id,
                    'type' => JournalEntryType::Credit,
                    'amount' => $advanceOffset,
                    'description' => "Advance Recovery / Offset against Rent for {$property->building_name}",
                ];
            }

            // CR: Reserve Deduction (if any)
            if ($reserveDeduction > 0) {
                $reserveAccount = Account::where('name', 'like', '%Reserve%')->first() ?? $ownerPayableAccount;
                $entries[] = [
                    'account_id' => $reserveAccount->id,
                    'type' => JournalEntryType::Credit,
                    'amount' => $reserveDeduction,
                    'description' => "Maintenance Reserve Retention for {$property->building_name}",
                ];
            }

            // CR: Bank Account (Net cash disbursed)
            if ($netPayout > 0) {
                $entries[] = [
                    'account_id' => $bankAccount->id,
                    'type' => JournalEntryType::Credit,
                    'amount' => $netPayout,
                    'description' => "Net Owner Payout Disbursed to {$owner->display_name}",
                ];
            }

            $branchId = app(\Tek2991\Accounting\Services\BranchContext::class)->getCurrentId() 
                ?? $property->branch_id 
                ?? \App\Models\Branch::first()?->id;

            $transaction = $this->txnService->createTransaction([
                'branch_id' => $branchId,
                'type' => TransactionType::Journal,
                'description' => "Owner Payout: {$owner->display_name} - {$property->building_name} ({$periodStart} to {$periodEnd})",
                'posted_at' => $options['payout_date'] ?? $periodEnd,
                'reference' => "PAYOUT-{$property->id}-" . Carbon::parse($periodStart)->format('Ym'),
                'reviewed' => true,
                'pending' => false,
            ], $entries);

            // 8. Generate Official Commission Sales Invoice for the Property Owner
            $commissionInvoice = null;
            if ($managementFee > 0) {
                $ownerContact = $owner->accountingContact ?? $this->provisioning->ensureAccountingContact($owner);
                $invoiceNumber = $this->docNumberService->nextInvoiceNumber();
                $formattedPeriod = Carbon::parse($periodStart)->format('d M Y') . ' – ' . Carbon::parse($periodEnd)->format('d M Y');

                $commissionInvoice = Invoice::create([
                    'branch_id' => $branchId,
                    'contact_id' => $ownerContact->id,
                    'invoice_number' => $invoiceNumber,
                    'status' => InvoiceStatus::Paid,
                    'issue_date' => $options['payout_date'] ?? $periodEnd,
                    'due_date' => $options['payout_date'] ?? $periodEnd,
                    'billing_period_start' => $periodStart,
                    'billing_period_end' => $periodEnd,
                    'currency_code' => 'INR',
                    'reference_type' => Property::class,
                    'reference_id' => $property->id,
                    'notes' => "Property Management Commission ({$managementFeePercent}%) for {$property->building_name} ({$formattedPeriod}) - Deducted at source from Owner Payout",
                    'terms' => 'Management fee automatically settled via monthly rental disbursement deduction.',
                    'subtotal' => $managementFee,
                    'grand_total' => $managementFee,
                    'amount_paid' => $managementFee,
                    'balance_due' => 0.00,
                    'transaction_id' => $transaction->id,
                ]);

                InvoiceItem::create([
                    'invoice_id' => $commissionInvoice->id,
                    'line_type' => DocumentLineType::Account,
                    'sort_order' => 1,
                    'description' => "Property Management Fee ({$managementFeePercent}%) - {$property->building_name} [{$formattedPeriod}]",
                    'quantity' => 1,
                    'unit_price' => $managementFee,
                    'line_total' => $managementFee,
                    'gross_amount' => $managementFee,
                    'net_amount' => $managementFee,
                    'income_account_id' => $commissionAccount->id,
                ]);
            }

            // 9. Create Payout Record
            $payout = OwnerPayout::create([
                'branch_id' => $branchId,
                'owner_id' => $owner->id,
                'property_id' => $property->id,
                'transaction_id' => $transaction->id,
                'commission_invoice_id' => $commissionInvoice?->id,
                'rent_collected' => $rentCollected,
                'management_fee' => $managementFee,
                'advance_offset' => $advanceOffset,
                'reserve_deduction' => $reserveDeduction,
                'amount' => $netPayout,
                'status' => 'completed',
                'notes' => $options['notes'] ?? "Owner Payout for {$periodStart} to {$periodEnd}",
                'period_start' => Carbon::parse($periodStart),
                'period_end' => Carbon::parse($periodEnd),
                'processed_at' => now(),
            ]);

            // 10. Settle Linked Maintenance Invoices
            $maintenanceInvoiceIds = $options['maintenance_invoice_ids'] ?? null;
            if ($maintenanceInvoiceIds === null && $advanceOffset > 0) {
                $reqIds = \App\Domain\Maintenance\Models\MaintenanceRequest::where('property_id', $property->id)->pluck('id');
                $maintenanceInvoiceIds = Invoice::where('reference_type', \App\Domain\Maintenance\Models\MaintenanceRequest::class)
                    ->whereIn('reference_id', $reqIds)
                    ->whereIn('status', [
                        InvoiceStatus::Draft,
                        InvoiceStatus::Sent,
                        InvoiceStatus::PartiallyPaid,
                    ])
                    ->pluck('id')
                    ->toArray();
            }

            if (!empty($maintenanceInvoiceIds)) {
                foreach ($maintenanceInvoiceIds as $mInvId) {
                    $mInv = Invoice::find($mInvId);
                    if ($mInv && $mInv->status !== InvoiceStatus::Paid) {
                        $mInv->amount_paid = $mInv->grand_total;
                        $mInv->balance_due = 0.00;
                        $mInv->status = InvoiceStatus::Paid;
                        $settlementNote = "Settled via Owner Payout for {$property->building_name} ({$periodStart} to {$periodEnd}) [Payout Ref: {$transaction->reference}]";
                        $mInv->notes = trim(($mInv->notes ? $mInv->notes . "\n" : '') . $settlementNote);
                        $mInv->save();
                    }
                }
            }

            // 11. Compile Payout Document Snapshot & Generate Immutable PDF
            app(OwnerPayoutService::class)->generateAndStorePayoutPdf($payout);

            return $payout;
        });
    }
}

