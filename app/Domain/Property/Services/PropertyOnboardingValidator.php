<?php

namespace App\Domain\Property\Services;

use App\Domain\Property\Models\Property;

class PropertyOnboardingValidator
{
    public function validate(Property $property): array
    {
        $property->loadMissing(['rooms', 'inventories', 'furnishingType', 'photos', 'documents', 'utilities', 'pricingVersions']);

        $steps = [
            'property_info' => $this->validatePropertyInfo($property),
            'rooms' => $this->validateRooms($property),
            'inventory' => $this->validateInventory($property),
            'photos' => $this->validatePhotos($property),
            'documents' => $this->validateDocuments($property),
            'utilities' => $this->validateUtilities($property),
            'financials' => $this->validateFinancials($property),
        ];

        $completed = count(array_filter($steps, fn($step) => $step['is_valid']));
        $total = count($steps);
        $progress = $total > 0 ? round(($completed / $total) * 100) : 0;

        return [
            'progress' => $progress,
            'is_ready' => $progress == 100,
            'steps' => $steps,
        ];
    }

    protected function validatePropertyInfo(Property $property): array
    {
        $isValid = !empty($property->address_line_1) &&
                   !empty($property->latitude) &&
                   !empty($property->longitude) &&
                   !empty($property->property_type_id) &&
                   !empty($property->floor_space_sqft) &&
                   !empty($property->furnishing_type_id) &&
                   !empty($property->bhk_type_id) &&
                   !empty($property->locality_id);

        return [
            'name' => 'Property Information',
            'is_valid' => $isValid,
            'missing' => $isValid ? [] : ['Ensure Address, Coordinates, Property Type, BHK, Floor Space, Locality, and Furnishing are set.'],
            'tab' => 'main',
        ];
    }

    protected function validateRooms(Property $property): array
    {
        $isValid = $property->rooms->count() > 0;
        
        return [
            'name' => 'Rooms Configuration',
            'is_valid' => $isValid,
            'missing' => $isValid ? [] : ['At least one room is required.'],
            'tab' => 'rooms',
        ];
    }

    protected function validateInventory(Property $property): array
    {
        $rule = $property->furnishingType ? $property->furnishingType->inventory_validation_rule : 'skip';

        $isValid = true;
        $missing = [];

        if ($rule === 'required' && $property->inventories->count() === 0) {
            $isValid = false;
            $missing[] = 'Inventory is required for this furnishing type.';
        }

        // Keys are mandatory for all properties
        $hasKeys = $property->inventories->contains(function ($inventory) {
            return $inventory->inventoryType && strtolower($inventory->inventoryType->slug) === 'keys';
        });

        if (!$hasKeys) {
            $isValid = false;
            $missing[] = 'Keys must be added to the inventory.';
        }

        return [
            'name' => 'Inventory Configuration',
            'is_valid' => $isValid,
            'missing' => $missing,
            'tab' => 'inventories',
        ];
    }

    protected function validatePhotos(Property $property): array
    {
        $hasGeneralPhotos = $property->photos->whereNull('property_room_id')->count() > 0;
        
        return [
            'name' => 'General Photos',
            'is_valid' => $hasGeneralPhotos,
            'missing' => $hasGeneralPhotos ? [] : ['At least one general (non-room) photo is required for marketing.'],
            'tab' => 'photos',
        ];
    }

    protected function validateDocuments(Property $property): array
    {
        $hasPropertyDocs = $property->documents->count() > 0;

        $relevantMou = $property->mous()
            ->whereIn('type', [
                \App\Domain\Mou\Enums\MouType::ONBOARDING,
                \App\Domain\Mou\Enums\MouType::KYC_UPDATE,
                \App\Domain\Mou\Enums\MouType::BANK_DETAILS_UPDATE,
            ])
            ->latest()
            ->first();
        $targetModel = $relevantMou ?? $property;

        $hasMediaDocs = \Spatie\MediaLibrary\MediaCollections\Models\Media::query()
            ->where('model_type', get_class($targetModel))
            ->where('model_id', $targetModel->id)
            ->whereIn('collection_name', ['mou_attachments', 'signatory_documents', 'signed_pdf', 'draft_pdf'])
            ->exists();

        $isValid = $hasPropertyDocs || $hasMediaDocs;

        return [
            'name' => 'Documents',
            'is_valid' => $isValid,
            'missing' => $isValid ? [] : ['At least one document (Owner KYC / MOU Attachment) must be uploaded under Financial Terms & MOU.'],
            'tab' => 'financials',
        ];
    }

    protected function validateUtilities(Property $property): array
    {
        // Require at least one utility (e.g. Electricity)
        $isValid = $property->utilities->count() > 0;
        
        return [
            'name' => 'Utilities Configuration',
            'is_valid' => $isValid,
            'missing' => $isValid ? [] : ['At least one utility (e.g. Electricity) must be configured.'],
            'tab' => 'utilities',
        ];
    }

    protected function validateFinancials(Property $property): array
    {
        $isValid = $property->pricingVersions->count() > 0;
        
        return [
            'name' => 'Financial Configuration',
            'is_valid' => $isValid,
            'missing' => $isValid ? [] : ['Pricing and financial configuration is required.'],
            'tab' => 'pricingVersions',
        ];
    }
}
