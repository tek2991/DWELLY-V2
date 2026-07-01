<?php

namespace Tek2991\Accounting\Services;

use Tek2991\Accounting\Contracts\TaxRegimeInterface;
use Tek2991\Accounting\Enums\TaxRegimeType;
use Tek2991\Accounting\Models\CompanyProfile;
use Tek2991\Accounting\Services\TaxRegimes\GenericTaxRegime;
use Tek2991\Accounting\Services\TaxRegimes\IndiaGstTaxRegime;

class TaxRegimeResolver
{
    public function resolve(?CompanyProfile $profile): TaxRegimeInterface
    {
        if (!$profile) {
            return app(GenericTaxRegime::class);
        }

        return match ($profile->tax_regime) {
            TaxRegimeType::IndiaGst => app(IndiaGstTaxRegime::class),
            default => app(GenericTaxRegime::class),
        };
    }
}
