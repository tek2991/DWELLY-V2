<?php

namespace App\Filament\Resources\TenancyAgreements\Pages;

use App\Filament\Resources\TenancyAgreements\TenancyAgreementResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTenancyAgreement extends EditRecord
{
    protected static string $resource = TenancyAgreementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
