<?php

namespace App\Domain\Opportunity\Actions;

use App\Domain\Opportunity\Models\Opportunity;

class GenerateOpportunityNumberAction
{
    public function execute(): string
    {
        $year = now()->format('Y');
        
        $lastOpportunity = Opportunity::withTrashed()
            ->where('number', 'like', "OPP-{$year}-%")
            ->orderBy('number', 'desc')
            ->first();

        if (! $lastOpportunity) {
            return "OPP-{$year}-000001";
        }

        $lastNumberStr = str_replace("OPP-{$year}-", '', $lastOpportunity->number);
        $nextNumber = (int) $lastNumberStr + 1;

        return sprintf("OPP-%s-%06d", $year, $nextNumber);
    }
}
