<?php

namespace Tek2991\Accounting;

use Tek2991\Accounting\Contracts\CompanyAccessor;
use Tek2991\Accounting\Models\Setting;

class AccountingManager
{
    protected ?Setting $cachedSetting = null;
    protected ?int $cachedCompanyId = null;
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
        return $this->getSetting()->default_currency ?? config('accounting.default_currency', 'INR');
    }

    public function getFiscalYearStart(): int
    {
        return $this->getSetting()->fiscal_year_start ?? config('accounting.fiscal_year_start', 1);
    }

    protected function getSetting(): Setting
    {
        $companyId = app(CompanyAccessor::class)->getCurrentCompanyId();

        if ($this->cachedSetting && $this->cachedCompanyId === $companyId) {
            return $this->cachedSetting;
        }

        $this->cachedCompanyId = $companyId;
        
        if ($companyId) {
            $this->cachedSetting = Setting::firstOrCreate(
                ['company_id' => $companyId],
                [
                    'default_currency' => config('accounting.default_currency', 'INR'),
                    'fiscal_year_start' => config('accounting.fiscal_year_start', 1),
                ]
            );
        } else {
            // Fallback for missing company
            $this->cachedSetting = new Setting([
                'default_currency' => config('accounting.default_currency', 'INR'),
                'fiscal_year_start' => config('accounting.fiscal_year_start', 1),
            ]);
        }

        return $this->cachedSetting;
    }
}
