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

        return \Illuminate\Support\Facades\DB::transaction(function () use ($mou) {
            // Logic to extract data from MOU and create Property
            $property = Property::create([
                'code' => null,
                'status' => 'draft',
                'address_line_1' => $mou->opportunity->address,
                'building_name' => $mou->opportunity->title,
                'property_type_id' => $mou->opportunity->estimated_property_type_id,
                'bhk_type_id' => \Illuminate\Support\Facades\DB::table('bhk_types')->where('name', $mou->opportunity->estimated_bhk)->value('id'),
                // ...
            ]);

            $mou->update([
                'property_id' => $property->id,
                'type' => \App\Domain\Mou\Enums\MouType::ONBOARDING,
            ]);

            // Auto-link "Keys" inventory item
            $keysType = \App\Domain\Property\Models\InventoryType::firstOrCreate(
                ['slug' => 'keys'],
                ['name' => 'Keys', 'is_active' => true]
            );

            \App\Domain\Property\Models\PropertyInventory::create([
                'property_id' => $property->id,
                'inventory_type_id' => $keysType->id,
                'count' => 1,
            ]);

            return $property;
        });
    }
}
