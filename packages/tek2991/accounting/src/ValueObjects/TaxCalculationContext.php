<?php

namespace Tek2991\Accounting\ValueObjects;

use App\Models\Branch;

use Illuminate\Database\Eloquent\Model;
use App\Models\Branch;
use Tek2991\Accounting\Models\Contact;
use Tek2991\Accounting\Models\Tax;

class TaxCalculationContext
{
    public function __construct(
        public float|int $amount,
        public Model $document, // Invoice|Bill
        public Tax $tax,
        public ?string $modeOverride,
        public Branch $branch,
        public ?Contact $contact
    ) {}
}
