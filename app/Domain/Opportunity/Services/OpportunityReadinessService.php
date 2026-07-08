<?php

namespace App\Domain\Opportunity\Services;

use App\Domain\Opportunity\Models\Opportunity;

class OpportunityReadinessService
{
    public function canCreateMOU(Opportunity $opportunity): array
    {
        $errors = [];

        if (!$opportunity->owner_name || !$opportunity->owner_phone) {
            $errors[] = 'Owner contact information (Name and Phone) is required.';
        }

        if (!$opportunity->expected_rent) {
            $errors[] = 'Expected rent is required.';
        }

        return [
            'is_ready' => empty($errors),
            'errors' => $errors,
        ];
    }
}
