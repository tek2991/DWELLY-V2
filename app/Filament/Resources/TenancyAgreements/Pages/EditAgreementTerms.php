<?php

namespace App\Filament\Resources\TenancyAgreements\Pages;

use App\Filament\Resources\TenancyAgreements\Pages\Concerns\HasTenancyWorkflowHeader;
use App\Filament\Resources\TenancyAgreements\Schemas\TenancyAgreementForm;
use App\Filament\Resources\TenancyAgreements\TenancyAgreementResource;
use BackedEnum;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class EditAgreementTerms extends EditRecord
{
    use HasTenancyWorkflowHeader;

    protected static string $resource = TenancyAgreementResource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyRupee;

    protected static ?string $navigationLabel = '2. Agreement Terms';

    protected static ?string $title = 'Tenancy Agreement – Commercial Terms & Banking';

    public function form(Schema $schema): Schema
    {
        return TenancyAgreementForm::configureTermsForm($schema);
    }

    protected function getFormActions(): array
    {
        $record = $this->getRecord();
        $user = auth()->user();

        if ($user && ! $user->can('update', $record)) {
            return [];
        }

        if (in_array($record?->status, ['active', 'vacated', 'terminated', 'deboarded'])) {
            if ($user && ! $user->can('updateTerms', $record)) {
                return [];
            }
        }

        return parent::getFormActions();
    }

    protected function getRedirectUrl(): ?string
    {
        return null;
    }
}
