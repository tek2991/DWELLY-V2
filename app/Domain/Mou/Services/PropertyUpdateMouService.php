<?php

namespace App\Domain\Mou\Services;

use App\Domain\Mou\Actions\GenerateMouNumberAction;
use App\Domain\Mou\Enums\MouType;
use App\Domain\Mou\Models\Mou;
use App\Domain\Opportunity\Enums\MouStatus;
use App\Domain\Opportunity\Models\FinancialModel;
use App\Domain\Party\Models\OwnerProfile;
use App\Domain\Party\Models\PartyBankAccount;
use App\Domain\Property\Models\Property;
use App\Domain\Property\Models\PropertyFinancialTerm;
use Exception;
use Illuminate\Support\Facades\DB;

class PropertyUpdateMouService
{
    public function __construct(
        protected GenerateMouNumberAction $generateMouNumberAction,
        protected MouWorkflowService $mouWorkflowService
    ) {}

    /**
     * Get the active pending (unverified) update MOU for a specific property and type, if any exists.
     */
    public function getPendingUpdateMou(Property $property, MouType $type): ?Mou
    {
        return $property->mous()
            ->where('type', $type)
            ->whereNotIn('status', [MouStatus::VERIFIED, MouStatus::CONVERTED, MouStatus::CANCELLED])
            ->latest()
            ->first();
    }

    /**
     * Initiate or update a pending update MOU for a given property.
     */
    public function initiateUpdate(Property $property, MouType $type, array $proposedData): Mou
    {
        return DB::transaction(function () use ($property, $type, $proposedData) {
            $latestMou = $property->mous()->latest()->first();
            $partyId = $property->owner_party_id ?? $latestMou?->party_id;

            $pendingMou = $this->getPendingUpdateMou($property, $type);

            if ($pendingMou) {
                return $this->updateProposedDetails($pendingMou, $proposedData);
            }

            $legalTerms = $latestMou?->legal_terms ?? [
                'address' => $property->address_line_1,
            ];

            $bankDetails = $latestMou?->bank_details ?? [];
            $signatoryDetails = $latestMou?->signatory_details ?? [];
            $isSignatoryDifferent = $latestMou?->is_signatory_different ?? false;

            if ($type === MouType::PRICING_UPDATE) {
                if (!empty($proposedData['financial_model_id'])) {
                    $fm = FinancialModel::find($proposedData['financial_model_id']);
                    if ($fm) {
                        $proposedData['financial_model_name'] = $fm->name;
                        $proposedData['financial_model_description'] = $fm->description;
                        $proposedData['financial_model_fee_collection'] = $fm->fee_collection;
                    }
                }
                $legalTerms = array_merge($legalTerms, $proposedData);
            } elseif ($type === MouType::BANK_DETAILS_UPDATE) {
                $bankDetails = array_merge($bankDetails, $proposedData);
            } elseif ($type === MouType::SIGN_AUTHORITY_UPDATE) {
                $isSignatoryDifferent = $proposedData['is_signatory_different'] ?? false;
                $signatoryDetails = array_merge($signatoryDetails, $proposedData['signatory_details'] ?? []);
            }

            $mou = Mou::create([
                'number' => $this->generateMouNumberAction->execute(),
                'property_id' => $property->id,
                'opportunity_id' => $latestMou?->opportunity_id,
                'party_id' => $partyId,
                'type' => $type,
                'status' => MouStatus::DRAFT,
                'owner_details' => $latestMou?->owner_details ?? [],
                'legal_terms' => $legalTerms,
                'bank_details' => $bankDetails,
                'is_signatory_different' => $isSignatoryDifferent,
                'signatory_details' => $signatoryDetails,
                'start_date' => $proposedData['start_date'] ?? now()->format('Y-m-d'),
                'prepared_by' => auth()->id(),
            ]);

            return $mou;
        });
    }

