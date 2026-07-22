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

    public function getOwnerDetails(?\App\Domain\Party\Models\Party $party = null, ?Opportunity $opportunity = null): array
    {
        if ($party) {
            $party->loadMissing(['individual', 'organization', 'addresses']);
            $firstAddress = $party->addresses->first();
            return [
                'party_id' => $party->id,
                'party_type' => $party->party_type,
                'name' => $party->display_name,
                'parent_name' => $party->individual?->parent_name ?? null,
                'phone' => $party->phone,
                'email' => $party->email,
                'pan_number' => $party->individual?->pan_number ?? $party->organization?->pan ?? null,
                'aadhar_number' => $party->individual?->aadhaar_number ?? null,
                'gstin' => $party->organization?->gstin ?? null,
                'cin' => $party->organization?->cin ?? null,
                'contact_person_name' => $party->organization?->contact_person_name ?? null,
                'contact_person_phone' => $party->organization?->contact_person_phone ?? null,
                'address' => $firstAddress?->address_line_1 ?? null,
                'state' => $firstAddress?->state ?? null,
            ];
        }

        if ($opportunity) {
            return [
                'name' => $opportunity->owner_name,
                'phone' => $opportunity->owner_phone,
                'email' => $opportunity->owner_email,
                'address' => $opportunity->address,
            ];
        }

        return [];
    }

    public function getSignatoryDetailsForOwner(?\App\Domain\Party\Models\Party $party = null, ?Opportunity $opportunity = null): array
    {
        if ($party) {
            $party->loadMissing(['individual', 'organization']);
            return [
                'name' => $party->display_name,
                'relation' => 'Self',
                'phone' => $party->phone,
                'email' => $party->email,
                'aadhar_number' => $party->individual?->aadhaar_number ?? null,
                'pan_number' => $party->individual?->pan_number ?? $party->organization?->pan ?? null,
            ];
        }

        if ($opportunity) {
            return [
                'name' => $opportunity->owner_name,
                'relation' => 'Self',
                'phone' => $opportunity->owner_phone,
                'email' => $opportunity->owner_email,
                'aadhar_number' => null,
                'pan_number' => null,
            ];
        }

        return [
            'name' => null,
            'relation' => 'Self',
            'phone' => null,
            'email' => null,
            'aadhar_number' => null,
            'pan_number' => null,
        ];
    }

    public function createDraftFromOpportunity(Opportunity $opportunity): Mou
    {
        if ($opportunity->status->value !== 'ready_for_mou') {
            throw new Exception("Opportunity must be in 'Ready for MOU' state.");
        }

        return DB::transaction(function () use ($opportunity) {
            $financialModel = $opportunity->expected_financial_model_id
                ? \App\Domain\Opportunity\Models\FinancialModel::find($opportunity->expected_financial_model_id)
                : null;

            $legalTerms = [
                'address' => $opportunity->address,
                'rent_amount' => $opportunity->expected_rent,
                'is_furnished' => $opportunity->estimated_is_furnished,
                'fee_percentage' => 12,
            ];

            if ($financialModel) {
                $legalTerms['financial_model_id'] = $financialModel->id;
                $legalTerms['financial_model_name'] = $financialModel->name;
                $legalTerms['financial_model_description'] = $financialModel->description;
                $legalTerms['financial_model_fee_collection'] = $financialModel->fee_collection;
            }

            $mou = Mou::create([
                'number' => $this->generateMouNumberAction->execute(),
                'opportunity_id' => $opportunity->id,
                'status' => MouStatus::DRAFT,
                'owner_details' => $this->getOwnerDetails(null, $opportunity),
                'is_signatory_different' => false,
                'signatory_details' => $this->getSignatoryDetailsForOwner(null, $opportunity),
                'legal_terms' => $legalTerms,
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

                $stateName = isset($partyData['state_id']) ? \Tek2991\Accounting\Models\State::find($partyData['state_id'])?->name : null;

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
                            'state' => $stateName,
                            'is_primary' => true,
                        ]);
                    }
                }

                // Create Owner Profile
                \App\Domain\Party\Models\OwnerProfile::create([
                    'party_id' => $party->id,
                ]);
            }

            $ownerDetails = $this->getOwnerDetails($party, $mou->opportunity);
            $mouUpdateData = [
                'party_id' => $party->id,
                'owner_details' => $ownerDetails,
            ];
            if (!$mou->is_signatory_different) {
                $mouUpdateData['signatory_details'] = $this->getSignatoryDetailsForOwner($party, $mou->opportunity);
            }
            $mou->update($mouUpdateData);
            
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
                        'beneficiary_name' => $mou->bank_details['beneficiary_name'] ?? 'Unknown',
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
                'beneficiary_name' => $bankDetails['beneficiary_name'],
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
