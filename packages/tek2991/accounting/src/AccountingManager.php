<?php

namespace Tek2991\Accounting;



class AccountingManager
{
    protected array $closingChecks = [];

    public function registerClosingCheck(string $checkClass): void
    {
        $this->closingChecks[] = $checkClass;
    }

    public function getClosingChecks(): array
    {
        return $this->closingChecks;
    }

    public function getCurrency(): string
    {
        return config('accounting.default_currency', 'INR');
    }

    public function getFiscalYearStart(): int
    {
        return config('accounting.fiscal_year_start', 4); // April 1st
    }
}
