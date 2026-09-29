<?php

namespace App\Domain\Opportunity\Actions;

use App\Domain\Shared\Services\NumberingService;

class GenerateOpportunityNumberAction
{
    public function execute(): string
    {
        return NumberingService::generate('opportunity');
    }
}
