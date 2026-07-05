<?php

namespace App\Domain\Opportunity\Services;

use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\Opportunity\Enums\OpportunityActivityType;

class OpportunityActivityLogger
{
    public function log(
        Opportunity $opportunity,
        OpportunityActivityType $type,
        ?string $notes = null,
        ?array $metadata = null,
        ?string $performedByUserId = null
    ): void {
        $opportunity->activities()->create([
            'activity_type' => $type,
            'notes' => $notes,
            'metadata' => $metadata,
            'performed_by' => $performedByUserId ?? auth()->id(),
            'performed_at' => now(),
        ]);
    }
}
