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

        if ($mou->type && $mou->type !== \App\Domain\Mou\Enums\MouType::ONBOARDING) {
            throw new Exception("Only onboarding MOUs can be converted to a new property.");
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($mou) {
            $cityId = $mou->legal_terms['city_id'] ?? null;
            $localityId = null;
            $cityName = $mou->legal_terms['city_name'] ?? null;

            if ($cityId) {
                $cityModel = \App\Domain\Geographic\Models\City::find($cityId);
                if ($cityModel) {
                    $cityName = $cityModel->name;
                    $locality = \App\Domain\Geographic\Models\Locality::where('city_id', $cityId)->first();
                    if (!$locality) {
                        $locality = \App\Domain\Geographic\Models\Locality::create([
                            'city_id' => $cityId,
                            'name' => 'General ' . $cityName,
                            'slug' => \Illuminate\Support\Str::slug('general-' . $cityName . '-' . \Illuminate\Support\Str::random(5)),
                            'is_active' => true,
                        ]);
                    }
                    $localityId = $locality->id;
                }
            }

            // Logic to extract data from MOU and create Property
            $property = Property::create([
                'code' => null,
                'status' => 'draft',
                'address_line_1' => $mou->legal_terms['address'] ?? $mou->opportunity?->address,
                'building_name' => $mou->opportunity?->title,
                'property_type_id' => $mou->opportunity?->estimated_property_type_id,
                'bhk_type_id' => \Illuminate\Support\Facades\DB::table('bhk_types')->where('name', $mou->opportunity?->estimated_bhk)->value('id'),
                'locality_id' => $localityId,
                'city' => $cityName,
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

            $pricingModelName = $mou->legal_terms['financial_model_name']
                ?? (isset($mou->legal_terms['financial_model_id']) ? \App\Domain\Opportunity\Models\FinancialModel::find($mou->legal_terms['financial_model_id'])?->name : null)
                ?? $mou->opportunity->expectedFinancialModel?->name
                ?? 'Standard';

            \App\Domain\Property\Models\PropertyFinancialTerm::create([
                'property_id' => $property->id,
                'mou_id' => $mou->id,
                'pricing_model' => $pricingModelName,
                'fee_percentage' => $mou->legal_terms['fee_percentage'] ?? null,
                'effective_from' => $mou->start_date ?? now(),
            ]);

            return $property;
        });
    }
}
