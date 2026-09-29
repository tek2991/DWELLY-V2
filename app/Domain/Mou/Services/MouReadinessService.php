<?php

namespace App\Domain\Mou\Services;

use App\Domain\Mou\Models\Mou;

class MouReadinessService
{
    public function isPartyResolved(Mou $mou): bool
    {
        return $mou->party_id !== null;
    }

    public function hasBankDetails(Mou $mou): bool
    {
        return !empty($mou->bank_details) &&
               isset($mou->bank_details['account_number']) &&
               isset($mou->bank_details['ifsc_code']);
    }

    public function hasLegalTerms(Mou $mou): bool
    {
        return !empty($mou->legal_terms) &&
               isset($mou->legal_terms['rent_amount']) &&
               isset($mou->legal_terms['security_deposit']);
    }

    public function hasElectricityBill(Mou $mou): bool
    {
        return $mou->hasMedia('electricity_bill');
    }

    public function canGeneratePdf(Mou $mou): array
    {
        $errors = [];

        if (!$this->isPartyResolved($mou)) {
            $errors[] = 'Party must be resolved.';
        }

        if (!$this->hasBankDetails($mou)) {
            $errors[] = 'Bank details are required.';
        }

        if ((!$mou->type || $mou->type === \App\Domain\Mou\Enums\MouType::ONBOARDING) && !$this->hasElectricityBill($mou)) {
            $errors[] = 'Electricity bill document is required.';
        }

        try {
            $mandatoryDocs = \App\Domain\Shared\Models\KycDocumentType::query()
                ->where('is_active', true)
                ->where('mou_owner_requirement', \App\Domain\Shared\Enums\KycRequirementLevel::MANDATORY)
                ->get();

            foreach ($mandatoryDocs as $doc) {
                if ($doc->code === 'electricity_bill') {
                    continue; // Handled above
                }
                $collection = $mou->getDocumentCollectionName($doc->code, 'owner');
                if (!$mou->hasMedia($collection)) {
                    $errors[] = "{$doc->name} document is required.";
                }
            }

            if (!empty($mou->is_signatory_different)) {
                $mandatorySignatoryDocs = \App\Domain\Shared\Models\KycDocumentType::query()
                    ->where('is_active', true)
                    ->where('mou_signatory_requirement', \App\Domain\Shared\Enums\KycRequirementLevel::MANDATORY)
                    ->get();

                foreach ($mandatorySignatoryDocs as $doc) {
                    $collection = $mou->getDocumentCollectionName($doc->code, 'signatory');
                    if (!$mou->hasMedia($collection)) {
                        $errors[] = "Signatory {$doc->name} document is required.";
                    }
                }
            }
        } catch (\Throwable $e) {
            // Gracefully ignore if database table is not present
        }

        return [
            'is_ready' => empty($errors),
            'errors' => $errors,
        ];
    }
}
