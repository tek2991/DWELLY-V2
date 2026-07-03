<?php

namespace App\Filament\Resources\Parties\Pages;

use App\Filament\Resources\Parties\PartyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditParty extends EditRecord
{
    protected static string $resource = PartyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if ($this->record->party_type === 'individual' && $this->record->individual) {
            $data['individual_data'] = $this->record->individual->toArray();
        } elseif ($this->record->party_type === 'organization' && $this->record->organization) {
            $data['organization_data'] = $this->record->organization->toArray();
        }
        
        if ($this->record->ownerProfile()->exists()) {
            $data['profile_type'] = 'owner';
        } elseif ($this->record->tenantProfile()->exists()) {
            $data['profile_type'] = 'tenant';
        } elseif ($this->record->vendorProfile()->exists()) {
            $data['profile_type'] = 'vendor';
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $individualData = $data['individual_data'] ?? null;
        $organizationData = $data['organization_data'] ?? null;
        
        unset($data['individual_data'], $data['organization_data'], $data['profile_type']);
        
        $record->update($data);

        if ($record->party_type === 'individual' && $individualData) {
            $record->individual()->updateOrCreate(['party_id' => $record->id], $individualData);
        } elseif ($record->party_type === 'organization' && $organizationData) {
            $record->organization()->updateOrCreate(['party_id' => $record->id], $organizationData);
        }

        return $record;
    }
}
