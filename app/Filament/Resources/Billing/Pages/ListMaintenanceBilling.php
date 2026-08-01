<?php

namespace App\Filament\Resources\Billing\Pages;

use App\Filament\Resources\Billing\MaintenanceBillingResource;
use Filament\Resources\Pages\ListRecords;

class ListMaintenanceBilling extends ListRecords
{
    protected static string $resource = MaintenanceBillingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
