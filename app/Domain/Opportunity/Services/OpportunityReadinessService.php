<?php

namespace App\Domain\Opportunity\Services;

use App\Domain\Opportunity\Models\Opportunity;

class OpportunityReadinessService
{
    public function canCreateMOU(Opportunity $opportunity): array
    {
        $errors = [];

        if (!$opportunity->title) {
            $errors[] = 'Title is required.';
        }

        if (!$opportunity->owner_name || !$opportunity->owner_phone) {
            $errors[] = 'Owner contact information (Name and Phone) is required.';
        }

        if (!$opportunity->assigned_user_id) {
            $errors[] = 'Assigned user is required.';
        }

        return [
            'is_ready' => empty($errors),
            'errors' => $errors,
        ];
    }
}
