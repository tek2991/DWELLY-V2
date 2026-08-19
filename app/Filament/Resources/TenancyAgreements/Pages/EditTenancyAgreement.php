<?php

namespace App\Filament\Resources\TenancyAgreements\Pages;

use App\Filament\Resources\TenancyAgreements\Pages\Concerns\HasTenancyWorkflowHeader;
use App\Filament\Resources\TenancyAgreements\Schemas\TenancyAgreementForm;
use App\Filament\Resources\TenancyAgreements\TenancyAgreementResource;
use BackedEnum;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class EditTenancyAgreement extends EditRecord
{
    use HasTenancyWorkflowHeader;

    protected static string $resource = TenancyAgreementResource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = '1. Move-In Audit';

    protected static ?string $title = 'Tenancy Agreement – Move-In Audit & Overview';

    public function form(Schema $schema): Schema
    {
        return TenancyAgreementForm::configureOverviewForm($schema);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $primaryRole = $this->getRecord()->roles()->where('is_primary', true)->first();
        if ($primaryRole) {
            $data['primary_tenant_id'] = $primaryRole->party_id;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['primary_tenant_id'])) {
            $primaryTenantId = $data['primary_tenant_id'];
            unset($data['primary_tenant_id']);

            $this->getRecord()->roles()->updateOrCreate(
                ['is_primary' => true],
                ['party_id' => $primaryTenantId, 'role_type' => 'Primary Tenant']
            );
        }

        return $data;
    }

    protected function getFormActions(): array
    {
        if (in_array($this->getRecord()?->status, ['active', 'vacated'])) {
            return [];
        }

        return parent::getFormActions();
    }

    protected function getRedirectUrl(): ?string
    {
        return null;
    }
}
