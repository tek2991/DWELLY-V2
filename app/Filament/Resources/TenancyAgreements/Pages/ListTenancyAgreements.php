<?php

namespace App\Filament\Resources\TenancyAgreements\Pages;

use App\Filament\Resources\TenancyAgreements\TenancyAgreementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTenancyAgreements extends ListRecords
{
    protected static string $resource = TenancyAgreementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
