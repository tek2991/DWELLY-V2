<?php

namespace App\Filament\Resources\Properties\RelationManagers\Traits;

trait LocksDuringPropertyOnboarding
{
    public function isReadOnly(): bool
    {
        $property = $this->getOwnerRecord();
        
        if ($property instanceof \App\Domain\Property\Models\Property && $property->isLockedDuringOnboarding()) {
            return true;
        }

        return parent::isReadOnly();
    }
}
