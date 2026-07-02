<?php

namespace Tek2991\Accounting\Support\ClosingChecks;

use Tek2991\Accounting\Contracts\ClosingCheck;
use Tek2991\Accounting\Models\FiscalPeriod;
use Tek2991\Accounting\Models\Transaction;
use Tek2991\Accounting\Support\ClosingValidationResult;

class VerifyNoDraftJournals implements ClosingCheck
{
    public function validate(FiscalPeriod $period, ClosingValidationResult $result): void
    {
        $draftCount = Transaction::whereBetween('posted_at', [$period->start_date, $period->end_date])
            ->pending()
            ->count();

        if ($draftCount > 0) {
            $result->addError("There are {$draftCount} draft (pending) transactions in this period. Please post or delete them.");
        }
    }
}
