<?php

namespace App\Filament\Resources\Billing\Pages;

use App\Filament\Resources\Billing\MaintenanceBillingResource;
use Filament\Resources\Pages\EditRecord;

class EditMaintenanceBilling extends EditRecord
{
    protected static string $resource = MaintenanceBillingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
