<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Services\AccountingProvisioningService;
use App\Domain\Party\Models\Party;
use Illuminate\Support\Facades\DB;
use Tek2991\Accounting\Enums\JournalEntryType;
use Tek2991\Accounting\Enums\SystemRole;
use Tek2991\Accounting\Enums\TransactionType;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Models\Transaction;
use Tek2991\Accounting\Services\TransactionService;

class RecordOwnerAdvanceAction
{
    public function __construct(
        private AccountingProvisioningService $provisioning,
        private TransactionService $txnService
    ) {}

    /**
     * Record an advance or capital purchase paid by Dwelly on the Owner's behalf (e.g. Geyser purchase).
     *
     * Journal:
     * DR: Owner Advance / Recoverable [Asset]
     * CR: Bank Account [Asset]
     */
    public function execute(
        Party $owner,
        float $amount,
        string $description,
        ?Account $bankAccount = null,
        ?string $date = null,
        ?string $reference = null
    ): Transaction {
        return DB::transaction(function () use ($owner, $amount, $description, $bankAccount, $date, $reference) {
            $ownerAdvanceAccount = $this->provisioning->getOwnerAdvanceAccount($owner);

            if (!$bankAccount) {
                $bankAccount = Account::where('type', 'asset')
                    ->whereIn('system_role', [SystemRole::Bank, SystemRole::Cash])
                    ->first() ?? Account::where('type', 'asset')->first();
            }

            $entries = [
                [
                    'account_id' => $ownerAdvanceAccount->id,
                    'type' => JournalEntryType::Debit,
                    'amount' => $amount,
                    'description' => "Owner Advance: {$description} (for {$owner->display_name})",
                ],
                [
                    'account_id' => $bankAccount->id,
                    'type' => JournalEntryType::Credit,
                    'amount' => $amount,
                    'description' => "Bank payment for Owner Advance: {$description}",
                ],
            ];

            $contact = $this->provisioning->ensureAccountingContact($owner);
            $branchId = app(\Tek2991\Accounting\Services\BranchContext::class)->getCurrentId() 
                ?? $contact->branch_id 
                ?? \App\Models\Branch::first()?->id;

            return $this->txnService->createTransaction([
                'branch_id' => $branchId,
                'type' => TransactionType::Journal,
                'description' => "Advance / Purchase on Owner's Name: {$owner->display_name} - {$description}",
                'posted_at' => $date ?? now()->toDateString(),
                'reference' => $reference ?? "ADV-OWNER-{$owner->id}-" . now()->format('Ymd'),
                'reviewed' => true,
                'pending' => false,
            ], $entries);
        });
    }
}
