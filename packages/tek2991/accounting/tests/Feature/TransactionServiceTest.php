<?php

namespace Tek2991\Accounting\Tests\Feature;

use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Enums\AccountType;
use Tek2991\Accounting\Enums\JournalEntryType;
use Tek2991\Accounting\Enums\TransactionType;
use Tek2991\Accounting\Services\TransactionService;
use Tek2991\Accounting\Tests\TestCase;
use Exception;

class TransactionServiceTest extends TestCase
{
    public function test_can_create_balanced_transaction()
    {
        $service = app(TransactionService::class);
        $debitAccount = Account::create([
            'type' => AccountType::Asset, 
            'name' => 'Cash',
            'code' => '1000'
        ]);
        $creditAccount = Account::create([
            'type' => AccountType::Revenue, 
            'name' => 'Sales',
            'code' => '4000'
        ]);

        $entries = [
            [
                'account_id' => $debitAccount->id,
                'type' => JournalEntryType::Debit,
                'amount' => 10000,
                'description' => 'Sale'
            ],
            [
                'account_id' => $creditAccount->id,
                'type' => JournalEntryType::Credit,
                'amount' => 10000,
                'description' => 'Sale'
            ]
        ];

        $transaction = $service->createTransaction([
            'description' => 'Test Transaction',
            'type' => TransactionType::JournalEntry,
            'posted_at' => now(),
            'reviewed' => true,
        ], $entries);

        $this->assertNotNull($transaction->id);
        $this->assertTrue($transaction->isBalanced());
        // Since we compare raw amount (cents) with sum(getRawOriginal('amount'))
        // we can test the eloquent relation
        $debitSum = $transaction->journalEntries()->where('type', JournalEntryType::Debit)->sum('amount');
        // Wait, SQLite driver might return strings or integers depending on PDO, but 100 is 100.
        // Actually, our Money model cast uses getRawOriginal. The DB stores 1000000 if we passed 10000.
        // Wait, $entries['amount'] passed to createTransaction is expected to be raw minor units!
        // So 10000 = $100.00
        $this->assertEquals(100, $debitSum); // wait, DB sum('amount') returns float due to cast: 'amount' / 100
    }

    public function test_rejects_unbalanced_transaction()
    {
        $service = app(TransactionService::class);

        $debitAccount = Account::create([
            'type' => AccountType::Asset, 
            'name' => 'Cash',
            'code' => '1001'
        ]);
        $creditAccount = Account::create([
            'type' => AccountType::Revenue, 
            'name' => 'Sales',
            'code' => '4001'
        ]);

        $entries = [
            [
                'account_id' => $debitAccount->id,
                'type' => JournalEntryType::Debit,
                'amount' => 10000,
                'description' => 'Sale'
            ],
            [
                'account_id' => $creditAccount->id,
                'type' => JournalEntryType::Credit,
                'amount' => 9000, // Unbalanced
                'description' => 'Sale'
            ]
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Transaction is not balanced.');

        $service->createTransaction([
            'description' => 'Test Transaction',
            'type' => TransactionType::JournalEntry,
            'posted_at' => now(),
            'reviewed' => true,
        ], $entries);
    }
}
