<?php

namespace Tek2991\Accounting\Enums;

use Filament\Support\Contracts\HasLabel;

enum ReportingClass: string implements HasLabel
{
    case CurrentAsset = 'current_asset';
    case FixedAsset = 'fixed_asset';
    case GSTAsset = 'gst_asset';
    case OtherAsset = 'other_asset';

    case CurrentLiability = 'current_liability';
    case GSTLiability = 'gst_liability';
    case LongTermLiability = 'long_term_liability';
    case OtherLiability = 'other_liability';

    case Equity = 'equity';

    case Revenue = 'revenue';

    case COGS = 'cogs';
    case OperatingExpense = 'operating_expense';
    case FinanceCost = 'finance_cost';
    case OtherExpense = 'other_expense';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::CurrentAsset => 'Current Asset',
            self::FixedAsset => 'Fixed Asset',
            self::GSTAsset => 'GST Asset',
            self::OtherAsset => 'Other Asset',
            self::CurrentLiability => 'Current Liability',
            self::GSTLiability => 'GST Liability',
            self::LongTermLiability => 'Long-Term Liability',
            self::OtherLiability => 'Other Liability',
            self::Equity => 'Equity',
            self::Revenue => 'Revenue',
            self::COGS => 'Cost of Goods Sold',
            self::OperatingExpense => 'Operating Expense',
            self::FinanceCost => 'Finance Cost',
            self::OtherExpense => 'Other Expense',
        };
    }

    public function getAccountType(): AccountType
    {
        return match ($this) {
            self::CurrentAsset, self::FixedAsset, self::GSTAsset, self::OtherAsset => AccountType::Asset,
            self::CurrentLiability, self::GSTLiability, self::LongTermLiability, self::OtherLiability => AccountType::Liability,
            self::Equity => AccountType::Equity,
            self::Revenue => AccountType::Revenue,
            self::COGS, self::OperatingExpense, self::FinanceCost, self::OtherExpense => AccountType::Expense,
        };
    }
}
