<?php

namespace Tek2991\Accounting\Services;

use Illuminate\Support\Collection;
use Tek2991\Accounting\ValueObjects\TaxCalculationContext;

class TaxService
{
    public function __construct(
        private TaxRegimeResolver $resolver
    ) {}

    public function calculateTax(TaxCalculationContext $context): Collection
    {
        $regime = $this->resolver->resolve($context->companyProfile);
        return $regime->calculate($context);
    }
}
