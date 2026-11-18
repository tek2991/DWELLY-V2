<?php

namespace App\Filament\Resources\TenancyAgreements\Pages;

use App\Filament\Resources\TenancyAgreements\Pages\Concerns\HasTenancyWorkflowHeader;
use App\Filament\Resources\TenancyAgreements\Schemas\TenancyAgreementForm;
use App\Filament\Resources\TenancyAgreements\TenancyAgreementResource;
use BackedEnum;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageTenantKyc extends EditRecord
{
    use HasTenancyWorkflowHeader;

    protected static string $resource = TenancyAgreementResource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $navigationLabel = '3. Tenant KYC & Occupants';

    protected static ?string $title = 'Tenancy Agreement – Tenant KYC & Secondary Occupants';

    public function form(Schema $schema): Schema
    {
        return TenancyAgreementForm::configureTenantKycAndOccupantsForm($schema);
    }

    protected function getFormActions(): array
    {
        $record = $this->getRecord();
        $user = auth()->user();

        if (in_array($record?->status, ['active', 'vacated', 'terminated', 'deboarded'])) {
            return [];
        }

        if ($user && ! $user->can('update', $record)) {
            return [];
        }

        return parent::getFormActions();
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        $primaryParty = $record->primaryTenant?->party ?? $record->tenants()->first();
        $individual = $primaryParty?->individual;
        $address = $primaryParty?->addresses()->where('is_primary', true)->first() ?? $primaryParty?->addresses?->first();

        if ($primaryParty) {
            $data['new_tenant'] = [
                'display_name' => $primaryParty->display_name,
                'phone' => $primaryParty->phone,
                'email' => $primaryParty->email,
                'parent_name' => $individual?->parent_name,
                'aadhaar_number' => $individual?->aadhaar_number,
                'pan_number' => $individual?->pan_number,
                'voter_id' => $individual?->voter_id,
                'address_line_1' => $address?->address_line_1,
            ];
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();
        $primaryParty = $record->primaryTenant?->party ?? $record->tenants()->first();

        if ($primaryParty && ! empty($data['new_tenant'])) {
            $tenantData = $data['new_tenant'];

            // 1. Update Party basic info
            $partyUpdates = [];
            if (isset($tenantData['display_name'])) {
                $partyUpdates['display_name'] = $tenantData['display_name'];
            }
            if (array_key_exists('phone', $tenantData)) {
                $partyUpdates['phone'] = $tenantData['phone'];
            }
            if (array_key_exists('email', $tenantData)) {
                $partyUpdates['email'] = $tenantData['email'];
            }
            if (! empty($partyUpdates)) {
                $primaryParty->update($partyUpdates);
            }

            // 2. Update Party Individual details
            $individualUpdates = [];
            if (isset($tenantData['display_name'])) {
                $individualUpdates['name'] = $tenantData['display_name'];
            }
            if (array_key_exists('parent_name', $tenantData)) {
                $individualUpdates['parent_name'] = $tenantData['parent_name'];
            }
            if (array_key_exists('aadhaar_number', $tenantData)) {
                $individualUpdates['aadhaar_number'] = $tenantData['aadhaar_number'];
            }
            if (array_key_exists('pan_number', $tenantData)) {
                $individualUpdates['pan_number'] = $tenantData['pan_number'];
            }
            if (array_key_exists('voter_id', $tenantData)) {
                $individualUpdates['voter_id'] = $tenantData['voter_id'];
            }
            if (! empty($individualUpdates)) {
                $primaryParty->individual()->updateOrCreate([], $individualUpdates);
            }

            // 3. Update Primary Residence Address
            if (! empty($tenantData['address_line_1'])) {
                $primaryAddress = $primaryParty->addresses()->where('is_primary', true)->first();
                if ($primaryAddress) {
                    $primaryAddress->update(['address_line_1' => $tenantData['address_line_1']]);
                } else {
                    $primaryParty->addresses()->create([
                        'address_line_1' => $tenantData['address_line_1'],
                        'is_primary' => true,
                    ]);
                }
            }

            // 4. Keep agreement's tenant_bank_details PAN in sync if bank details PAN is empty
            if (! empty($tenantData['pan_number'])) {
                $bankDetails = $record->tenant_bank_details ?? [];
                if (is_array($bankDetails) && empty($bankDetails['pan_number'])) {
                    $bankDetails['pan_number'] = $tenantData['pan_number'];
                    $record->update(['tenant_bank_details' => $bankDetails]);
                }
            }
        }

        unset($data['new_tenant'], $data['kyc_documents_upload']);

        return $data;
    }

    protected function getRedirectUrl(): ?string
    {
        return null;
    }
}
