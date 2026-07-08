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

    public function canGeneratePdf(Mou $mou): array
    {
        $errors = [];

        if (!$this->isPartyResolved($mou)) {
            $errors[] = 'Party must be resolved.';
        }

        if (!$this->hasBankDetails($mou)) {
            $errors[] = 'Bank details are required.';
        }

        if (!$this->hasLegalTerms($mou)) {
            $errors[] = 'Legal terms are required.';
        }

        return [
            'is_ready' => empty($errors),
            'errors' => $errors,
        ];
    }
}
