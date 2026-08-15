<?php

namespace App\Filament\Resources\Billing\MaintenanceQuotationResource\Pages;

use App\Filament\Resources\Billing\MaintenanceQuotationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMaintenanceQuotations extends ListRecords
{
    protected static string $resource = MaintenanceQuotationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
