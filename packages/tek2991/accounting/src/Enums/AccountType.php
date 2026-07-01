<?php

namespace Tek2991\Accounting\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum AccountType: string implements HasLabel, HasColor, HasIcon
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Revenue = 'revenue';
    case Expense = 'expense';

    public function getLabel(): ?string
    {
        return $this->name;
    }

    public function getPluralLabel(): string
    {
        return match ($this) {
            self::Asset => 'Assets',
            self::Liability => 'Liabilities',
            self::Equity => 'Equity',
            self::Revenue => 'Revenue',
            self::Expense => 'Expenses',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Asset => 'info',
            self::Liability => 'danger',
            self::Equity => 'success',
            self::Revenue => 'primary',
            self::Expense => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Asset => 'heroicon-o-building-library',
            self::Liability => 'heroicon-o-scale',
            self::Equity => 'heroicon-o-shield-check',
            self::Revenue => 'heroicon-o-arrow-trending-up',
            self::Expense => 'heroicon-o-arrow-trending-down',
        };
    }

    /**
     * Real accounts appear on the Balance Sheet (Asset, Liability, Equity).
     * Their balances carry forward across periods.
     */
    public function isReal(): bool
    {
        return in_array($this, [self::Asset, self::Liability, self::Equity]);
    }

    /**
     * Nominal accounts appear on the Income Statement (Revenue, Expense).
     * Their balances reset each period.
     */
    public function isNominal(): bool
    {
        return in_array($this, [self::Revenue, self::Expense]);
    }

    /**
     * Determine the natural balance type for this category.
     * Assets and Expenses are debit-normal.
     * Liabilities, Equity, and Revenue are credit-normal.
     */
    public function getDefaultBalanceType(): JournalEntryType
    {
        return match ($this) {
            self::Asset, self::Expense => JournalEntryType::Debit,
            self::Liability, self::Equity, self::Revenue => JournalEntryType::Credit,
        };
    }

    /**
     * Calculate net movement based on this category's natural balance.
     * For debit-normal accounts: debit - credit
     * For credit-normal accounts: credit - debit
     */
    public function calculateNetMovement(int $totalDebit, int $totalCredit): int
    {
        return match ($this->getDefaultBalanceType()) {
            JournalEntryType::Debit => $totalDebit - $totalCredit,
            JournalEntryType::Credit => $totalCredit - $totalDebit,
        };
    }

    /**
     * Get the starting account code for this category.
     */
    public function getCodeRangeStart(): int
    {
        $ranges = config('accounting.account_code.ranges', []);

        return $ranges[$this->value][0] ?? match ($this) {
            self::Asset => 1000,
            self::Liability => 2000,
            self::Equity => 3000,
            self::Revenue => 4000,
            self::Expense => 5000,
        };
    }

    /**
     * Get the ending account code for this category.
     */
    public function getCodeRangeEnd(): int
    {
        $ranges = config('accounting.account_code.ranges', []);

        return $ranges[$this->value][1] ?? match ($this) {
            self::Asset => 1999,
            self::Liability => 2999,
            self::Equity => 3999,
            self::Revenue => 4999,
            self::Expense => 5999,
        };
    }

    public static function fromPluralLabel(string $label): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->getPluralLabel() === $label) {
                return $case;
            }
        }

        return null;
    }
}
