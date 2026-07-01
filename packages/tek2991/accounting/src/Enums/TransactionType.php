<?php

namespace Tek2991\Accounting\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TransactionType: string implements HasLabel, HasColor, HasIcon
{
    case Deposit = 'deposit';
    case Withdrawal = 'withdrawal';
    case Journal = 'journal';
    case InvoicePosting = 'invoice_posting';
    case BillPosting = 'bill_posting';
    case PaymentIn = 'payment_in';
    case PaymentOut = 'payment_out';
    case CreditNote = 'credit_note';
    case DebitNote = 'debit_note';

    public function getLabel(): ?string
    {
        return $this->name;
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Deposit, self::PaymentIn => 'success',
            self::Withdrawal, self::PaymentOut => 'danger',
            self::Journal, self::InvoicePosting, self::BillPosting, self::CreditNote, self::DebitNote => 'info',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Deposit, self::PaymentIn => 'heroicon-o-arrow-down-tray',
            self::Withdrawal, self::PaymentOut => 'heroicon-o-arrow-up-tray',
            self::Journal => 'heroicon-o-document-text',
            self::InvoicePosting, self::BillPosting => 'heroicon-o-document-duplicate',
            self::CreditNote, self::DebitNote => 'heroicon-o-document-minus',
        };
    }

    /**
     * Whether this transaction type requires a bank account.
     */
    public function requiresBankAccount(): bool
    {
        return in_array($this, [self::Deposit, self::Withdrawal, self::PaymentIn, self::PaymentOut]);
    }
}
