<?php

namespace App\Domain\Agreement\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Domain\Agreement\Models\TenancyAgreement;

class TenancyActivated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public TenancyAgreement $agreement
    ) {}
}
