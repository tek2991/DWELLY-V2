<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Models\OwnerPayout;
use App\Domain\Finance\Services\AccountingProvisioningService;
use App\Domain\Property\Models\Property;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tek2991\Accounting\Enums\JournalEntryType;
use Tek2991\Accounting\Enums\SystemRole;
use Tek2991\Accounting\Enums\TransactionType;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Services\TransactionService;

class ProcessOwnerPayoutAction
{
    public function __construct(
        private AccountingProvisioningService $provisioning,
        private TransactionService $txnService
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

            // 8. Create Payout Record
            $payout = OwnerPayout::create([
                'branch_id' => $branchId,
                'owner_id' => $owner->id,
                'property_id' => $property->id,
                'transaction_id' => $transaction->id,
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

            return $payout;
        });
    }
}

