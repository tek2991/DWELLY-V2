<?php

namespace App\Domain\Mou\Services;

use App\Domain\Mou\Models\Mou;
use App\Domain\Opportunity\Enums\MouStatus;
use App\Domain\Opportunity\Models\Opportunity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class MouService
{
    /**
     * Create a new draft MOU for an opportunity.
     */
    public function prepareDraft(Opportunity $opportunity, array $partyData, array $legalTerms, array $bankDetails): Mou
    {
        // In reality, this would resolve/create the party from $partyData.
        // For now, we will assume the party is resolved and passed as an ID in partyData, or created here.
        $partyId = $partyData['party_id'] ?? null;

        return Mou::create([
            'number' => 'MOU-' . now()->format('Y') . '-' . strtoupper(Str::random(6)),
            'opportunity_id' => $opportunity->id,
            'party_id' => $partyId,
            'status' => MouStatus::DRAFT,
            'legal_terms' => $legalTerms,
            'bank_details' => $bankDetails,
        ]);
    }

    /**
     * Mark an MOU as verified.
     */
    public function verify(Mou $mou): void
    {
        $mou->update([
            'status' => MouStatus::VERIFIED, // Assuming VERIFIED is added to enum, or SIGNED
            'verified_at' => now(),
            'verified_by' => Auth::id(),
        ]);
        
        // Also update the related opportunity if needed
        $mou->opportunity->update(['mou_status' => MouStatus::VERIFIED]); // Example
    }
}
