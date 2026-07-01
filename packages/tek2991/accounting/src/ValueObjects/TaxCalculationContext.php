<?php

namespace Tek2991\Accounting\ValueObjects;

use Illuminate\Database\Eloquent\Model;
use Tek2991\Accounting\Models\CompanyProfile;
use Tek2991\Accounting\Models\Contact;
use Tek2991\Accounting\Models\Tax;

class TaxCalculationContext
{
    public function __construct(
        public float|int $amount,
        public Model $document, // Invoice|Bill
        public Tax $tax,
        public ?string $modeOverride,
        public CompanyProfile $companyProfile,
        public ?Contact $contact
    ) {}
}
