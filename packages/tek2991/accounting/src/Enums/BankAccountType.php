<?php

namespace Tek2991\Accounting\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum BankAccountType: string implements HasLabel, HasColor, HasIcon
{
    case Depository = 'depository';
    case Credit = 'credit';
    case Investment = 'investment';
    case Loan = 'loan';
    case Other = 'other';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Depository => 'Depository',
            self::Credit     => 'Credit Card',
            self::Investment => 'Investment',
            self::Loan       => 'Loan',
            self::Other      => 'Other',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Depository => 'success',
            self::Credit     => 'danger',
            self::Investment => 'info',
            self::Loan       => 'warning',
            self::Other      => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Depository => 'heroicon-o-building-library',
            self::Credit     => 'heroicon-o-credit-card',
            self::Investment => 'heroicon-o-chart-bar-square',
            self::Loan       => 'heroicon-o-banknotes',
            self::Other      => 'heroicon-o-ellipsis-horizontal-circle',
        };
    }

    /**
     * The AccountType this bank account type belongs to.
     * Depository and Investment are Asset accounts.
     * Credit and Loan are Liability accounts.
     */
    public function getAccountType(): AccountType
    {
        return match ($this) {
            self::Depository, self::Investment => AccountType::Asset,
            self::Credit, self::Loan           => AccountType::Liability,
            self::Other                        => AccountType::Asset,
        };
    }

    /**
     * Get the ReportingClass values valid for this BankAccountType.
     *
     * @return ReportingClass[]
     */
    public function getValidReportingClasses(): array
    {
        return match ($this) {
            self::Depository => [ReportingClass::CurrentAsset],
            self::Investment => [ReportingClass::FixedAsset, ReportingClass::OtherAsset],
            self::Credit     => [ReportingClass::CurrentLiability],
            self::Loan       => [ReportingClass::LongTermLiability],
            self::Other      => [ReportingClass::CurrentAsset, ReportingClass::OtherAsset],
        };
    }
}
