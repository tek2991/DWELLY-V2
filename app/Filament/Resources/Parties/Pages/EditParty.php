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
        
        $roles = [];
        if ($this->record->ownerProfile()->exists()) {
            $roles[] = 'owner';
        }
        if ($this->record->tenantProfile()->exists()) {
            $roles[] = 'tenant';
        }
        if ($this->record->vendorProfile()->exists()) {
            $roles[] = 'vendor';
        }
        $data['roles'] = $roles;

        $bank = $this->record->bankAccounts()->where('is_primary', true)->first();
        if ($bank) {
            $data['bank_details'] = [
                'bank_beneficiary_name' => $bank->account_name,
                'bank_name' => $bank->bank_name,
                'bank_address' => $bank->bank_address,
                'bank_account_no' => $bank->account_number,
                'bank_ifsc_code' => $bank->ifsc_code,
            ];
        }

        $billing = $this->record->addresses()->where('type', 'billing')->first();
        $shipping = $this->record->addresses()->where('type', 'shipping')->first();
        if ($billing || $shipping) {
            $data['address_details'] = [
                'billing_address' => $billing?->address_line_1,
                'shipping_address' => $shipping?->address_line_1,
            ];
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(\App\Domain\Party\Services\PartyService::class)->updateParty($record, $data);
    }
}
