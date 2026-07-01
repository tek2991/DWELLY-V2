<?php

namespace Tek2991\Accounting\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Tek2991\Accounting\Models\FiscalPeriod;

class FiscalPeriodClosed
{
    use Dispatchable, SerializesModels;

    public function __construct(public FiscalPeriod $fiscalPeriod)
    {
    }
}
