<?php

namespace Tek2991\Accounting\Support\ClosingChecks;

use Tek2991\Accounting\Contracts\ClosingCheck;
use Tek2991\Accounting\Models\FiscalPeriod;
use Tek2991\Accounting\Models\JournalEntry;
use Tek2991\Accounting\Support\ClosingValidationResult;
use Tek2991\Accounting\Enums\JournalEntryType;

class VerifyTrialBalance implements ClosingCheck
{
    public function validate(FiscalPeriod $period, ClosingValidationResult $result): void
    {
        $totalDebit = JournalEntry::whereHas('transaction', function($q) use($period) {
            $q->whereNotNull('posted_at')
              ->whereBetween('posted_at', [$period->start_date, $period->end_date]);
        })->where('type', JournalEntryType::Debit)->sum('amount');

        $totalCredit = JournalEntry::whereHas('transaction', function($q) use($period) {
            $q->whereNotNull('posted_at')
              ->whereBetween('posted_at', [$period->start_date, $period->end_date]);
        })->where('type', JournalEntryType::Credit)->sum('amount');

        if ($totalDebit !== $totalCredit) {
            $result->addError("Trial Balance is not balanced. Total Debits do not equal Total Credits.");
        }
    }
}
