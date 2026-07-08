<?php

namespace App\Domain\Opportunity\Services;

use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\Opportunity\Enums\OpportunityStatus;
use App\Domain\Opportunity\Enums\OpportunityActivityType;
use Exception;

class OpportunityWorkflowService
{
    public function __construct(
        protected OpportunityActivityLogger $activityLogger
    ) {}

    public function markContacted(Opportunity $opportunity, ?string $notes = null): void
    {
        $this->transition($opportunity, OpportunityStatus::CONTACTED, $notes);
    }

    public function scheduleSiteVisit(Opportunity $opportunity, string $scheduledDate, ?string $notes = null): void
    {
        $oldStatus = $opportunity->status;
        $newStatus = OpportunityStatus::SITE_VISIT_SCHEDULED;
        
        if ($oldStatus === $newStatus) return;
        
        $opportunity->update(['status' => $newStatus]);
        
        $this->activityLogger->log(
            $opportunity,
            OpportunityActivityType::STATUS_CHANGE,
            $notes,
            [
                'old_status' => $oldStatus?->value,
                'new_status' => $newStatus->value,
                'subtitle' => 'Scheduled for ' . \Carbon\Carbon::parse($scheduledDate)->format('m/d/Y')
            ]
        );
    }

    public function completeSiteVisit(Opportunity $opportunity, ?string $notes = null): void
    {
        $this->transition($opportunity, OpportunityStatus::SITE_VISIT_COMPLETED, $notes);
    }

    public function startNegotiation(Opportunity $opportunity, ?string $notes = null): void
    {
        $oldStatus = $opportunity->status;
        $newStatus = OpportunityStatus::NEGOTIATION;
        
        if ($oldStatus === $newStatus) return;
        
        $opportunity->update(['status' => $newStatus]);
        
        $this->activityLogger->log(
            $opportunity,
            OpportunityActivityType::STATUS_CHANGE,
            $notes,
            [
                'old_status' => $oldStatus?->value,
                'new_status' => $newStatus->value,
                'subtitle' => 'Negotiated'
            ]
        );
    }

    public function markReadyForMou(Opportunity $opportunity, ?string $notes = null): void
    {
        $this->transition($opportunity, OpportunityStatus::READY_FOR_MOU, $notes);
    }
    
    public function closeLost(Opportunity $opportunity, ?string $notes = null): void
    {
        $this->transition($opportunity, OpportunityStatus::CLOSED_LOST, $notes);
    }
    
    public function cancel(Opportunity $opportunity, ?string $notes = null): void
    {
        $this->transition($opportunity, OpportunityStatus::CANCELLED, $notes);
    }

    protected function transition(Opportunity $opportunity, OpportunityStatus $newStatus, ?string $notes = null): void
    {
        $oldStatus = $opportunity->status;
        
        // Example of enforcing a rule: cannot go from NEW straight to READY_FOR_MOU in a real scenario
        // Can add state machine rules here. For now, we just enforce they aren't the same.
        if ($oldStatus === $newStatus) {
            return;
        }

        $opportunity->update(['status' => $newStatus]);

        $this->activityLogger->log(
            $opportunity,
            OpportunityActivityType::STATUS_CHANGE,
            $notes,
            [
                'old_status' => $oldStatus?->value,
                'new_status' => $newStatus->value,
            ]
        );
    }
}
