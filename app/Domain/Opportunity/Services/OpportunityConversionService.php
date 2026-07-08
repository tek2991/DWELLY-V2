<?php

namespace App\Domain\Opportunity\Services;

use App\Domain\Opportunity\Models\Opportunity;

class OpportunityConversionService
{
    public function __construct(
        protected OpportunityWorkflowService $workflowService
    ) {}

    public function convertToProperty(Opportunity $opportunity): void
    {
        // 1. Create Property
        // 2. Copy Parties
        // 3. Copy Attachments
        // 4. Launch Onboarding Audit
        
        // Placeholder implementation for Phase 1
        
        // Transition state
        $opportunity->update(['status' => \App\Domain\Opportunity\Enums\OpportunityStatus::CONVERTED]);
        
        app(OpportunityActivityLogger::class)->log(
            $opportunity,
            \App\Domain\Opportunity\Enums\OpportunityActivityType::STATUS_CHANGE,
            'Opportunity Converted to Property',
            [
                'old_status' => \App\Domain\Opportunity\Enums\OpportunityStatus::READY_FOR_MOU->value,
                'new_status' => \App\Domain\Opportunity\Enums\OpportunityStatus::CONVERTED->value,
            ]
        );
    }
}
