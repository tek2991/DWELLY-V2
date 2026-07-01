<?php

namespace Tek2991\Accounting\Services;

use Illuminate\Support\Facades\DB;
use Tek2991\Accounting\Enums\AccountType;
use Tek2991\Accounting\Enums\JournalEntryType;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\ValueObjects\Money;

class AccountService
{
    /**
     * Get the total debit balance for an account in a date range.
     */
    public function getDebitBalance(Account $account, string $startDate, string $endDate): Money
    {
        $result = $this->getAccountBalances($startDate, $endDate, [$account->id])->first();

        return new Money($result->total_debit ?? 0, $account->currency_code);
    }

    /**
     * Get the total credit balance for an account in a date range.
     */
    public function getCreditBalance(Account $account, string $startDate, string $endDate): Money
    {
        $result = $this->getAccountBalances($startDate, $endDate, [$account->id])->first();

        return new Money($result->total_credit ?? 0, $account->currency_code);
    }

    /**
     * Get the net movement for an account in a date range.
     * Respects the account's natural balance direction.
     */
    public function getNetMovement(Account $account, string $startDate, string $endDate): Money
    {
        $result = $this->getAccountBalances($startDate, $endDate, [$account->id])->first();

        $netMovement = $account->type->calculateNetMovement(
            $result->total_debit ?? 0,
            $result->total_credit ?? 0
        );

        return new Money($netMovement, $account->currency_code);
    }

    /**
     * Get the starting balance for an account before a given date.
     * Returns null for nominal accounts (Revenue/Expense) unless overridden.
     */
    public function getStartingBalance(Account $account, string $startDate, bool $includeNominal = false): ?Money
    {
        if (! $includeNominal && $account->type->isNominal()) {
            return null;
        }

        $result = $this->getAccountBalances('1970-01-01', $startDate, [$account->id], true)->first();

        $balance = $account->type->calculateNetMovement(
            $result->total_debit ?? 0,
            $result->total_credit ?? 0
        );

        return new Money($balance, $account->currency_code);
    }

    /**
     * Get the ending balance for an account at the end of a date range.
     */
    public function getEndingBalance(Account $account, string $startDate, string $endDate): Money
    {
        $startingBalance = $this->getStartingBalance($account, $startDate, true);
        $netMovement = $this->getNetMovement($account, $startDate, $endDate);

        $startingAmount = $startingBalance?->getAmount() ?? 0;

        return new Money($startingAmount + $netMovement->getAmount(), $account->currency_code);
    }

    /**
     * Get aggregated debit/credit totals for accounts in a date range.
     *
     * @param string $startDate
     * @param string $endDate
     * @param array<int>|null $accountIds Filter to specific account IDs
     * @param bool $excludeEndDate If true, excludes the end date (for starting balance calculation)
     * @return \Illuminate\Support\Collection
     */
    public function getAccountBalances(
        string $startDate,
        string $endDate,
        ?array $accountIds = null,
        bool $excludeEndDate = false
    ) {
        $journalTable = config('accounting.table_prefix', 'acc_') . 'journal_entries';
        $transactionTable = config('accounting.table_prefix', 'acc_') . 'transactions';

        $start = \Illuminate\Support\Carbon::parse($startDate)->startOfDay();
        $end = \Illuminate\Support\Carbon::parse($endDate)->endOfDay();

        $query = DB::table($journalTable)
            ->join($transactionTable, "{$journalTable}.transaction_id", '=', "{$transactionTable}.id")
            ->select(
                "{$journalTable}.account_id",
                DB::raw("SUM(CASE WHEN {$journalTable}.type = 'debit' THEN {$journalTable}.amount ELSE 0 END) as total_debit"),
                DB::raw("SUM(CASE WHEN {$journalTable}.type = 'credit' THEN {$journalTable}.amount ELSE 0 END) as total_credit"),
            )
            ->whereNotNull("{$transactionTable}.posted_at");

        if ($excludeEndDate) {
            $query->where("{$transactionTable}.posted_at", '<', \Illuminate\Support\Carbon::parse($endDate)->startOfDay());
        } else {
            $query->whereBetween("{$transactionTable}.posted_at", [$start, $end]);
        }

        if ($accountIds !== null) {
            $query->whereIn("{$journalTable}.account_id", $accountIds);
        }

        return $query->groupBy("{$journalTable}.account_id")->get();
    }

    /**
     * Get balances for all accounts in a category.
     *
     * @return array<int, array{account: Account, balance: Money}>
     */
    public function getTypeBalances(AccountType $type, string $startDate, string $endDate): array
    {
        $accounts = Account::ofType($type)->active()->get();
        $accountIds = $accounts->pluck('id')->toArray();
        $results = [];

        if (empty($accountIds)) {
            return $results;
        }

        $movements = $this->getAccountBalances($startDate, $endDate, $accountIds)->keyBy('account_id');

        $startingBalances = [];
        if (!$type->isNominal()) {
            $startingBalances = $this->getAccountBalances('1970-01-01', $startDate, $accountIds, true)->keyBy('account_id');
        }

        foreach ($accounts as $account) {
            $startingDebit = 0;
            $startingCredit = 0;
            if (isset($startingBalances[$account->id])) {
                $startingDebit = $startingBalances[$account->id]->total_debit ?? 0;
                $startingCredit = $startingBalances[$account->id]->total_credit ?? 0;
            }
            $startingAmount = $type->isNominal() ? 0 : $type->calculateNetMovement($startingDebit, $startingCredit);

            $periodDebit = 0;
            $periodCredit = 0;
            if (isset($movements[$account->id])) {
                $periodDebit = $movements[$account->id]->total_debit ?? 0;
                $periodCredit = $movements[$account->id]->total_credit ?? 0;
            }
            $netMovementAmount = $type->calculateNetMovement($periodDebit, $periodCredit);

            $endingBalanceAmount = $startingAmount + $netMovementAmount;

            $results[] = [
                'account' => $account,
                'balance' => new Money($endingBalanceAmount, $account->currency_code),
            ];
        }

        return $results;
    }

    /**
     * Get the total balance across all accounts in a category.
     */
    public function getTypeTotal(AccountType $type, string $startDate, string $endDate): Money
    {
        $balances = $this->getTypeBalances($type, $startDate, $endDate);
        $currency = \Tek2991\Accounting\Facades\Accounting::getCurrency();
        $total = Money::zero($currency);

        foreach ($balances as $item) {
            $total = $total->add($item['balance']);
        }

        return $total;
    }

    /**
     * Verify the accounting equation: Assets = Liabilities + Equity.
     * Returns true if balanced.
     */
    public function verifyAccountingEquation(string $startDate, string $endDate): bool
    {
        $assets = $this->getTypeTotal(AccountType::Asset, $startDate, $endDate);
        $liabilities = $this->getTypeTotal(AccountType::Liability, $startDate, $endDate);
        $equity = $this->getTypeTotal(AccountType::Equity, $startDate, $endDate);

        return $assets->getAmount() === ($liabilities->getAmount() + $equity->getAmount());
    }
}
