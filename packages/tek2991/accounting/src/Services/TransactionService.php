<?php

namespace Tek2991\Accounting\Services;

use Illuminate\Support\Facades\DB;
use Tek2991\Accounting\Enums\JournalEntryType;
use Tek2991\Accounting\Enums\TransactionType;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Models\JournalEntry;
use Tek2991\Accounting\Models\Transaction;
use Tek2991\Accounting\Services\PeriodLockService;

class TransactionService
{
    /**
     * Create a transaction with balanced journal entries.
     *
     * @param array{
     *     company_id: int,
     *     type: TransactionType,
     *     description: string,
     *     notes?: string,
     *     reference?: string,
     *     amount: int,
     *     posted_at?: string,
     *     pending?: bool,
     *     reviewed?: bool,
     *     account_id?: int,
     * } $transactionData
     * @param array<array{
     *     account_id: int,
     *     type: JournalEntryType,
     *     amount: int,
     *     description?: string,
     * }> $entries Journal entry lines — must balance (total debits == total credits)
     *
     * @throws \InvalidArgumentException If entries don't balance
     * @throws \Throwable
     */
    public function createTransaction(array $transactionData, array $entries): Transaction
    {
        if (isset($transactionData['posted_at']) && $transactionData['posted_at']) {
            app(PeriodLockService::class)->assertNotClosed(
                $transactionData['company_id'],
                $transactionData['posted_at']
            );
        }

        $type = $transactionData['type'] ?? null;
        if ($type instanceof \Tek2991\Accounting\Enums\TransactionType) {
            $type = $type->value;
        }

        if ($type === \Tek2991\Accounting\Enums\TransactionType::Journal->value) {
            foreach ($entries as $entry) {
                $account = \Tek2991\Accounting\Models\Account::find($entry['account_id']);
                if ($account && $account->is_control_account) {
                    throw new \Tek2991\Accounting\Exceptions\InvalidOperationException("Cannot post manual journal entry to control account: {$account->name}");
                }
            }
        }

        $amount = $this->validateBalance($entries);
        
        if (!isset($transactionData['amount'])) {
            $transactionData['amount'] = $amount;
        }

        return DB::transaction(function () use ($transactionData, $entries) {
            $transaction = Transaction::create($transactionData);

            foreach ($entries as $entry) {
                $transaction->journalEntries()->create([
                    'company_id' => $transaction->company_id,
                    'account_id' => $entry['account_id'],
                    'type' => $entry['type'],
                    'amount' => $entry['amount'],
                    'description' => $entry['description'] ?? $transaction->description,
                ]);
            }

            return $transaction->load('journalEntries');
        });
    }

    /**
     * Create a simple two-account transaction (most common case).
     *
     * Debits the first account, credits the second.
     */
    public function createSimpleTransaction(
        int $companyId,
        int $debitAccountId,
        int $creditAccountId,
        int $amount,
        string $description,
        TransactionType $type = TransactionType::Journal,
        ?string $postedAt = null,
        ?string $reference = null,
    ): Transaction {
        return $this->createTransaction(
            [
                'company_id' => $companyId,
                'type' => $type,
                'description' => $description,
                'amount' => $amount,
                'posted_at' => $postedAt ?? now()->toDateString(),
                'reference' => $reference,
                'reviewed' => false,
                'pending' => false,
            ],
            [
                [
                    'account_id' => $debitAccountId,
                    'type' => JournalEntryType::Debit,
                    'amount' => $amount,
                ],
                [
                    'account_id' => $creditAccountId,
                    'type' => JournalEntryType::Credit,
                    'amount' => $amount,
                ],
            ]
        );
    }

    /**
     * Reverse a posted transaction by creating an equal-and-opposite transaction.
     */
    public function reverseTransaction(Transaction $transaction, ?string $description = null): Transaction
    {
        $originalEntries = $transaction->journalEntries;

        $reversedEntries = $originalEntries->map(function (JournalEntry $entry) {
            return [
                'account_id' => $entry->account_id,
                'type' => $entry->type->opposite(),
                'amount' => $entry->amount,
                'description' => $entry->description,
            ];
        })->toArray();

        $reversal = $this->createTransaction(
            [
                'company_id' => $transaction->company_id,
                'type' => $transaction->type,
                'description' => $description ?? "Reversal of: {$transaction->description}",
                'amount' => $transaction->amount,
                'posted_at' => now()->toDateString(),
                'reference' => "REV-{$transaction->id}",
                'reviewed' => false,
                'pending' => false,
                'allow_reversal' => false,
            ],
            $reversedEntries
        );

        $transaction->update(['allow_reversal' => false]);

        return $reversal;
    }

    /**
     * Validate that journal entries balance (total debits == total credits).
     *
     * @param array<array{type: JournalEntryType, amount: int}> $entries
     * @throws \InvalidArgumentException
     */
    protected function validateBalance(array $entries): float
    {
        if (empty($entries)) {
            throw new \InvalidArgumentException('Transaction must have at least one journal entry.');
        }

        $totalDebit = 0;
        $totalCredit = 0;

        $totalDebit = round(collect($entries)->filter(function ($entry) {
            $type = $entry['type'] instanceof JournalEntryType ? $entry['type']->value : $entry['type'];
            return $type === JournalEntryType::Debit->value;
        })->sum('amount'), 2);
        
        $totalCredit = round(collect($entries)->filter(function ($entry) {
            $type = $entry['type'] instanceof JournalEntryType ? $entry['type']->value : $entry['type'];
            return $type === JournalEntryType::Credit->value;
        })->sum('amount'), 2);

        if ($totalDebit !== $totalCredit) {
            throw new \InvalidArgumentException(
                "Journal entries must balance. Debit total: {$totalDebit}, Credit total: {$totalCredit}"
            );
        }

        if ($totalDebit === 0) {
            throw new \InvalidArgumentException('Transaction amount cannot be zero.');
        }

        return $totalDebit;
    }
}
