<?php

namespace App\Filament\Resources\Operations\MaintenanceRequestResource\Pages;

use App\Filament\Resources\Operations\MaintenanceRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMaintenanceRequest extends CreateRecord
{
    protected static string $resource = MaintenanceRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_id'] = auth()->id();
        $data['status'] = !empty($data['vendor_party_id'])
            ? \App\Domain\Maintenance\Enums\MaintenanceStatus::VENDOR_ASSIGNED
            : \App\Domain\Maintenance\Enums\MaintenanceStatus::SUBMITTED;

        if (!empty($data['vendor_party_id'])) {
            $data['assigned_at'] = now();
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();
        if ($record->items()->count() === 0) {
            $record->items()->create([
                'status' => 'pending',
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
