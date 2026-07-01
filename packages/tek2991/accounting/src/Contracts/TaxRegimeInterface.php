<?php

namespace Tek2991\Accounting\Contracts;

use Illuminate\Support\Collection;
use Tek2991\Accounting\ValueObjects\TaxCalculationContext;

interface TaxRegimeInterface
{
    public function calculate(TaxCalculationContext $context): Collection;
}
