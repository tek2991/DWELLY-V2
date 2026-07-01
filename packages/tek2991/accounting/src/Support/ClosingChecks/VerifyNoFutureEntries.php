<?php

namespace Tek2991\Accounting\Support\ClosingChecks;

use Tek2991\Accounting\Contracts\ClosingCheck;
use Tek2991\Accounting\Models\FiscalPeriod;
use Tek2991\Accounting\Models\Transaction;
use Tek2991\Accounting\Support\ClosingValidationResult;

class VerifyNoFutureEntries implements ClosingCheck
{
    public function validate(FiscalPeriod $period, ClosingValidationResult $result): void
    {
        $futureCount = Transaction::where('company_id', $period->company_id)
            ->whereBetween('posted_at', [$period->start_date, $period->end_date])
            ->where('posted_at', '>', now())
            ->count();

        if ($futureCount > 0) {
            $result->addError("There are {$futureCount} transactions dated in the future within this period. You cannot close a period containing future-dated entries.");
        }
    }
}
