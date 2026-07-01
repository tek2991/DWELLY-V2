<?php

namespace Tek2991\Accounting\Services;

use Filament\Facades\Filament;
use Tek2991\Accounting\Enums\TaxRegimeType;
use Tek2991\Accounting\Models\CompanyProfile;

class CompanyContext
{
    public function getCurrentCompanyId(): ?int
    {
        if (Filament::hasTenancy() && Filament::getTenant()) {
            return Filament::getTenant()->getKey();
        }
        
        return null;
    }

    public function getProfile(): ?CompanyProfile
    {
        $companyId = $this->getCurrentCompanyId();
        if (!$companyId) {
            return null;
        }

        return CompanyProfile::firstOrCreate(
            ['company_id' => $companyId],
            ['tax_regime' => TaxRegimeType::Generic]
        );
    }

    public function isIndiaGst(): bool
    {
        $profile = $this->getProfile();
        return $profile && $profile->tax_regime === TaxRegimeType::IndiaGst;
    }
}
