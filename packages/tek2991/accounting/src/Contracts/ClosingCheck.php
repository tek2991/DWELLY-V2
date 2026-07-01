<?php

namespace Tek2991\Accounting\Contracts;

use Tek2991\Accounting\Models\FiscalPeriod;
use Tek2991\Accounting\Support\ClosingValidationResult;

interface ClosingCheck
{
    /**
     * Validate the given fiscal period and add any errors to the result.
     */
    public function validate(FiscalPeriod $period, ClosingValidationResult $result): void;
}
