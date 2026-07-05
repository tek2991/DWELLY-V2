<?php

namespace App\Domain\Opportunity\Services;

use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\Opportunity\Enums\OpportunityStatus;
use App\Domain\Opportunity\Enums\OpportunityActivityType;
use App\Domain\Opportunity\Enums\MouStatus;
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

    public function generateMOU(Opportunity $opportunity, array $data): void
    {
        // 1. Create or Resolve Party
        $party = \App\Domain\Party\Models\Party::create([
            'party_type' => $data['party_type'] === 'individual' ? 'individual' : 'organization',
            'display_name' => $data['legal_name'],
            'phone' => $opportunity->owner_phone,
            'email' => $opportunity->owner_email,
        ]);

        if ($data['party_type'] === 'individual') {
            \App\Domain\Party\Models\PartyIndividual::create([
                'party_id' => $party->id,
                'first_name' => $data['legal_name'],
                'pan_number' => $data['pan_number'] ?? null,
                'aadhaar_number' => $data['aadhar_number'] ?? null,
            ]);
        } else {
            \App\Domain\Party\Models\PartyOrganization::create([
                'party_id' => $party->id,
                'legal_name' => $data['legal_name'],
                'pan' => $data['pan_number'] ?? null,
                'gstin' => $data['gst_number'] ?? null,
            ]);
        }

        // 2. Create Owner Profile
        $ownerProfile = \App\Domain\Party\Models\OwnerProfile::create([
            'party_id' => $party->id,
        ]);

        // 3. Create Bank Account
        $bankAccount = \App\Domain\Party\Models\PartyBankAccount::create([
            'party_id' => $party->id,
            'bank_name' => $data['bank_name'],
            'account_name' => $data['account_holder_name'],
            'account_number' => $data['account_number'],
            'ifsc_code' => $data['ifsc_code'],
            'is_primary' => true,
        ]);
        
        $ownerProfile->update(['default_bank_account_id' => $bankAccount->id]);

        // 4. Generate MOU
        $legalTerms = [
            'rent_amount' => $data['rent_amount'],
            'security_deposit' => $data['security_deposit'],
            'lock_in_months' => $data['lock_in_months'],
            'notice_period_months' => $data['notice_period_months'],
            'notes' => $data['notes'] ?? null,
        ];
        
        $bankDetails = [
            'bank_name' => $data['bank_name'],
            'account_name' => $data['account_holder_name'],
            'account_number' => $data['account_number'],
            'ifsc_code' => $data['ifsc_code'],
        ];

        app(\App\Domain\Mou\Services\MouService::class)->prepareDraft($opportunity, ['party_id' => $party->id], $legalTerms, $bankDetails);

        // 5. Update Opportunity Status
        $this->transition($opportunity, OpportunityStatus::MOU_PENDING, 'Party Resolved and MOU Draft Generated.');
        
        $oldMouStatus = $opportunity->mou_status;
        $opportunity->update(['mou_status' => MouStatus::DRAFT]);
        
        $this->activityLogger->log(
            $opportunity,
            OpportunityActivityType::MOU_STATUS_CHANGE,
            'MOU Draft Generated.',
            ['old_mou_status' => $oldMouStatus?->value, 'new_mou_status' => MouStatus::DRAFT->value]
        );
    }

    public function uploadSignedMOU(Opportunity $opportunity, ?string $notes = null): void
    {
        $this->transition($opportunity, OpportunityStatus::MOU_SIGNED, $notes);
        
        $oldMouStatus = $opportunity->mou_status;
        $opportunity->update(['mou_status' => MouStatus::SIGNED]);

        $this->activityLogger->log(
            $opportunity,
            OpportunityActivityType::MOU_STATUS_CHANGE,
            'Signed MOU Uploaded.',
            ['old_mou_status' => $oldMouStatus?->value, 'new_mou_status' => MouStatus::SIGNED->value]
        );
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
        
        // Example of enforcing a rule: cannot go from NEW straight to MOU_SIGNED in a real scenario
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