    /**
     * Update the proposed details on an existing draft Update MOU.
     */
    public function updateProposedDetails(Mou $mou, array $proposedData): Mou
    {
        return DB::transaction(function () use ($mou, $proposedData) {
            if (in_array($mou->status, [MouStatus::VERIFIED, MouStatus::CONVERTED])) {
                throw new Exception("Cannot update an MOU that is already verified or converted.");
            }

            $type = $mou->type;

            if ($type === MouType::PRICING_UPDATE) {
                if (!empty($proposedData['financial_model_id'])) {
                    $fm = FinancialModel::find($proposedData['financial_model_id']);
                    if ($fm) {
                        $proposedData['financial_model_name'] = $fm->name;
                        $proposedData['financial_model_description'] = $fm->description;
                        $proposedData['financial_model_fee_collection'] = $fm->fee_collection;
                    }
                }
                $legalTerms = array_merge($mou->legal_terms ?? [], $proposedData);
                $mou->legal_terms = $legalTerms;
                if (!empty($proposedData['start_date'])) {
                    $mou->start_date = $proposedData['start_date'];
                }
            } elseif ($type === MouType::BANK_DETAILS_UPDATE) {
                $bankDetails = array_merge($mou->bank_details ?? [], $proposedData);
                $mou->bank_details = $bankDetails;
            } elseif ($type === MouType::SIGN_AUTHORITY_UPDATE) {
                $mou->is_signatory_different = $proposedData['is_signatory_different'] ?? $mou->is_signatory_different;
                $signatoryDetails = array_merge($mou->signatory_details ?? [], $proposedData['signatory_details'] ?? []);
                $mou->signatory_details = $signatoryDetails;
            }

            $mou->save();
            return $mou;
        });
    }

    /**
     * Commit the verified Update MOU changes to active Domain models.
     */
    public function commitUpdateMou(Mou $mou): void
    {
        DB::transaction(function () use ($mou) {
            $type = $mou->type;

            if ($type === MouType::PRICING_UPDATE) {
                $pricingModelName = $mou->legal_terms['financial_model_name']
                    ?? (isset($mou->legal_terms['financial_model_id']) ? FinancialModel::find($mou->legal_terms['financial_model_id'])?->name : null)
                    ?? $mou->legal_terms['pricing_model']
                    ?? 'Standard';

                PropertyFinancialTerm::create([
                    'property_id' => $mou->property_id,
                    'mou_id' => $mou->id,
                    'pricing_model' => $pricingModelName,
                    'fee_percentage' => $mou->legal_terms['fee_percentage'] ?? null,
                    'effective_from' => $mou->start_date ?? now(),
                ]);
            } elseif ($type === MouType::BANK_DETAILS_UPDATE) {
                if ($mou->party_id && !empty($mou->bank_details) && !empty($mou->bank_details['account_number'])) {
                    // Mark older accounts as non-primary
                    PartyBankAccount::where('party_id', $mou->party_id)->update(['is_primary' => false]);

                    $bankAccount = PartyBankAccount::updateOrCreate(
                        [
                            'party_id' => $mou->party_id,
                            'account_number' => $mou->bank_details['account_number'],
                        ],
                        [
                            'bank_name' => $mou->bank_details['bank_name'] ?? 'Unknown',
                            'bank_address' => $mou->bank_details['bank_address'] ?? null,
                            'beneficiary_name' => $mou->bank_details['beneficiary_name'] ?? 'Unknown',
                            'ifsc_code' => $mou->bank_details['ifsc_code'] ?? 'Unknown',
                            'is_primary' => true,
                        ]
                    );

                    $profile = OwnerProfile::where('party_id', $mou->party_id)->first();
                    if ($profile) {
                        $profile->update(['default_bank_account_id' => $bankAccount->id]);
                    }
                }
            } elseif ($type === MouType::SIGN_AUTHORITY_UPDATE) {
                // Signatory details are preserved directly on the verified MOU and linked Party
                if ($mou->party && $mou->is_signatory_different && !empty($mou->signatory_details)) {
                    // Logged on MOU
                }
            }
        });
    }
}
