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

    public function callMountedAction(array $arguments = []): mixed
    {
        $result = parent::callMountedAction($arguments);

        $this->dispatch('refresh-onboarding-progress');

        return $result;
    }

    public function updateTableColumnState(string $column, string $record, mixed $input): mixed
    {
        $result = parent::updateTableColumnState($column, $record, $input);

        $this->dispatch('refresh-onboarding-progress');

        return $result;
    }

    public function reorderTable(array $order, string|int|null $draggedRecordKey = null): void
    {
        parent::reorderTable($order, $draggedRecordKey);

        $this->dispatch('refresh-onboarding-progress');
    }

    public function dehydrateLocksDuringPropertyOnboarding(): void
    {
        $this->dispatch('refresh-onboarding-progress');
    }
}

