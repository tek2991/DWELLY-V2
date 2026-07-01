<?php

namespace Tek2991\Accounting\Services;

use Illuminate\Support\Facades\DB;

use Tek2991\Accounting\Enums\AccountType;
use Tek2991\Accounting\Enums\JournalEntryType;
use Tek2991\Accounting\Enums\SystemRole;
use Tek2991\Accounting\Models\Account;
use Exception;

class YearEndCloseService
{
    public function __construct(
        private AccountService $accountService,
        private TransactionService $txnService
    ) {}

    public function closeYear(int $companyId, string $yearEndDate, string $description = 'Year End Closing Entry'): \Tek2991\Accounting\Models\Transaction
    {
        return DB::transaction(function () use ($companyId, $yearEndDate, $description) {
            $retainedEarningsAccount = Account::where('company_id', $companyId)
                ->where('system_role', SystemRole::RetainedEarnings)
                ->first();

            if (!$retainedEarningsAccount) {
                throw new Exception("Retained Earnings account not configured for this company.");
            }

            // Get all revenue and expense accounts
            $revenueAccounts = Account::where('company_id', $companyId)
                ->ofType(AccountType::Revenue)
                ->active()
                ->get();
                
            $expenseAccounts = Account::where('company_id', $companyId)
                ->ofType(AccountType::Expense)
                ->active()
                ->get();

            $entries = [];
            $totalProfitLoss = 0;

            // Zero out Revenue accounts (Debit Revenue, Credit Retained Earnings)
            foreach ($revenueAccounts as $account) {
                // Get balance from beginning of time until year end date
                // Note: Revenue accounts have a credit normal balance
                $balance = $this->accountService->getNetMovement($account, '1970-01-01', $yearEndDate);
                $amount = $balance->getAmount();
                
                if ($amount !== 0) {
                    $totalProfitLoss += $amount;
                    
                    // To zero out a credit balance, we debit it.
                    // If the balance is somehow negative (debit balance in revenue), we credit it.
                    $entries[] = [
                        'account_id' => $account->id,
                        'type' => $amount > 0 ? JournalEntryType::Debit : JournalEntryType::Credit,
                        'amount' => abs($amount),
                        'description' => "Close {$account->name} to Retained Earnings",
                    ];
                }
            }

            // Zero out Expense accounts (Credit Expense, Debit Retained Earnings)
            foreach ($expenseAccounts as $account) {
                // Note: Expense accounts have a debit normal balance
                $balance = $this->accountService->getNetMovement($account, '1970-01-01', $yearEndDate);
                $amount = $balance->getAmount(); // Returns positive for debit balance
                
                if ($amount !== 0) {
                    $totalProfitLoss -= $amount; // Expense reduces profit
                    
                    // To zero out a debit balance, we credit it.
                    $entries[] = [
                        'account_id' => $account->id,
                        'type' => $amount > 0 ? JournalEntryType::Credit : JournalEntryType::Debit,
                        'amount' => abs($amount),
                        'description' => "Close {$account->name} to Retained Earnings",
                    ];
                }
            }

            if (empty($entries)) {
                throw new Exception("No balances to close for the given year end date.");
            }

            // Book the net difference to Retained Earnings
            if ($totalProfitLoss !== 0) {
                $entries[] = [
                    'account_id' => $retainedEarningsAccount->id,
                    // If Profit > 0 (Revenue > Expense), we Credit Retained Earnings
                    // If Loss < 0 (Expense > Revenue), we Debit Retained Earnings
                    'type' => $totalProfitLoss > 0 ? JournalEntryType::Credit : JournalEntryType::Debit,
                    'amount' => abs($totalProfitLoss),
                    'description' => "Net Income/Loss for Year Ended {$yearEndDate}",
                ];
            }

            return $this->txnService->createTransaction([
                'company_id' => $companyId,
                'posted_at' => $yearEndDate,
                'description' => $description,
                'type' => \Tek2991\Accounting\Enums\TransactionType::Journal,
                'reference' => 'YEC-' . substr($yearEndDate, 0, 4),
                'reviewed' => true,
                'pending' => false,
            ], $entries);
        });
    }
}
