<?php

namespace App\Filament\Resources\Properties\RelationManagers\Traits;

use App\Filament\Resources\Properties\Pages\OnboardingDashboard;

trait LocksDuringPropertyOnboarding
{
    public function isReadOnly(): bool
    {
        if ($this->getPageClass() === OnboardingDashboard::class) {
            return parent::isReadOnly();
        }

        $property = $this->getOwnerRecord();
        
        if ($property instanceof \App\Domain\Property\Models\Property && $property->isLockedDuringOnboarding()) {
            return true;
        }

        return parent::isReadOnly();
    }
}

