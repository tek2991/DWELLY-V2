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
        $individual = $record->primaryTenant?->party?->individual;

        if ($individual) {
            $data['new_tenant'] = [
                'aadhaar_number' => $individual->aadhaar_number,
                'pan_number' => $individual->pan_number,
                'voter_id' => $individual->voter_id,
            ];
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();
        $primaryParty = $record->primaryTenant?->party;

        if ($primaryParty && ! empty($data['new_tenant'])) {
            $tenantData = $data['new_tenant'];
            $updateData = array_filter([
                'aadhaar_number' => $tenantData['aadhaar_number'] ?? null,
                'pan_number' => $tenantData['pan_number'] ?? null,
                'voter_id' => $tenantData['voter_id'] ?? null,
            ]);
            if (! empty($updateData)) {
                $primaryParty->individual()->updateOrCreate([], $updateData);
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
