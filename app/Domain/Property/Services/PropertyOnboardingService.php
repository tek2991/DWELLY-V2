<?php

namespace App\Domain\Property\Services;

use App\Domain\Mou\Models\Mou;
use App\Domain\Property\Models\Property;
use Exception;

class PropertyOnboardingService
{
    /**
     * Create an initial Property record from a verified MOU.
     * This acts as the handoff from Legal to Operations.
     */
    public function createPropertyFromMou(Mou $mou): Property
    {
        if ($mou->status->value !== 'verified') {
            throw new Exception("Cannot convert to property. MOU is not verified.");
        }

        // Logic to extract data from MOU and create Property
        $property = Property::create([
            // Normally we'd copy address, rent, etc. Since Property is currently lightweight,
            // we will populate what's available or set defaults for onboarding.
            // e.g. 'title' => "Property for " . $mou->opportunity->title,
        ]);

        // If Property model had links to MOU or Party we would set them here.
        // e.g.
        // $property->mou_id = $mou->id;
        // $property->owner_party_id = $mou->party_id;
        // $property->save();

        // Note: The UI/Controller that calls this should subsequently
        // redirect the user to the "Onboarding Workspace" for this new Property.

        return $property;
    }
}
