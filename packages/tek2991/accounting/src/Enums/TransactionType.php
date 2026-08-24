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
        return match ($this) {
            self::Deposit => 'Deposit',
            self::Withdrawal => 'Withdrawal',
            self::Journal => 'Journal Entry',
            self::InvoicePosting => 'Invoice',
            self::BillPosting => 'Bill',
            self::PaymentIn => 'Payment In',
            self::PaymentOut => 'Payment Out',
            self::CreditNote => 'Credit Note',
            self::DebitNote => 'Debit Note',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Deposit, self::PaymentIn => 'success',
            self::Withdrawal, self::PaymentOut => 'danger',
            self::Journal => 'primary',
            self::InvoicePosting => 'info',
            self::BillPosting => 'warning',
            self::CreditNote, self::DebitNote => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Deposit, self::PaymentIn => 'heroicon-m-arrow-down-left',
            self::Withdrawal, self::PaymentOut => 'heroicon-m-arrow-up-right',
            self::Journal => 'heroicon-m-document-text',
            self::InvoicePosting => 'heroicon-m-document-arrow-up',
            self::BillPosting => 'heroicon-m-document-arrow-down',
            self::CreditNote, self::DebitNote => 'heroicon-m-document-minus',
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
