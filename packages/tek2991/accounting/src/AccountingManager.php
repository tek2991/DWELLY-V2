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

    public function getDefaultBankAccountId(): ?int
    {
        return \Tek2991\Accounting\Models\BankAccount::where('enabled', true)->value('account_id')
            ?? \Tek2991\Accounting\Models\Account::where('type', \Tek2991\Accounting\Enums\AccountType::Asset)->where('default', true)->value('id')
            ?? \Tek2991\Accounting\Models\Account::where('system_role', \Tek2991\Accounting\Enums\SystemRole::Bank)->value('id')
            ?? \Tek2991\Accounting\Models\Account::where('code', '1130')->value('id');
    }
}
