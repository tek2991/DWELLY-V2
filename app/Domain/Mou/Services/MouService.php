<?php

namespace App\Domain\Mou\Services;

use App\Domain\Mou\Models\Mou;
use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\Opportunity\Enums\MouStatus;
use App\Domain\Mou\Actions\GenerateMouNumberAction;
use Exception;
use Illuminate\Support\Facades\DB;

use App\Domain\Finance\Services\AccountingProvisioningService;
use App\Domain\Party\Events\PartyCreated;

class MouService
{
    public function __construct(
        protected GenerateMouNumberAction $generateMouNumberAction,
        protected AccountingProvisioningService $accountingProvisioning
    ) {}

    public function createDraftFromOpportunity(Opportunity $opportunity): Mou
    {
        if ($opportunity->status->value !== 'ready_for_mou') {
            throw new Exception("Opportunity must be in 'Ready for MOU' state.");
        }

        return DB::transaction(function () use ($opportunity) {
            $mou = Mou::create([
                'number' => $this->generateMouNumberAction->execute(),
                'opportunity_id' => $opportunity->id,
                'status' => MouStatus::DRAFT,
                'legal_terms' => [
                    'rent_amount' => $opportunity->expected_rent,
                    'is_furnished' => $opportunity->estimated_is_furnished,
                ],
                'prepared_by' => auth()->id(),
            ]);

            return $mou;
        });
    }

    public function resolveParty(Mou $mou, array $partyData): void
    {
        DB::transaction(function () use ($mou, $partyData) {
            
            if (($partyData['action_type'] ?? 'create_new') === 'select_existing') {
                $party = \App\Domain\Party\Models\Party::findOrFail($partyData['existing_party_id']);
                
                // Ensure they have an OwnerProfile if they don't already
                if (!$party->ownerProfile()->exists()) {
                    \App\Domain\Party\Models\OwnerProfile::create([
                        'party_id' => $party->id,
                    ]);
                }
            } else {
                $party = \App\Domain\Party\Models\Party::create([
                    'party_type' => $partyData['party_type'] === 'individual' ? 'individual' : 'organization',
                    'display_name' => $partyData['party_type'] === 'individual' ? trim($partyData['name'] ?? '') : $partyData['legal_name'],
                    'phone' => $partyData['phone'] ?? $mou->opportunity->owner_phone,
                    'email' => $partyData['email'] ?? $mou->opportunity->owner_email,
                    'state_id' => $partyData['state_id'] ?? null,
                ]);

                if ($partyData['party_type'] === 'individual') {
                    \App\Domain\Party\Models\PartyIndividual::create([
                        'party_id' => $party->id,
                        'name' => $partyData['name'],
                        'parent_name' => $partyData['parent_name'] ?? null,
                        'date_of_birth' => $partyData['date_of_birth'] ?? null,
                        'gender' => $partyData['gender'] ?? null,
                        'pan_number' => $partyData['pan_number'] ?? null,
                        'aadhaar_number' => $partyData['aadhar_number'] ?? null,
                        'voter_id' => $partyData['voter_id'] ?? null,
                    ]);
                } else {
                    \App\Domain\Party\Models\PartyOrganization::create([
                        'party_id' => $party->id,
                        'legal_name' => $partyData['legal_name'],
                        'pan' => $partyData['pan_number'] ?? null,
                        'gstin' => $partyData['gst_number'] ?? null,
                        'cin' => $partyData['cin'] ?? null,
                        'contact_person_name' => $partyData['contact_person_name'] ?? null,
                        'contact_person_phone' => $partyData['contact_person_phone'] ?? null,
                    ]);
                }

                if (!empty($partyData['address'])) {
                    $baseType = $partyData['party_type'] === 'individual' ? 'residential' : 'registered_office';
                    $types = [$baseType, 'billing', 'shipping'];
                    
                    foreach ($types as $type) {
                        \App\Domain\Party\Models\PartyAddress::create([
                            'party_id' => $party->id,
                            'type' => $type,
                            'address_line_1' => $partyData['address'],
                            'is_primary' => true,
                        ]);
                    }
                }

                // Create Owner Profile
                \App\Domain\Party\Models\OwnerProfile::create([
                    'party_id' => $party->id,
                ]);
            }

            $mou->update(['party_id' => $party->id]);
            
            // Sync resolved party back to Opportunity
            $mou->opportunity->update(['owner_party_id' => $party->id]);

            // If MOU already has bank details, sync them to the party as a Bank Account
            if (!empty($mou->bank_details)) {
                $bankAccount = \App\Domain\Party\Models\PartyBankAccount::firstOrCreate(
                    [
                        'party_id' => $party->id,
                        'account_number' => $mou->bank_details['account_number'] ?? null,
                    ],
                    [
                        'bank_name' => $mou->bank_details['bank_name'] ?? 'Unknown',
                        'bank_address' => $mou->bank_details['bank_address'] ?? null,
                        'account_name' => $mou->bank_details['account_holder_name'] ?? 'Unknown',
                        'ifsc_code' => $mou->bank_details['ifsc_code'] ?? 'Unknown',
                        'is_primary' => true,
                    ]
                );

                $profile = \App\Domain\Party\Models\OwnerProfile::where('party_id', $party->id)->first();
                if ($profile && !$profile->default_bank_account_id) {
                    $profile->update(['default_bank_account_id' => $bankAccount->id]);
                }
            }

            // Reload and provision accounting entity
            $party->loadMissing(['individual', 'organization', 'bankAccounts', 'addresses', 'ownerProfile', 'tenantProfile', 'vendorProfile']);
            $this->accountingProvisioning->ensurePartyAccountingReady($party);

            if ($partyData['action_type'] === 'create_new') {
                PartyCreated::dispatch($party, 'owner');
            }
        });
    }

    public function provisionAccounting(Mou $mou, array $bankDetails): void
    {
        if (!$mou->party_id) {
            throw new Exception("Party must be resolved before provisioning accounting.");
        }

        DB::transaction(function () use ($mou, $bankDetails) {
            $bankAccount = \App\Domain\Party\Models\PartyBankAccount::create([
                'party_id' => $mou->party_id,
                'bank_name' => $bankDetails['bank_name'],
                'bank_address' => $bankDetails['bank_address'] ?? null,
                'account_name' => $bankDetails['account_holder_name'],
                'account_number' => $bankDetails['account_number'],
                'ifsc_code' => $bankDetails['ifsc_code'],
                'is_primary' => true,
            ]);
            
            $ownerProfile = \App\Domain\Party\Models\OwnerProfile::where('party_id', $mou->party_id)->first();
            if ($ownerProfile) {
                $ownerProfile->update(['default_bank_account_id' => $bankAccount->id]);
            }

            $mou->update(['bank_details' => $bankDetails]);
        });
    }
}
